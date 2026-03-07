$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot

$outputDir = Join-Path $repoRoot "artifacts\bundles"
New-Item -ItemType Directory -Path $outputDir -Force | Out-Null

function New-ZipBundle {
  param(
    [Parameter(Mandatory = $true)][string]$SourceDir,
    [Parameter(Mandatory = $true)][string]$ZipName
  )

  if (-not (Test-Path $SourceDir)) {
    throw "Source directory not found: $SourceDir"
  }

  $zipPath = Join-Path $outputDir $ZipName
  if (Test-Path $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
  }

  $files = Get-ChildItem -Path $SourceDir -Recurse -File | Where-Object {
    $_.FullName -notmatch '[\\/]\.git([\\/]|$)' -and
    $_.FullName -notmatch '[\\/]node_modules([\\/]|$)'
  } | ForEach-Object {
    Resolve-Path -LiteralPath $_.FullName -Relative
  }

  if (-not $files -or $files.Count -eq 0) {
    throw "No files found to archive for source: $SourceDir"
  }

  Compress-Archive -Path $files -DestinationPath $zipPath -Force
  Write-Output "Created $zipPath"
}

New-ZipBundle -SourceDir "documentation" -ZipName "documentation.zip"
New-ZipBundle -SourceDir "frontend" -ZipName "frontend.zip"
New-ZipBundle -SourceDir "backend" -ZipName "backend.zip"

