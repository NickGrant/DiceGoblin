param(
  [int]$PollMinutes = 5,
  [int]$DurationMinutes = 60,
  [string]$RepoRoot = (Get-Location).Path,
  [string]$StatePath = "",
  [string]$LogPath = "",
  [string]$CodexOutputPath = "",
  [int]$CodexCooldownMinutes = 15,
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

  $output = & git @Arguments 2>&1
  $exitCode = $LASTEXITCODE
  if (-not $AllowFailure -and $exitCode -ne 0) {
    throw "git $($Arguments -join ' ') failed with exit code $exitCode`n$output"
  }

  [PSCustomObject]@{
    ExitCode = $exitCode
    Output = ($output | Out-String).Trim()
  }
}

function Invoke-Codex {
  param(
    [string]$Prompt
  )

  $arguments = @(
    "exec",
    "-C", $script:RepoRootResolved,
    "-a", "never",
    "-s", "danger-full-access",
    "--output-last-message", $script:CodexOutputPathResolved,
    $Prompt
  )

  $output = & codex @arguments 2>&1
  $exitCode = $LASTEXITCODE
  $text = ($output | Out-String).Trim()

  [PSCustomObject]@{
    ExitCode = $exitCode
    Output = $text
  }
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

  foreach ($block in Get-Blocks -Raw $raw) {
    if ($block -notmatch "(?m)^title:\s+") {
      continue
    }

    $issues += [PSCustomObject]@{
      Title = Get-FieldValue -Block $block -Name "title"
      Status = Get-FieldValue -Block $block -Name "status"
      Priority = Get-FieldValue -Block $block -Name "priority"
      Execution = Get-FieldValue -Block $block -Name "execution"
      Ready = Get-FieldValue -Block $block -Name "ready"
      Milestone = Get-FieldValue -Block $block -Name "milestone"
      Description = Get-FieldValue -Block $block -Name "description"
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

  foreach ($block in Get-Blocks -Raw $raw) {
    if ($block -notmatch "(?m)^name:\s+") {
      continue
    }

    $name = Get-FieldValue -Block $block -Name "name"
    if (-not $name -or $name.StartsWith("<")) {
      continue
    }

    $milestones += [PSCustomObject]@{
      Name = $name
      Status = Get-FieldValue -Block $block -Name "status"
      ExecutionWindow = Get-FieldValue -Block $block -Name "execution_window"
      IsCurrent = Get-FieldValue -Block $block -Name "is_current"
      Description = Get-FieldValue -Block $block -Name "description"
      Issues = @(Get-BulletListValue -Block $block -Name "issues")
    }
  }

  return $milestones
}

function Get-ActionableIssues {
  $milestones = Get-Milestones -Path $script:MilestonesPath
  $issues = Get-Issues -Path $script:IssuesPath

  $currentMilestone = $milestones |
    Where-Object { $_.IsCurrent -eq "yes" -and $_.ExecutionWindow -eq "open" -and $_.Status -ne "complete" } |
    Select-Object -First 1

  if (-not $currentMilestone) {
    return [PSCustomObject]@{
      Milestone = $null
      Issues = @()
    }
  }

  $statusRank = @{
    "reopened" = 0
    "in-progress" = 1
    "unstarted" = 2
    "blocked" = 3
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
      $_.Execution -eq "active" -and
      $_.Ready -eq "yes" -and
      $_.Status -in @("reopened", "in-progress", "unstarted") -and
      (
        $_.Milestone -eq $currentMilestone.Name -or
        ($_.Milestone -eq "unassigned") -or
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

function Get-State {
  if (-not (Test-Path -LiteralPath $script:StatePathResolved)) {
    return [ordered]@{
      lastMilestoneHash = ""
      lastCodexRunUtc = ""
      lastSyncedHead = ""
    }
  }

  try {
    return Get-Content -LiteralPath $script:StatePathResolved -Raw | ConvertFrom-Json -AsHashtable
  } catch {
    Write-Log -Level "WARN" -Message "State file was unreadable. Reinitializing state."
    return [ordered]@{
      lastMilestoneHash = ""
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
  $text = ($output | Out-String).Trim()

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
    [bool]$PreRunTreeWasClean
  )

  if (-not $PreRunTreeWasClean) {
    Write-Log -Level "WARN" -Message "Skipping auto-commit because the working tree was already dirty before Codex ran."
    return
  }

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

  Invoke-Git -Arguments @("add", "-A") | Out-Null

  $cachedDiff = Invoke-Git -Arguments @("diff", "--cached", "--name-only")
  if ([string]::IsNullOrWhiteSpace($cachedDiff.Output)) {
    Write-Log -Message "No staged diff was produced after git add -A."
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
    [string]$MilestoneHash,
    [bool]$PreRunTreeWasClean
  )

  $actionable = Get-ActionableIssues
  $milestone = $actionable.Milestone
  $issues = @($actionable.Issues)

  if (-not $milestone) {
    Write-Log -Message "Milestones changed, but there is no current open milestone to execute."
    $State["lastMilestoneHash"] = $MilestoneHash
    Save-State -State $State
    return
  }

  if ($issues.Count -eq 0) {
    Write-Log -Message "Milestones changed, but no active ready issues were found for milestone '$($milestone.Name)'."
    $State["lastMilestoneHash"] = $MilestoneHash
    Save-State -State $State
    return
  }

  if (Test-CodexCooldown -State $State) {
    Write-Log -Level "WARN" -Message "Milestones changed, but Codex cooldown is active. Skipping this cycle."
    $State["lastMilestoneHash"] = $MilestoneHash
    Save-State -State $State
    return
  }

  $issueSummary = $issues |
    Select-Object -First 5 |
    ForEach-Object { "- $($_.Title) [$($_.Status), $($_.Priority)]" }

  $prompt = @"
You are working in the Dice Goblins repository at $($script:RepoRootResolved).

Start by reading and following:
- AGENTS.md
- agent/LLM_CONTEXT.md
- agent/ISSUES.md
- agent/MILESTONES.md
- agent/ROLES.md

The watcher detected an updated active milestone.

Current milestone:
- $($milestone.Name)

Actionable issues already identified from the active backlog:
$($issueSummary -join [Environment]::NewLine)

Instructions:
1. Validate that the current milestone and related active issues are implementation-relevant to developing the Dice Goblins game.
2. If they are not implementation-relevant, stop without making changes and explain why.
3. If they are implementation-relevant, choose the highest-priority ready issue from the current open milestone, implement it, verify the change, and update only the minimum related docs required by repo policy.
4. Respect dirty-worktree safety. Do not revert user changes you did not make.
5. Follow the repo backlog policy: only execute active, ready work in the current open milestone unless the docs themselves clearly require a tightly related supporting change.
"@

  Write-Log -Message "Launching Codex for milestone '$($milestone.Name)' with $($issues.Count) actionable issue(s)."
  $result = Invoke-Codex -Prompt $prompt

  if ($result.ExitCode -ne 0) {
    Write-Log -Level "ERROR" -Message "Codex exec failed with exit code $($result.ExitCode). Details: $($result.Output -replace '\s+', ' ')"
  } else {
    Write-Log -Message "Codex exec completed successfully."
    Invoke-CommitAndPush -MilestoneName $milestone.Name -PreRunTreeWasClean $PreRunTreeWasClean
  }

  $State["lastCodexRunUtc"] = [datetime]::UtcNow.ToString("o")
  $State["lastMilestoneHash"] = Get-FileHashSafe -Path $script:MilestonesPath
  $State["lastSyncedHead"] = (Invoke-Git -Arguments @("rev-parse", "HEAD")).Output
  Save-State -State $State
}

$script:RepoRootResolved = (Resolve-Path -LiteralPath $RepoRoot).Path
$defaultArtifactsDir = Join-Path $script:RepoRootResolved "artifacts\automation"
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
  if (-not ($state.Keys -contains "lastMilestoneHash")) {
    $state["lastMilestoneHash"] = Get-FileHashSafe -Path $script:MilestonesPath
    Save-State -State $state
  }

  $startedAt = Get-Date
  while ($true) {
    try {
      Invoke-SafeSync

      $currentHead = (Invoke-Git -Arguments @("rev-parse", "HEAD")).Output
      $preRunStatus = Invoke-Git -Arguments @("status", "--porcelain")
      $preRunTreeWasClean = [string]::IsNullOrWhiteSpace($preRunStatus.Output)
      $currentMilestoneHash = Get-FileHashSafe -Path $script:MilestonesPath

      if ($state["lastMilestoneHash"] -ne $currentMilestoneHash) {
        Write-Log -Message "Detected change in agent/MILESTONES.md."

        if (-not (Test-Path -LiteralPath $script:CodexLockPath)) {
          Set-Content -LiteralPath $script:CodexLockPath -Value ([datetime]::UtcNow.ToString("o"))
          try {
            if (Invoke-BacklogValidation) {
              Invoke-CodexForMilestone -State $state -MilestoneHash $currentMilestoneHash -PreRunTreeWasClean $preRunTreeWasClean
            } else {
              $state["lastMilestoneHash"] = $currentMilestoneHash
              $state["lastSyncedHead"] = $currentHead
              Save-State -State $state
            }
          } finally {
            if (Test-Path -LiteralPath $script:CodexLockPath) {
              Remove-Item -LiteralPath $script:CodexLockPath -Force
            }
          }
        } else {
          Write-Log -Level "WARN" -Message "Codex lock is present. Skipping trigger this cycle."
        }
      } else {
        $state["lastSyncedHead"] = $currentHead
        Save-State -State $state
        Write-Log -Message "No milestone changes detected."
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
