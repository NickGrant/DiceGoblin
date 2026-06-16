$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot

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

Sync-DirectoryContents -SourceDir "frontend\dist\browser" -DestinationDir (Join-Path $releaseDir "frontend")
Sync-DirectoryContents -SourceDir "backend" -DestinationDir (Join-Path $releaseDir "backend")

New-ZipBundle -SourceDir "documentation" -ZipName "documentation.zip"
New-ZipBundle -SourceDir (Join-Path $releaseDir "frontend") -ZipName "frontend.zip"
New-ZipBundle -SourceDir (Join-Path $releaseDir "backend") -ZipName "backend.zip"
