$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot

$releaseApiBaseUrl = if ($env:DICE_GOBLIN_RELEASE_API_URL) {
  $env:DICE_GOBLIN_RELEASE_API_URL.TrimEnd('/')
} else {
  "https://dicegoblins-api.nickgrant.io"
}
$releaseFrontendUrl = if ($env:DICE_GOBLIN_RELEASE_FRONTEND_URL) {
  $env:DICE_GOBLIN_RELEASE_FRONTEND_URL.TrimEnd('/')
} else {
  "https://dicegoblins.nickgrant.io"
}

$outputDir = Join-Path $repoRoot "artifacts\bundles"
New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
$releaseDir = Join-Path $outputDir "release"
New-Item -ItemType Directory -Path $releaseDir -Force | Out-Null

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

function Add-FileToZipArchive {
  param(
    [Parameter(Mandatory = $true)]
    [System.IO.Compression.ZipArchive]$Archive,
    [Parameter(Mandatory = $true)]
    [string]$FilePath,
    [Parameter(Mandatory = $true)]
    [string]$EntryName
  )

  $normalizedEntryName = $EntryName -replace '\\', '/'
  $entry = $Archive.CreateEntry($normalizedEntryName, [System.IO.Compression.CompressionLevel]::Optimal)
  $entryStream = $entry.Open()
  $fileStream = $null

  try {
    $fileStream = [System.IO.File]::Open(
      $FilePath,
      [System.IO.FileMode]::Open,
      [System.IO.FileAccess]::Read,
      [System.IO.FileShare]::ReadWrite
    )
    $fileStream.CopyTo($entryStream)
  } finally {
    if ($fileStream) {
      $fileStream.Dispose()
    }
    $entryStream.Dispose()
  }
}

function Get-RelativeArchivePath {
  param(
    [Parameter(Mandatory = $true)][string]$RootPath,
    [Parameter(Mandatory = $true)][string]$FilePath
  )

  $rootUri = New-Object System.Uri(((Resolve-Path -LiteralPath $RootPath).Path.TrimEnd('\') + '\'))
  $fileUri = New-Object System.Uri((Resolve-Path -LiteralPath $FilePath).Path)
  $relativeUri = $rootUri.MakeRelativeUri($fileUri)
  return [System.Uri]::UnescapeDataString($relativeUri.ToString()) -replace '/', '\'
}

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

  $sourceRoot = (Resolve-Path -LiteralPath $SourceDir).Path
  $files = Get-ChildItem -Path $SourceDir -Recurse -File | Where-Object {
    $_.FullName -notmatch '[\\/]\.git([\\/]|$)' -and
    $_.FullName -notmatch '[\\/]node_modules([\\/]|$)'
  }

  if (-not $files -or $files.Count -eq 0) {
    throw "No files found to archive for source: $SourceDir"
  }

  $zipStream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::Create)
  $archive = New-Object System.IO.Compression.ZipArchive(
    $zipStream,
    [System.IO.Compression.ZipArchiveMode]::Create,
    $false
  )

  try {
    foreach ($file in $files) {
      $entryName = Get-RelativeArchivePath -RootPath $sourceRoot -FilePath $file.FullName
      Add-FileToZipArchive -Archive $archive -FilePath $file.FullName -EntryName $entryName
    }
  } finally {
    $archive.Dispose()
    $zipStream.Dispose()
  }

  Write-Output "Created $zipPath"
}

function Sync-DirectoryContents {
  param(
    [Parameter(Mandatory = $true)][string]$SourceDir,
    [Parameter(Mandatory = $true)][string]$DestinationDir
  )

  if (-not (Test-Path $SourceDir)) {
    throw "Source directory not found: $SourceDir"
  }

  if (Test-Path $DestinationDir) {
    Remove-Item -LiteralPath $DestinationDir -Recurse -Force
  }

  New-Item -ItemType Directory -Path $DestinationDir -Force | Out-Null
  Copy-Item -Path (Join-Path $SourceDir '*') -Destination $DestinationDir -Recurse -Force
  Write-Output "Synced $SourceDir -> $DestinationDir"
}

function Set-FileContentNoNewline {
  param(
    [Parameter(Mandatory = $true)][string]$Path,
    [Parameter(Mandatory = $true)][string]$Content
  )

  $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
  [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

function Write-ReleaseFrontendRuntimeConfig {
  param(
    [Parameter(Mandatory = $true)][string]$DestinationDir
  )

  $configPath = Join-Path $DestinationDir "runtime-config.js"
  $content = @"
window.__DICE_GOBLIN_CONFIG__ = {
  apiBaseUrl: '$releaseApiBaseUrl',
  enableDevPanel: false,
};
"@.Trim()
  Set-FileContentNoNewline -Path $configPath -Content $content
  Write-Output "Wrote release runtime config to $configPath"
}

function Write-ReleaseBackendEnvExample {
  param(
    [Parameter(Mandatory = $true)][string]$DestinationDir
  )

  $envExamplePath = Join-Path $DestinationDir ".env.example"
  $content = @"
# Release environment template
APP_ENV=prod
APP_URL=$releaseApiBaseUrl
FRONTEND_URL=$releaseFrontendUrl

# CORS
DEV_ALLOWED_ORIGINS=$releaseFrontendUrl
ENABLE_DEBUG_ENDPOINTS=0

# Session
SESSION_NAME=dice_goblins_session

# Database
DB_HOST=
DB_PORT=3306
DB_NAME=dice_goblins
DB_USER=
DB_PASS=

# Discord OAuth
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_REDIRECT_URI=$releaseApiBaseUrl/auth/discord/callback
"@.Trim()
  Set-FileContentNoNewline -Path $envExamplePath -Content $content
  Write-Output "Wrote release backend env template to $envExamplePath"
}

$releaseFrontendDir = Join-Path $releaseDir "frontend"
$releaseBackendDir = Join-Path $releaseDir "backend"

Sync-DirectoryContents -SourceDir "frontend\dist\browser" -DestinationDir $releaseFrontendDir
Sync-DirectoryContents -SourceDir "backend" -DestinationDir $releaseBackendDir

$releaseBackendLocalEnvPath = Join-Path $releaseBackendDir ".env"
if (Test-Path $releaseBackendLocalEnvPath) {
  Remove-Item -LiteralPath $releaseBackendLocalEnvPath -Force
}
$releaseBackendLocalTestEnvPath = Join-Path $releaseBackendDir ".env.test.local"
if (Test-Path $releaseBackendLocalTestEnvPath) {
  Remove-Item -LiteralPath $releaseBackendLocalTestEnvPath -Force
}
$releaseBackendPhpUnitCachePath = Join-Path $releaseBackendDir ".phpunit.result.cache"
if (Test-Path $releaseBackendPhpUnitCachePath) {
  Remove-Item -LiteralPath $releaseBackendPhpUnitCachePath -Force
}

Write-ReleaseFrontendRuntimeConfig -DestinationDir $releaseFrontendDir
Write-ReleaseBackendEnvExample -DestinationDir $releaseBackendDir

New-ZipBundle -SourceDir "documentation" -ZipName "documentation.zip"
New-ZipBundle -SourceDir $releaseFrontendDir -ZipName "frontend.zip"
New-ZipBundle -SourceDir $releaseBackendDir -ZipName "backend.zip"
