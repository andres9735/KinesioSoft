# backup.ps1 - Script de backup para KinesioSoft

# Detectar carpeta del proyecto (soporta ejecución como script o interactiva)
$here = if ($PSScriptRoot -and $PSScriptRoot -ne '') { $PSScriptRoot } else { (Get-Location).Path }
Set-Location -Path $here

# Crear carpeta backups
$backupDir = Join-Path $here "backups"
if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir | Out-Null
}

# Fecha y hash
$DATE = Get-Date -Format "yyyyMMdd_HHmm"
$HASH = (git rev-parse --short HEAD)

# Backup .env
$envFile = Join-Path $here ".env"
if (Test-Path $envFile) {
    $destEnv = Join-Path $backupDir (".env_{0}_{1}" -f $DATE, $HASH)
    Copy-Item $envFile $destEnv -Force
    Write-Host "✅ Backup de .env guardado en $destEnv"
} else {
    Write-Host "⚠️ No se encontró el archivo .env"
}

# Backup uploads (storage/app/public)
$uploadsPath = Join-Path $here "storage\app\public"
if (Test-Path $uploadsPath) {
    $destZip = Join-Path $backupDir ("uploads_{0}_{1}.zip" -f $DATE, $HASH)
    Compress-Archive -Path (Join-Path $uploadsPath "*") -DestinationPath $destZip -Force
    Write-Host "✅ Backup de uploads guardado en $destZip"
} else {
    Write-Host "⚠️ No se encontró la carpeta $uploadsPath"
}
