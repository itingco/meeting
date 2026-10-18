# INGCO Meeting AI - Register an automatic Google Drive worker for current Windows user.
# Intended for Windows PowerShell 5.1 / XAMPP local testing.
$ErrorActionPreference = 'Stop'
$project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$worker = Join-Path $PSScriptRoot 'drive-worker.ps1'
if (-not (Test-Path -LiteralPath (Join-Path $project 'artisan'))) {
    throw "artisan tidak ditemukan di $project. Ekstrak ZIP langsung ke folder project Laravel."
}
if (-not (Test-Path -LiteralPath $worker)) { throw "Worker script tidak ditemukan: $worker" }

$taskName = 'INGCO Meeting AI - Drive Worker'
$user = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$powershellExe = Join-Path $PSHOME 'powershell.exe'
if (-not (Test-Path -LiteralPath $powershellExe)) { $powershellExe = 'powershell.exe' }
$arguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $worker + '"'
$action = New-ScheduledTaskAction -Execute $powershellExe -Argument $arguments -WorkingDirectory $project
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $user
$principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -StartWhenAvailable
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force | Out-Null
Start-ScheduledTask -TaskName $taskName
Start-Sleep -Seconds 2
$task = Get-ScheduledTask -TaskName $taskName
Write-Host 'TASK NAME : ' $task.TaskName
Write-Host 'STATUS    : ' $task.State
Write-Host 'PROJECT   : ' $project
Write-Host 'LOG FILE  : ' (Join-Path $project 'storage\logs\drive-worker.log')
Write-Host 'CATATAN   : otomatis berjalan saat user Windows ini login; komputer harus menyala dan jaringan tersedia.'
