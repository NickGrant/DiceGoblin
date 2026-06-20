param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$HeadroomArgs
)

$candidates = @()

$command = Get-Command headroom.exe -ErrorAction SilentlyContinue
if ($command) {
    $candidates += $command.Source
}

$candidates += @(
    (Join-Path $env:APPDATA "Python\Python312\Scripts\headroom.exe"),
    (Join-Path $env:LOCALAPPDATA "Programs\Python\Python312\Scripts\headroom.exe")
)

$headroom = $candidates |
    Where-Object { $_ -and (Test-Path $_) } |
    Select-Object -First 1

if (-not $headroom) {
    Write-Error "Unable to locate headroom.exe. Install Headroom or add its Scripts directory to PATH."
    exit 1
}

& $headroom @HeadroomArgs
exit $LASTEXITCODE
