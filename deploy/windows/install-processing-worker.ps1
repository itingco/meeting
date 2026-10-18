# Run as Windows PowerShell Administrator once. Registers processing worker for current login.
$ErrorActionPreference = 'Stop'
$project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$worker = Join-Path $PSScriptRoot 'processing-worker.ps1'
if (-not (Test-Path (Join-Path $project 'artisan'))) { throw "artisan tidak ditemukan di $project" }
if (-not (Test-Path $worker)) { throw "processing-worker.ps1 tidak ditemukan" }
$taskName = 'INGCO Meeting AI - Processing Worker'
$user = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$powershellExe = Join-Path $PSHOME 'powershell.exe'
$argsText = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $worker + '"'
$action = New-ScheduledTaskAction -Execute $powershellExe -Argument $argsText -WorkingDirectory $project
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $user
$principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -StartWhenAvailable
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force | Out-Null
# Start-ScheduledTask -TaskName $taskName
Start-Sleep -Seconds 2
$task = Get-ScheduledTask -TaskName $taskName
Write-Host 'TASK NAME  : ' $task.TaskName
Write-Host 'STATUS     : ' $task.State
Write-Host 'PROJECT    : ' $project
Write-Host 'LOG FILE   : ' (Join-Path $project 'storage\logs\processing-worker.log')
Write-Host 'CATATAN    : task berjalan saat user Windows ini LOGIN, bukan sebagai Windows service.'
