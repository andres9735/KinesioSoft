param(
  [switch]$Interactive = $false,
  [string]$Filter = "",

  # Parámetros de MySQL (ajustá si hace falta)
  [string]$DbHost = "127.0.0.1",
  [string]$DbUser = "root",
  [string]$DbPass = "",
  [string]$DbName = "kinesiosoft",
  [string]$MysqlPath = ""   # ruta al binario mysql.exe si no está en PATH
)

# -------- utilidades --------
function Here {
  if ($PSScriptRoot -and $PSScriptRoot -ne '') { $PSScriptRoot } else { (Get-Location).Path }
}
function Ensure-Dir([string]$path) { if (-not (Test-Path $path)) { New-Item -ItemType Directory -Path $path | Out-Null } }
function Resolve-MysqlExe([string]$hint) {
  if ($hint -and (Test-Path $hint)) { return $hint }
  $cmd = Get-Command mysql -ErrorAction SilentlyContinue
  if ($cmd) { return $cmd.Source }
  $candidates = @(
    "C:\xampp\mysql\bin\mysql.exe",
    "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe",
    "C:\Program Files\MySQL\MySQL Server 5.7\bin\mysql.exe"
  )
  foreach ($c in $candidates) { if (Test-Path $c) { return $c } }
  return $null
}
function Pick-BackupFile([string]$pattern, [string]$filter) {
    $items = Get-ChildItem $backupDir -File | Where-Object { $_.Name -like $pattern }
    if ($filter) { $items = $items | Where-Object { $_.Name -match [regex]::Escape($filter) } }
    return $items | Sort-Object LastWriteTime -Descending | Select-Object -First 1
}

# -------- inicio --------
$here = Here
Set-Location -Path $here

$backupDir = Join-Path $here "backups"
if (-not (Test-Path $backupDir)) {
    Write-Host "No se encontró la carpeta de backups en: $backupDir"
    exit 1
}

if ($Interactive) {
    Write-Host "=== Backups disponibles en $backupDir ==="
    Get-ChildItem $backupDir -File | Sort-Object LastWriteTime -Descending | Select-Object Name, LastWriteTime
    if (-not $Filter) {
        $Filter = Read-Host "Ingresá parte del nombre del backup a restaurar (ej: hash o fecha 2025...)"
    }
}

# Seleccionar últimos backups
$envBackup      = Pick-BackupFile ".env*" $Filter
$uploadsBackup  = Pick-BackupFile "uploads*.zip" $Filter
# BD: permite .sql o .sql.zip
$dbBackupSql    = Pick-BackupFile ("{0}_*.sql" -f $DbName) $Filter
$dbBackupZip    = Pick-BackupFile ("{0}_*.sql.zip" -f $DbName) $Filter

if (-not $envBackup -and -not $uploadsBackup -and -not $dbBackupSql -and -not $dbBackupZip) {
    Write-Host "No se encontraron backups que coincidan."
    exit 1
}

# Restaurar .env
$envPath = Join-Path $here ".env"
if ($envBackup) {
    Copy-Item $envBackup.FullName $envPath -Force
    Write-Host ("Restaurado .env desde: {0}" -f $envBackup.Name)
} else {
    Write-Host "No se encontró backup de .env (omitido)."
}

# Restaurar uploads
$uploadsPath = Join-Path $here "storage\app\public"
if ($uploadsBackup) {
    Ensure-Dir $uploadsPath
    Expand-Archive -Path $uploadsBackup.FullName -DestinationPath $uploadsPath -Force
    Write-Host ("Restaurados uploads desde: {0}" -f $uploadsBackup.Name)
} else {
    Write-Host "No se encontró backup de uploads (omitido)."
}

# Restaurar base de datos
$mysqlExe = Resolve-MysqlExe $MysqlPath
if (-not $mysqlExe) {
    if ($dbBackupSql -or $dbBackupZip) {
        Write-Host "ADVERTENCIA: Hay backup de BD pero no se encontró mysql.exe. Indicá -MysqlPath."
    }
} else {
    $sqlToImport = $null
    $tempDir = Join-Path $backupDir "_tmp_restore_sql"
    if ($dbBackupZip) {
        Ensure-Dir $tempDir
        # descomprimir al temp
        Expand-Archive -Path $dbBackupZip.FullName -DestinationPath $tempDir -Force
        # tomar el primer .sql resultante
        $found = Get-ChildItem $tempDir -Filter "*.sql" -File | Sort-Object Length -Descending | Select-Object -First 1
        if ($found) { $sqlToImport = $found.FullName }
    } elseif ($dbBackupSql) {
        $sqlToImport = $dbBackupSql.FullName
    }

    if ($sqlToImport) {
        Write-Host ("Importando BD '{0}' desde: {1}" -f $DbName, (Split-Path $sqlToImport -Leaf))
        # crear DB si no existe
        $pwdArg = ""
        if ($DbPass -ne "") { $pwdArg = "-p$DbPass" }  # sin espacio
        & "$mysqlExe" -h $DbHost -u $DbUser $pwdArg -e ("CREATE DATABASE IF NOT EXISTS `{0}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" -f $DbName)
        if ($LASTEXITCODE -ne 0) { Write-Host "ERROR: No se pudo crear/verificar la base."; exit 1 }

        # importar
        & "$mysqlExe" -h $DbHost -u $DbUser $pwdArg $DbName < $sqlToImport
        if ($LASTEXITCODE -ne 0) {
            Write-Host "ERROR: Falló la importación de la base."
            if (Test-Path $tempDir) { Remove-Item $tempDir -Recurse -Force }
            exit 1
        } else {
            Write-Host "Base de datos restaurada correctamente."
        }
    } else {
        Write-Host "No se encontró dump de base de datos (omitido)."
    }

    if (Test-Path $tempDir) { Remove-Item $tempDir -Recurse -Force }
}

Write-Host "Restauración completa."





