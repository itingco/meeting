# INGCO Meeting AI - Auto restart processing worker (AI/Word/email) at Windows logon.
# DO NOT store passwords/API keys here. Read Laravel .env via Artisan.
$ErrorActionPreference = 'Stop'
$project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$artisan = Join-Path $project 'artisan'
if (-not (Test-Path $artisan -PathType Leaf)) { throw "artisan tidak ditemukan: $artisan" }
$php = 'C:\xampp82\php\php.exe'
if (-not (Test-Path $php -PathType Leaf)) { $php = (Get-Command php -ErrorAction Stop).Source }
$logFolder = Join-Path $project 'storage\logs'
if (-not (Test-Path $logFolder)) { New-Item -ItemType Directory -Path $logFolder -Force | Out-Null }
$logPath = Join-Path $logFolder 'processing-worker.log'
Set-Location -LiteralPath $project
# Locate winget FFmpeg if it is installed but task's PATH has not refreshed yet.
if (-not (Get-Command ffmpeg -ErrorAction SilentlyContinue)) {
    $packages = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
    if (Test-Path $packages) {
        $found = Get-ChildItem $packages -Filter ffmpeg.exe -Recurse -File -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($found) { $env:Path = "$($found.DirectoryName);$env:Path" }
    }
}
while ($true) {
    try {
        Add-Content -LiteralPath $logPath -Value ("[{0}] START processing worker" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')) -Encoding UTF8
        & $php artisan queue:work database --queue=processing --sleep=1 --tries=1 --timeout=3400 --max-time=3600 -v 2>&1 |
            Out-File -LiteralPath $logPath -Append -Encoding UTF8
        Add-Content -LiteralPath $logPath -Value ("[{0}] Worker stopped; retry in 10s" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')) -Encoding UTF8
    } catch {
        Add-Content -LiteralPath $logPath -Value ("[{0}] Worker error: {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $_.Exception.Message) -Encoding UTF8
    }
    Start-Sleep -Seconds 10
}
