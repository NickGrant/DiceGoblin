param(
  [int]$PollMinutes = 5,
  [int]$DurationMinutes = 60,
  [string]$RepoRoot = (Get-Location).Path,
  [string]$CodexPath = "",
  [string]$StatePath = "",
  [string]$LogPath = "",
  [string]$CodexOutputPath = "",
  [int]$CodexCooldownMinutes = 15,
  [int]$CodexTimeoutMinutes = 30,
  [bool]$AutoCommitAndPush = $true
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"
if (Get-Variable -Name PSNativeCommandUseErrorActionPreference -ErrorAction SilentlyContinue) {
  $PSNativeCommandUseErrorActionPreference = $false
}

function Test-ProcessRunning {
  param([int]$ProcessId)

  try {
    $null = Get-Process -Id $ProcessId -ErrorAction Stop
    return $true
  } catch {
    return $false
  }
}

function Write-Log {
  param(
    [string]$Message,
    [string]$Level = "INFO"
  )

  $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
  $line = "[$timestamp] [$Level] $Message"
  Write-Host $line
  Add-Content -LiteralPath $script:LogPathResolved -Value $line
}

function Ensure-ParentDirectory {
  param([string]$Path)

  $parent = Split-Path -Parent $Path
  if ($parent -and -not (Test-Path -LiteralPath $parent)) {
    New-Item -ItemType Directory -Path $parent | Out-Null
  }
}

function ConvertTo-TrimmedText {
  param([AllowNull()][object]$Value)

  if ($null -eq $Value) {
    return ""
  }

  if ($Value -is [string]) {
    return $Value.Trim()
  }

  if ($Value -is [System.Collections.IEnumerable] -and -not ($Value -is [string])) {
    $parts = @()
    foreach ($item in $Value) {
      if ($null -ne $item) {
        $parts += [string]$item
      }
    }

    return ([string]::Join([Environment]::NewLine, $parts)).Trim()
  }

  return ([string]$Value).Trim()
}

function Read-FileTextOrEmpty {
  param([string]$Path)

  if (-not (Test-Path -LiteralPath $Path)) {
    return ""
  }

  $raw = Get-Content -LiteralPath $Path -Raw -ErrorAction SilentlyContinue
  if ($null -eq $raw) {
    return ""
  }

  return [string]$raw
}

function Get-ChildProcessIds {
  param([int]$ParentProcessId)

  $children = @(Get-CimInstance Win32_Process -Filter "ParentProcessId = $ParentProcessId" -ErrorAction SilentlyContinue)
  $allIds = @()
  foreach ($child in $children) {
    $allIds += [int]$child.ProcessId
    $allIds += @(Get-ChildProcessIds -ParentProcessId ([int]$child.ProcessId))
  }

  return $allIds
}

function Stop-ProcessTree {
  param([int]$ProcessId)

  $descendants = @(Get-ChildProcessIds -ParentProcessId $ProcessId)
  foreach ($childId in ($descendants | Sort-Object -Descending -Unique)) {
    try {
      Stop-Process -Id $childId -Force -ErrorAction Stop
    } catch {
    }
  }

  try {
    Stop-Process -Id $ProcessId -Force -ErrorAction Stop
  } catch {
  }
}

function Resolve-CodexExecutable {
  param([string]$RequestedPath)

  $candidates = [System.Collections.Generic.List[string]]::new()

  if (-not [string]::IsNullOrWhiteSpace($RequestedPath)) {
    $candidates.Add($RequestedPath)
  }

  $command = Get-Command codex -ErrorAction SilentlyContinue
  if ($command -and $command.Source) {
    $candidates.Add($command.Source)
  }

  try {
    $whereOutput = cmd.exe /d /c "where codex 2>nul"
    if ($LASTEXITCODE -eq 0) {
      foreach ($result in $whereOutput) {
        if (-not [string]::IsNullOrWhiteSpace($result)) {
          $candidates.Add($result.Trim())
        }
      }
    }
  } catch {
  }

  $knownPaths = @(
    "$env:USERPROFILE\AppData\Local\Programs\OpenAI\Codex\codex.exe"
  )
  foreach ($path in $knownPaths) {
    if (-not [string]::IsNullOrWhiteSpace($path)) {
      $candidates.Add($path)
    }
  }

  $vscodeExtensionRoots = @(Get-ChildItem -Path "$env:USERPROFILE\.vscode\extensions" -Directory -Filter "openai.chatgpt-*" -ErrorAction SilentlyContinue | Sort-Object Name -Descending)
  foreach ($root in $vscodeExtensionRoots) {
    $bundledCodex = Join-Path $root.FullName "bin\windows-x86_64\codex.exe"
    if (Test-Path -LiteralPath $bundledCodex) {
      $candidates.Add($bundledCodex)
    }
  }

  foreach ($candidate in $candidates) {
    $expanded = [Environment]::ExpandEnvironmentVariables($candidate)
    if (Test-Path -LiteralPath $expanded) {
      return (Resolve-Path -LiteralPath $expanded).Path
    }
  }

  throw "Unable to resolve codex.exe. Pass -CodexPath explicitly or add Codex to PATH."
}

function Remove-StaleFileLock {
  param(
    [string]$Path,
    [int]$MinimumAgeMinutes = 2
  )

  if (-not (Test-Path -LiteralPath $Path)) {
    return $false
  }

  try {
    $item = Get-Item -LiteralPath $Path
    $ageMinutes = ((Get-Date) - $item.LastWriteTime).TotalMinutes
    if ($ageMinutes -lt $MinimumAgeMinutes) {
      Write-Log -Level "WARN" -Message "Lock file $Path is only $([math]::Round($ageMinutes, 2)) minute(s) old. Leaving it in place."
      return $false
    }
  } catch {
    Write-Log -Level "WARN" -Message "Could not inspect lock file ${Path}: $($_.Exception.Message)"
  }

  try {
    Remove-Item -LiteralPath $Path -Force
    Write-Log -Level "WARN" -Message "Removed stale lock file $Path"
    return $true
  } catch {
    Write-Log -Level "WARN" -Message "Could not remove lock file ${Path}: $($_.Exception.Message)"
    return $false
  }
}

function Acquire-WatcherMutex {
  if (Test-Path -LiteralPath $script:WatcherLockPath) {
    try {
      $lockInfo = Get-Content -LiteralPath $script:WatcherLockPath -Raw | ConvertFrom-Json
      $existingPid = [int]$lockInfo.pid
      if (Test-ProcessRunning -ProcessId $existingPid) {
        throw "Another watcher instance is already running with PID $existingPid."
      }

      Write-Log -Level "WARN" -Message "Found stale watcher lock for PID $existingPid. Reclaiming it."
      Remove-Item -LiteralPath $script:WatcherLockPath -Force
    } catch {
      if (Test-Path -LiteralPath $script:WatcherLockPath) {
        try {
          Remove-Item -LiteralPath $script:WatcherLockPath -Force
          Write-Log -Level "WARN" -Message "Removed unreadable or stale watcher lock file."
        } catch {
          throw "Another watcher instance may be running, and the watcher lock could not be reclaimed."
        }
      }
    }
  }

  $payload = [ordered]@{
    pid = $PID
    startedAtUtc = [datetime]::UtcNow.ToString("o")
    repoRoot = $script:RepoRootResolved
  } | ConvertTo-Json

  Set-Content -LiteralPath $script:WatcherLockPath -Value $payload
}

function Release-WatcherMutex {
  if (Test-Path -LiteralPath $script:WatcherLockPath) {
    try {
      Remove-Item -LiteralPath $script:WatcherLockPath -Force
    } catch {
      Write-Log -Level "WARN" -Message "Failed to remove watcher lock file: $($_.Exception.Message)"
    }
  }
}

function Invoke-Git {
  param(
    [string[]]$Arguments,
    [switch]$AllowFailure
  )

  $previousErrorAction = $ErrorActionPreference
  $ErrorActionPreference = "Continue"
  try {
    $output = & git @Arguments 2>&1
    $exitCode = $LASTEXITCODE
  } finally {
    $ErrorActionPreference = $previousErrorAction
  }

  if (-not $AllowFailure -and $exitCode -ne 0) {
    throw "git $($Arguments -join ' ') failed with exit code $exitCode`n$output"
  }

  [PSCustomObject]@{
    ExitCode = $exitCode
    Output = ConvertTo-TrimmedText -Value $output
  }
}

function Invoke-Codex {
  param(
    [string]$Prompt,
    [string]$IssueTitle = ""
  )

  $promptPath = Join-Path $script:ArtifactsDirResolved "codex-prompt.txt"
  $stdoutPath = Join-Path $script:ArtifactsDirResolved "codex-stdout.txt"
  $stderrPath = Join-Path $script:ArtifactsDirResolved "codex-stderr.txt"
  $resultPath = Join-Path $script:ArtifactsDirResolved "codex-result.json"
  $workerPath = Join-Path $script:ArtifactsDirResolved "codex-worker.ps1"

  Set-Content -LiteralPath $promptPath -Value $Prompt
  Remove-Item -LiteralPath $stdoutPath,$stderrPath,$resultPath -Force -ErrorAction SilentlyContinue

  $workerScript = @'
param(
  [string]$CodexExe,
  [string]$RepoRoot,
  [string]$CodexOutputPath,
  [string]$PromptPath,
  [string]$ResultPath
)

$ErrorActionPreference = "Continue"
if (Get-Variable -Name PSNativeCommandUseErrorActionPreference -ErrorAction SilentlyContinue) {
  $PSNativeCommandUseErrorActionPreference = $false
}

$arguments = @(
  "-a", "never",
  "-s", "danger-full-access",
  "exec",
  "-C", $RepoRoot,
  "--output-last-message", $CodexOutputPath,
  "-"
)

$output = (Get-Content -LiteralPath $PromptPath -Raw) | & $CodexExe @arguments 2>&1
$exitCode = $LASTEXITCODE
$payload = [ordered]@{
  exitCode = $exitCode
  output = if ($null -eq $output) { "" } else { ($output | Out-String).Trim() }
} | ConvertTo-Json -Depth 4
Set-Content -LiteralPath $ResultPath -Value $payload
'@
  Set-Content -LiteralPath $workerPath -Value $workerScript

  $workerArgs = @(
    "-NoProfile",
    "-ExecutionPolicy", "Bypass",
    "-File", $workerPath,
    $script:CodexExe,
    $script:RepoRootResolved,
    $script:CodexOutputPathResolved,
    $promptPath,
    $resultPath
  )

  $worker = Start-Process -FilePath "powershell.exe" -ArgumentList $workerArgs -RedirectStandardOutput $stdoutPath -RedirectStandardError $stderrPath -PassThru -WindowStyle Hidden
  Update-CodexLockPid -ProcessId $worker.Id -Metadata $IssueTitle

  try {
    if (-not $worker.WaitForExit($CodexTimeoutMinutes * 60 * 1000)) {
      Stop-ProcessTree -ProcessId $worker.Id
      return [PSCustomObject]@{
        ExitCode = 124
        Output = "Codex execution timed out after $CodexTimeoutMinutes minute(s)."
      }
    }
  } catch {
    Stop-ProcessTree -ProcessId $worker.Id
    throw
  }

  $resultRaw = Read-FileTextOrEmpty -Path $resultPath
  $stderrText = ConvertTo-TrimmedText -Value (Read-FileTextOrEmpty -Path $stderrPath)

  if (-not [string]::IsNullOrWhiteSpace($resultRaw)) {
    try {
      $result = $resultRaw | ConvertFrom-Json
      $combinedOutput = @($result.output, $stderrText) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
      return [PSCustomObject]@{
        ExitCode = [int]$result.exitCode
        Output = ConvertTo-TrimmedText -Value ($combinedOutput -join [Environment]::NewLine)
      }
    } catch {
    }
  }

  return [PSCustomObject]@{
    ExitCode = $worker.ExitCode
    Output = $stderrText
  }
}

function Try-AcquireCodexLock {
  param([string]$Metadata = "")

  if (Test-Path -LiteralPath $script:CodexLockPath) {
    try {
      $lockInfo = Get-Content -LiteralPath $script:CodexLockPath -Raw | ConvertFrom-Json
      $lockPid = 0
      if ($lockInfo.PSObject.Properties.Name -contains "pid") {
        $lockPid = [int]$lockInfo.pid
      }

      if ($lockPid -gt 0 -and (Test-ProcessRunning -ProcessId $lockPid)) {
        return $false
      }
    } catch {
    }

    if (-not (Remove-StaleFileLock -Path $script:CodexLockPath -MinimumAgeMinutes 1)) {
      return $false
    }
  }

  $payload = [ordered]@{
    pid = 0
    startedAtUtc = [datetime]::UtcNow.ToString("o")
    metadata = $Metadata
  } | ConvertTo-Json

  Set-Content -LiteralPath $script:CodexLockPath -Value $payload
  return $true
}

function Release-CodexLock {
  if (Test-Path -LiteralPath $script:CodexLockPath) {
    Remove-Item -LiteralPath $script:CodexLockPath -Force -ErrorAction SilentlyContinue
  }
}

function Update-CodexLockPid {
  param(
    [int]$ProcessId,
    [string]$Metadata = ""
  )

  $payload = [ordered]@{
    pid = $ProcessId
    startedAtUtc = [datetime]::UtcNow.ToString("o")
    metadata = $Metadata
  } | ConvertTo-Json

  Set-Content -LiteralPath $script:CodexLockPath -Value $payload
}

function Get-FileHashSafe {
  param([string]$Path)

  if (-not (Test-Path -LiteralPath $Path)) {
    return ""
  }

  return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash
}

function Get-Blocks {
  param([string]$Raw)

  if ([string]::IsNullOrWhiteSpace($Raw)) {
    return @()
  }

  return ($Raw -split "(?m)^\-\-\-\s*$") |
    ForEach-Object { $_.Trim() } |
    Where-Object { $_ }
}

function Get-GitStatusPaths {
  param([string]$StatusOutput)

  if ([string]::IsNullOrWhiteSpace($StatusOutput)) {
    return @()
  }

  $paths = @()
  foreach ($line in ($StatusOutput -split "`r?`n")) {
    if ([string]::IsNullOrWhiteSpace($line) -or $line.Length -lt 4) {
      continue
    }

    $pathText = $line.Substring(3).Trim()
    if ($pathText -match "\s+->\s+") {
      $pathText = ($pathText -split "\s+->\s+")[-1].Trim()
    }

    if (-not [string]::IsNullOrWhiteSpace($pathText)) {
      $paths += $pathText
    }
  }

  return @($paths | Sort-Object -Unique)
}

function Get-DirtyGitPaths {
  $pathBuckets = @(
    (Invoke-Git -Arguments @("diff", "--name-only")).Output,
    (Invoke-Git -Arguments @("diff", "--cached", "--name-only")).Output,
    (Invoke-Git -Arguments @("ls-files", "--others", "--exclude-standard")).Output
  )

  $paths = @()
  foreach ($bucket in $pathBuckets) {
    if ([string]::IsNullOrWhiteSpace($bucket)) {
      continue
    }

    $paths += $bucket -split "`r?`n" |
      ForEach-Object { $_.Trim() } |
      Where-Object { $_ -notmatch "^(warning|error):" } |
      Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
  }

  return @($paths | Sort-Object -Unique)
}

function Get-FieldValue {
  param(
    [string]$Block,
    [string]$Name
  )

  $pattern = "(?m)^" + [regex]::Escape($Name) + ":\s*(.+)$"
  $match = [regex]::Match($Block, $pattern)
  if ($match.Success) {
    return $match.Groups[1].Value.Trim()
  }

  return $null
}

function Get-BulletListValue {
  param(
    [string]$Block,
    [string]$Name
  )

  $pattern = "(?ms)^" + [regex]::Escape($Name) + ":\s*\r?\n(.*?)(?:\r?\n[a-z_]+:\s|$)"
  $match = [regex]::Match($Block, $pattern)
  if (-not $match.Success) {
    return @()
  }

  return $match.Groups[1].Value -split "`r?`n" |
    ForEach-Object { $_.Trim() } |
    Where-Object { $_.StartsWith("- ") } |
    ForEach-Object { $_.Substring(2).Trim() } |
    Where-Object { $_ }
}

function Get-Issues {
  param([string]$Path)

  if (-not (Test-Path -LiteralPath $Path)) {
    return @()
  }

  $raw = Get-Content -LiteralPath $Path -Raw
  $issues = @()

  $milestoneSections = $raw -split "(?m)^\#\#\s+"
  foreach ($section in $milestoneSections | Select-Object -Skip 1) {
    $sectionLines = $section -split "`r?`n"
    $milestoneName = $sectionLines[0].Trim()
    $sectionBody = ($sectionLines | Select-Object -Skip 1) -join [Environment]::NewLine
    $issueBlocks = $sectionBody -split "(?m)^\#\#\#\s+"

    foreach ($block in $issueBlocks | Select-Object -Skip 1) {
      $blockLines = $block -split "`r?`n"
      $title = $blockLines[0].Trim()
      $body = ($blockLines | Select-Object -Skip 1) -join [Environment]::NewLine

      $issues += [PSCustomObject]@{
        Title = $title
        Status = Get-MarkdownFieldValue -Block $body -Name "Status"
        Priority = Get-MarkdownFieldValue -Block $body -Name "Priority"
        Milestone = $milestoneName
        Body = $body
        Description = if ($body -match "(?im)^####\s+Problem") { "present" } else { $null }
      }
    }
  }

  return $issues
}

function Get-Milestones {
  param([string]$Path)

  if (-not (Test-Path -LiteralPath $Path)) {
    return @()
  }

  $raw = Get-Content -LiteralPath $Path -Raw
  $milestones = @()

  $milestoneBlocks = $raw -split "(?m)^\#\#\s+"
  foreach ($block in $milestoneBlocks | Select-Object -Skip 1) {
    $lines = $block -split "`r?`n"
    $name = $lines[0].Trim()
    if (-not $name) {
      continue
    }

    $body = ($lines | Select-Object -Skip 1) -join [Environment]::NewLine
    $milestones += [PSCustomObject]@{
      Name = $name
      Status = Get-MarkdownFieldValue -Block $body -Name "Status"
      Issues = @(Get-RelatedIssueTitles -Block $body)
    }
  }

  return $milestones
}

function Get-MarkdownFieldValue {
  param(
    [string]$Block,
    [string]$Name
  )

  $pattern = "(?mi)^\*\*" + [regex]::Escape($Name) + ":\*\*\s*(.+)$"
  $match = [regex]::Match($Block, $pattern)
  if ($match.Success) {
    return $match.Groups[1].Value.Trim()
  }

  return $null
}

function ConvertTo-HashtableCompat {
  param([object]$InputObject)

  if ($null -eq $InputObject) {
    return $null
  }

  if ($InputObject -is [System.Collections.IDictionary]) {
    $table = [ordered]@{}
    foreach ($key in $InputObject.Keys) {
      $table[$key] = ConvertTo-HashtableCompat -InputObject $InputObject[$key]
    }
    return $table
  }

  if ($InputObject -is [System.Collections.IEnumerable] -and -not ($InputObject -is [string])) {
    $items = @()
    foreach ($item in $InputObject) {
      $items += ConvertTo-HashtableCompat -InputObject $item
    }
    return $items
  }

  if ($InputObject -is [pscustomobject] -or $InputObject -is [psobject]) {
    $table = [ordered]@{}
    foreach ($property in $InputObject.PSObject.Properties) {
      $table[$property.Name] = ConvertTo-HashtableCompat -InputObject $property.Value
    }
    return $table
  }

  return $InputObject
}

function Get-RelatedIssueTitles {
  param([string]$Block)

  $match = [regex]::Match($Block, "(?ms)^###\s+Related Issues\s*\r?\n(.*?)(?:\r?\n##\s|\z)")
  if (-not $match.Success) {
    return @()
  }

  return $match.Groups[1].Value -split "`r?`n" |
    ForEach-Object { $_.Trim() } |
    Where-Object { $_.StartsWith("- ") } |
    ForEach-Object { ($_ -replace "^-+\s*", "") -replace "^[A-Z0-9-]+:\s*", "" } |
    Where-Object { $_ }
}

function Get-ActionableIssues {
  $milestones = Get-Milestones -Path $script:MilestonesPath
  $issues = Get-Issues -Path $script:IssuesPath

  $currentMilestone = $milestones |
    Where-Object { $_.Status -eq "Active" } |
    Select-Object -First 1

  if (-not $currentMilestone) {
    return [PSCustomObject]@{
      Milestone = $null
      Issues = @()
    }
  }

  $statusRank = @{
    "In Progress" = 0
    "Open" = 1
    "Blocked" = 2
  }

  $priorityRank = @{
    "high" = 0
    "medium" = 1
    "low" = 2
  }

  $milestoneIssueTitles = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::Ordinal)
  foreach ($title in $currentMilestone.Issues) {
    [void]$milestoneIssueTitles.Add($title)
  }

  $actionable = $issues |
    Where-Object {
      $_.Status -in @("In Progress", "Open") -and
      (
        $_.Milestone -eq $currentMilestone.Name -or
        $milestoneIssueTitles.Contains($_.Title)
      )
    } |
    Sort-Object `
      @{ Expression = { $statusRank[$_.Status] } ; Ascending = $true }, `
      @{ Expression = { $priorityRank[$_.Priority] } ; Ascending = $true }, `
      @{ Expression = { $_.Title } ; Ascending = $true }

  return [PSCustomObject]@{
    Milestone = $currentMilestone
    Issues = @($actionable)
  }
}

function Get-BacklogHash {
  $milestonesHash = Get-FileHashSafe -Path $script:MilestonesPath
  $issuesHash = Get-FileHashSafe -Path $script:IssuesPath
  return "$milestonesHash`::$issuesHash"
}

function Get-CurrentExecutionTarget {
  $actionable = Get-ActionableIssues
  $milestone = $actionable.Milestone
  $issues = @($actionable.Issues)

  if (-not $milestone) {
    return [PSCustomObject]@{
      Milestone = $null
      Issue = $null
      RemainingIssueCount = 0
    }
  }

  $issue = $issues |
    Where-Object { $_.Status -eq "In Progress" } |
    Select-Object -First 1

  if (-not $issue) {
    $issue = $issues |
      Where-Object { $_.Status -eq "Open" } |
      Select-Object -First 1
  }

  return [PSCustomObject]@{
    Milestone = $milestone
    Issue = $issue
    RemainingIssueCount = $issues.Count
  }
}

function Set-MarkdownFieldValueInFile {
  param(
    [string]$Path,
    [string]$SectionPattern,
    [string]$FieldName,
    [string]$NewValue
  )

  $raw = Get-Content -LiteralPath $Path -Raw
  $fieldPattern = "(?mi)(?<prefix>$SectionPattern[\s\S]*?^\*\*" + [regex]::Escape($FieldName) + ":\*\*\s*)(?<value>.+)$"
  $match = [regex]::Match($raw, $fieldPattern)
  if (-not $match.Success) {
    throw "Could not update field '$FieldName' in $Path."
  }

  $updated = $raw.Substring(0, $match.Groups["value"].Index) + $NewValue + $raw.Substring($match.Groups["value"].Index + $match.Groups["value"].Length)
  Set-Content -LiteralPath $Path -Value $updated
}

function Set-IssueStatus {
  param(
    [string]$IssueTitle,
    [string]$NewStatus
  )

  $escapedTitle = [regex]::Escape($IssueTitle)
  $sectionPattern = "^###\s+$escapedTitle\s*$"
  Set-MarkdownFieldValueInFile -Path $script:IssuesPath -SectionPattern $sectionPattern -FieldName "Status" -NewValue $NewStatus
  Write-Log -Message "Updated issue '$IssueTitle' to status '$NewStatus'."
}

function Set-MilestoneStatus {
  param(
    [string]$MilestoneName,
    [string]$NewStatus
  )

  $escapedName = [regex]::Escape($MilestoneName)
  $sectionPattern = "^##\s+$escapedName\s*$"
  Set-MarkdownFieldValueInFile -Path $script:MilestonesPath -SectionPattern $sectionPattern -FieldName "Status" -NewValue $NewStatus
  Write-Log -Message "Updated milestone '$MilestoneName' to status '$NewStatus'."
}

function Advance-MilestonesIfNeeded {
  $milestones = Get-Milestones -Path $script:MilestonesPath
  $issues = Get-Issues -Path $script:IssuesPath

  $activeMilestone = $milestones |
    Where-Object { $_.Status -eq "Active" } |
    Select-Object -First 1

  if (-not $activeMilestone) {
    return $false
  }

  $remainingIssues = @(
    $issues |
      Where-Object {
        $_.Milestone -eq $activeMilestone.Name -and
        $_.Status -in @("Open", "In Progress", "Blocked")
      }
  )

  if ($remainingIssues.Count -gt 0) {
    return $false
  }

  Set-MilestoneStatus -MilestoneName $activeMilestone.Name -NewStatus "Complete"

  $nextPlannedMilestone = $milestones |
    Where-Object { $_.Status -eq "Planned" } |
    Select-Object -First 1

  if ($nextPlannedMilestone) {
    Set-MilestoneStatus -MilestoneName $nextPlannedMilestone.Name -NewStatus "Active"
    Write-Log -Message "Advanced next planned milestone '$($nextPlannedMilestone.Name)' to Active."
  } else {
    Write-Log -Message "No planned milestones remain after completing '$($activeMilestone.Name)'."
  }

  return $true
}

function Get-State {
  if (-not (Test-Path -LiteralPath $script:StatePathResolved)) {
    return [ordered]@{
      lastBacklogHash = ""
      lastCodexRunUtc = ""
      lastSyncedHead = ""
    }
  }

  try {
    $raw = Get-Content -LiteralPath $script:StatePathResolved -Raw
    $parsed = $raw | ConvertFrom-Json
    return (ConvertTo-HashtableCompat -InputObject $parsed)
  } catch {
    Write-Log -Level "WARN" -Message "State file was unreadable. Reinitializing state."
    return [ordered]@{
      lastBacklogHash = ""
      lastCodexRunUtc = ""
      lastSyncedHead = ""
    }
  }
}

function Save-State {
  param([hashtable]$State)

  $json = $State | ConvertTo-Json -Depth 6
  $tempPath = "$($script:StatePathResolved).tmp"
  Set-Content -LiteralPath $tempPath -Value $json
  Move-Item -LiteralPath $tempPath -Destination $script:StatePathResolved -Force
}

function Test-CodexCooldown {
  param([hashtable]$State)

  $lastRunRaw = $State["lastCodexRunUtc"]
  if ([string]::IsNullOrWhiteSpace($lastRunRaw)) {
    return $false
  }

  try {
    $lastRun = [datetime]::Parse($lastRunRaw).ToUniversalTime()
  } catch {
    return $false
  }

  $elapsed = ([datetime]::UtcNow - $lastRun).TotalMinutes
  return $elapsed -lt $CodexCooldownMinutes
}

function Invoke-SafeSync {
  function Clear-StaleRemoteRefLocks {
    $lockFiles = @(
      (Join-Path $script:RepoRootResolved ".git\refs\remotes\origin\main.lock"),
      (Join-Path $script:RepoRootResolved ".git\logs\refs\remotes\origin\main.lock")
    )

    $lockRemoved = $false
    foreach ($lockPath in $lockFiles) {
      if (Remove-StaleFileLock -Path $lockPath) {
        $lockRemoved = $true
      }
    }

    return $lockRemoved
  }

  function Invoke-FetchWithRepair {
    $preFetchRepair = Clear-StaleRemoteRefLocks

    $fetch = Invoke-Git -Arguments @("fetch", "--prune", "origin") -AllowFailure
    if ($fetch.ExitCode -eq 0) {
      return $fetch
    }

    if ($fetch.Output -match "reference already exists") {
      $postFailureRepair = Clear-StaleRemoteRefLocks
      if ($postFailureRepair -or $preFetchRepair) {
        Write-Log -Level "WARN" -Message "Retrying git fetch after clearing stale remote-ref lock files."
        $retry = Invoke-Git -Arguments @("fetch", "--prune", "origin") -AllowFailure
        if ($retry.ExitCode -eq 0) {
          return $retry
        }

        throw "git fetch --prune origin failed after lock-file repair`n$($retry.Output)"
      }
    }

    throw "git fetch --prune origin failed`n$($fetch.Output)"
  }

  $status = Invoke-Git -Arguments @("status", "--porcelain")
  $localDirty = -not [string]::IsNullOrWhiteSpace($status.Output)

  $fetch = Invoke-FetchWithRepair
  if ($fetch.Output) {
    Write-Log -Message "git fetch completed: $($fetch.Output -replace '\s+', ' ')"
  } else {
    Write-Log -Message "git fetch completed."
  }

  $upstream = Invoke-Git -Arguments @("rev-parse", "--abbrev-ref", "--symbolic-full-name", "@{u}") -AllowFailure
  if ($upstream.ExitCode -ne 0 -or [string]::IsNullOrWhiteSpace($upstream.Output)) {
    Write-Log -Level "WARN" -Message "No upstream tracking branch found. Skipping sync."
    return
  }

  $head = (Invoke-Git -Arguments @("rev-parse", "HEAD")).Output
  $upstreamHead = (Invoke-Git -Arguments @("rev-parse", "@{u}")).Output
  $mergeBase = (Invoke-Git -Arguments @("merge-base", "HEAD", "@{u}")).Output

  if ($head -eq $upstreamHead) {
    Write-Log -Message "Repository already matches upstream $($upstream.Output)."
    return
  }

  if ($head -ne $mergeBase -and $upstreamHead -ne $mergeBase) {
    Write-Log -Level "WARN" -Message "Local branch has diverged from $($upstream.Output). Skipping auto-pull."
    return
  }

  if ($localDirty) {
    Write-Log -Level "WARN" -Message "Working tree is dirty. Skipping auto-pull to avoid overwriting local work."
    return
  }

  $pull = Invoke-Git -Arguments @("pull", "--ff-only")
  if ($pull.Output) {
    Write-Log -Message "git pull --ff-only completed: $($pull.Output -replace '\s+', ' ')"
  } else {
    Write-Log -Message "git pull --ff-only completed."
  }
}

function Invoke-BacklogValidation {
  $output = & npm.cmd run llm:check 2>&1
  $exitCode = $LASTEXITCODE
  $text = ConvertTo-TrimmedText -Value $output

  if ($exitCode -ne 0) {
    Write-Log -Level "WARN" -Message "Backlog validation failed. Codex trigger skipped. Details: $($text -replace '\s+', ' ')"
    return $false
  }

  Write-Log -Message "Backlog validation passed."
  return $true
}

function Invoke-CommitAndPush {
  param(
    [string]$MilestoneName,
    [bool]$PreRunTreeWasClean,
    [string[]]$PreRunDirtyPaths = @()
  )

  if (-not $AutoCommitAndPush) {
    Write-Log -Message "Auto-commit is disabled for this watcher run."
    return
  }

  $status = Invoke-Git -Arguments @("status", "--porcelain")
  if ([string]::IsNullOrWhiteSpace($status.Output)) {
    Write-Log -Message "Codex completed without filesystem changes to commit."
    return
  }

  if (-not (Invoke-BacklogValidation)) {
    Write-Log -Level "WARN" -Message "Skipping auto-commit because backlog validation failed after the Codex run."
    return
  }

  $currentPaths = @(Get-DirtyGitPaths)
  $preexistingPathSet = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::OrdinalIgnoreCase)
  foreach ($path in $PreRunDirtyPaths) {
    if (-not [string]::IsNullOrWhiteSpace($path)) {
      [void]$preexistingPathSet.Add($path)
    }
  }

  $commitPaths = @(
    $currentPaths |
      Where-Object { -not $preexistingPathSet.Contains($_) }
  )

  if ($commitPaths.Count -eq 0) {
    if ($PreRunTreeWasClean) {
      Write-Log -Level "WARN" -Message "No commitable paths were detected after the Codex run."
    } else {
      Write-Log -Level "WARN" -Message "Skipping auto-commit because all changed paths overlap with pre-existing dirty worktree files."
    }
    return
  }

  Invoke-Git -Arguments (@("add", "--") + $commitPaths) | Out-Null

  $cachedDiff = Invoke-Git -Arguments @("diff", "--cached", "--name-only")
  if ([string]::IsNullOrWhiteSpace($cachedDiff.Output)) {
    Write-Log -Message "No staged diff was produced after staging commitable paths."
    return
  }

  $commitMessage = "chore(auto): codex milestone sync - $MilestoneName"
  Invoke-Git -Arguments @("commit", "-m", $commitMessage) | Out-Null
  Write-Log -Message "Created commit: $commitMessage"

  $push = Invoke-Git -Arguments @("push")
  if ($push.Output) {
    Write-Log -Message "git push completed: $($push.Output -replace '\s+', ' ')"
  } else {
    Write-Log -Message "git push completed."
  }
}

function Invoke-CodexForMilestone {
  param(
    [hashtable]$State,
    [string]$BacklogHash,
    [bool]$PreRunTreeWasClean,
    [string[]]$PreRunDirtyPaths = @()
  )

  $target = Get-CurrentExecutionTarget
  $milestone = $target.Milestone
  $issue = $target.Issue

  if (-not $milestone) {
    Write-Log -Message "Backlog changed, but there is no active milestone to execute."
    $State["lastBacklogHash"] = $BacklogHash
    Save-State -State $State
    return
  }

  if (-not $issue) {
    if (Advance-MilestonesIfNeeded) {
      $State["lastBacklogHash"] = Get-BacklogHash
      Save-State -State $State
    } else {
      Write-Log -Message "Backlog changed, but no actionable issues were found for milestone '$($milestone.Name)'."
      $State["lastBacklogHash"] = $BacklogHash
      Save-State -State $State
    }
    return
  }

  if ($issue.Status -eq "Open") {
    Set-IssueStatus -IssueTitle $issue.Title -NewStatus "In Progress"
    $issue = (Get-CurrentExecutionTarget).Issue
  }

  if (Test-CodexCooldown -State $State) {
    Write-Log -Level "WARN" -Message "Backlog changed, but Codex cooldown is active. Skipping this cycle."
    $State["lastBacklogHash"] = Get-BacklogHash
    Save-State -State $State
    return
  }

  $verificationCommands = @(
    "npm.cmd run llm:check",
    "composer --working-dir=backend test",
    "npm.cmd --prefix frontend run test -- --watch=false --browsers=ChromeHeadless",
    "npm.cmd --prefix frontend run build"
  )

  $prompt = @"
You are working in the Dice Goblins repository at $($script:RepoRootResolved).

Start by reading and following:
- AGENTS.md
- agent/LLM_CONTEXT.md
- agent/ISSUES.md
- agent/MILESTONES.md
- agent/ROLES.md

The watcher has already selected the next issue for you. Do not re-triage the backlog.

Active milestone:
- $($milestone.Name)

Selected issue:
- $($issue.Title)
- Status: $($issue.Status)
- Priority: $($issue.Priority)

Execution requirements:
1. Implement only the selected issue and tightly related verification/doc updates required by repo policy.
2. Run this verification loop until the relevant checks are clean or you hit a real blocker:
$($verificationCommands | ForEach-Object { "- $_" } | Out-String)
3. If a check fails, fix the issue when it is caused by your work and rerun the affected checks.
4. If a failure is clearly pre-existing or blocked externally, stop and explain it clearly.
5. When the selected issue is complete, update the active backlog docs accordingly, including archive movement if the repo policy requires active issues only.
6. Respect dirty-worktree safety. Do not revert user changes you did not make.
"@

  Write-Log -Message "Launching Codex for issue '$($issue.Title)' in milestone '$($milestone.Name)'."
  $result = Invoke-Codex -Prompt $prompt -IssueTitle $issue.Title

  if ($result.ExitCode -ne 0) {
    Write-Log -Level "ERROR" -Message "Codex exec failed with exit code $($result.ExitCode). Details: $($result.Output -replace '\s+', ' ')"
  } else {
    Write-Log -Message "Codex exec completed successfully."
    [void](Advance-MilestonesIfNeeded)
    try {
      Invoke-CommitAndPush -MilestoneName $milestone.Name -PreRunTreeWasClean $PreRunTreeWasClean -PreRunDirtyPaths $PreRunDirtyPaths
    } catch {
      Write-Log -Level "ERROR" -Message $_.Exception.Message
    }
  }

  $State["lastCodexRunUtc"] = [datetime]::UtcNow.ToString("o")
  $State["lastBacklogHash"] = Get-BacklogHash
  $State["lastSyncedHead"] = (Invoke-Git -Arguments @("rev-parse", "HEAD")).Output
  Save-State -State $State
}

$script:RepoRootResolved = (Resolve-Path -LiteralPath $RepoRoot).Path
$script:CodexExe = Resolve-CodexExecutable -RequestedPath $CodexPath
$defaultArtifactsDir = Join-Path $script:RepoRootResolved "artifacts\automation"
$script:ArtifactsDirResolved = $defaultArtifactsDir
$script:StatePathResolved = if ($StatePath) { $StatePath } else { Join-Path $defaultArtifactsDir "repo-watch-state.json" }
$script:LogPathResolved = if ($LogPath) { $LogPath } else { Join-Path $defaultArtifactsDir "repo-watch.log" }
$script:CodexOutputPathResolved = if ($CodexOutputPath) { $CodexOutputPath } else { Join-Path $defaultArtifactsDir "codex-last-message.txt" }
$script:MilestonesPath = Join-Path $script:RepoRootResolved "agent\MILESTONES.md"
$script:IssuesPath = Join-Path $script:RepoRootResolved "agent\ISSUES.md"
$script:CodexLockPath = Join-Path $defaultArtifactsDir "codex-exec.lock"
$script:WatcherLockPath = Join-Path $defaultArtifactsDir "repo-watch.lock"

Ensure-ParentDirectory -Path $script:StatePathResolved
Ensure-ParentDirectory -Path $script:LogPathResolved
Ensure-ParentDirectory -Path $script:CodexOutputPathResolved
Ensure-ParentDirectory -Path $script:WatcherLockPath

if (-not (Test-Path -LiteralPath $script:LogPathResolved)) {
  New-Item -ItemType File -Path $script:LogPathResolved | Out-Null
}

Push-Location $script:RepoRootResolved
try {
  Acquire-WatcherMutex
  if ($PollMinutes -lt 1) {
    throw "PollMinutes must be at least 1."
  }

  if ($DurationMinutes -lt 1) {
    throw "DurationMinutes must be at least 1."
  }

  $pollSeconds = $PollMinutes * 60

  Write-Log -Message "Repo watcher started. PollMinutes=$PollMinutes DurationMinutes=$DurationMinutes RepoRoot=$script:RepoRootResolved"
  $state = Get-State
  if (-not ($state.Keys -contains "lastBacklogHash")) {
    $state["lastBacklogHash"] = Get-BacklogHash
    Save-State -State $state
  }

  $startedAt = Get-Date
  while ($true) {
    try {
      Invoke-SafeSync

      $currentHead = (Invoke-Git -Arguments @("rev-parse", "HEAD")).Output
      $preRunStatus = Invoke-Git -Arguments @("status", "--porcelain")
      $preRunTreeWasClean = [string]::IsNullOrWhiteSpace($preRunStatus.Output)
      $preRunDirtyPaths = @(Get-DirtyGitPaths)
      $currentBacklogHash = Get-BacklogHash

      if ($state["lastBacklogHash"] -ne $currentBacklogHash) {
        Write-Log -Message "Detected change in active backlog files."

        if (Try-AcquireCodexLock -Metadata $currentBacklogHash) {
          try {
            if (Invoke-BacklogValidation) {
              Invoke-CodexForMilestone -State $state -BacklogHash $currentBacklogHash -PreRunTreeWasClean $preRunTreeWasClean -PreRunDirtyPaths $preRunDirtyPaths
              $state = Get-State
            } else {
              $state["lastBacklogHash"] = $currentBacklogHash
              $state["lastSyncedHead"] = $currentHead
              Save-State -State $state
            }
          } finally {
            Release-CodexLock
          }
        } else {
          Write-Log -Level "WARN" -Message "Codex lock is present. Skipping trigger this cycle."
        }
      } else {
        $state["lastSyncedHead"] = $currentHead
        Save-State -State $state
        Write-Log -Message "No active backlog changes detected."
      }
    } catch {
      Write-Log -Level "ERROR" -Message $_.Exception.Message
    }

    $elapsedMinutes = ((Get-Date) - $startedAt).TotalMinutes
    if ($elapsedMinutes -ge $DurationMinutes) {
      Write-Log -Message "Duration limit reached. Stopping watcher."
      break
    }

    Start-Sleep -Seconds $pollSeconds
  }
} finally {
  Release-WatcherMutex
  Pop-Location
}
