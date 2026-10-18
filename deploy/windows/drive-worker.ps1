# INGCO Meeting AI - Google Drive worker supervisor for local Windows/XAMPP.
# Runs only the Drive queue. Does not run OpenAI/Word/email processing.
$ErrorActionPreference = 'Stop'
$project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$artisan = Join-Path $project 'artisan'
if (-not (Test-Path -LiteralPath $artisan -PathType Leaf)) { throw "Laravel artisan tidak ditemukan: $artisan" }

$php = 'C:\xampp82\php\php.exe'
if (-not (Test-Path -LiteralPath $php -PathType Leaf)) {
    $command = Get-Command php -ErrorAction Stop
    $php = $command.Source
}

$logFolder = Join-Path $project 'storage\logs'
if (-not (Test-Path -LiteralPath $logFolder)) { New-Item -Path $logFolder -ItemType Directory -Force | Out-Null }
$logPath = Join-Path $logFolder 'drive-worker.log'
Set-Location -LiteralPath $project

while ($true) {
    try {
        Add-Content -LiteralPath $logPath -Value ("[{0}] Mulai/ulang worker Drive" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')) -Encoding UTF8
        & $php artisan queue:work database --queue=drive --sleep=1 --tries=5 --timeout=300 --max-time=3600 -v 2>&1 |
            Out-File -LiteralPath $logPath -Append -Encoding UTF8
        Add-Content -LiteralPath $logPath -Value ("[{0}] Worker berhenti (exit={1}); mencoba lagi dalam 10 detik" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $LASTEXITCODE) -Encoding UTF8
    }
    catch {
        Add-Content -LiteralPath $logPath -Value ("[{0}] Worker error: {1}; mencoba lagi dalam 10 detik" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $_.Exception.Message) -Encoding UTF8
    }
    Start-Sleep -Seconds 10
}
