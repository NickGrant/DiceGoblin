$HeadroomArgs = $args

$repositoryRoot = Split-Path -Parent $PSScriptRoot
$headroomRoot = Join-Path $repositoryRoot ".headroom"

if (-not $env:HEADROOM_CONFIG_DIR) {
    $env:HEADROOM_CONFIG_DIR = Join-Path $headroomRoot "config"
}
if (-not $env:HEADROOM_WORKSPACE_DIR) {
    $env:HEADROOM_WORKSPACE_DIR = Join-Path $headroomRoot "runtime"
}
if (-not $env:HEADROOM_TELEMETRY) {
    $env:HEADROOM_TELEMETRY = "off"
}
if (-not $env:HEADROOM_BEACON) {
    $env:HEADROOM_BEACON = "off"
}

$candidates = @()

$command = Get-Command headroom.exe -ErrorAction SilentlyContinue
if ($command) {
    $candidates += $command.Source
}

if ($env:APPDATA) {
    $candidates += Get-ChildItem (Join-Path $env:APPDATA "Python\Python*\Scripts\headroom.exe") -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty FullName
}
if ($env:LOCALAPPDATA) {
    $candidates += Get-ChildItem (Join-Path $env:LOCALAPPDATA "Programs\Python\Python*\Scripts\headroom.exe") -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty FullName
}

$headroom = $candidates |
    Where-Object { $_ -and (Test-Path $_) } |
    Select-Object -First 1

if (-not $headroom) {
    Write-Error "Unable to locate headroom.exe. Install Headroom or add its Scripts directory to PATH."
    exit 1
}

Push-Location $repositoryRoot
try {
    & $headroom @HeadroomArgs
    exit $LASTEXITCODE
}
finally {
    Pop-Location
}
