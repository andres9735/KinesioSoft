
param(
  [switch]$Interactive = $false,
  [string]$Filter = "",
  # Parámetros DB (ajustá si usás otras credenciales)
  [string]$DbHost = "127.0.0.1",
  [string]$DbUser = "root",
  [string]$DbPass = "",
  [string]$DbName = "kinesiosoft",
  # Si tenés mysql fuera del PATH, podés pasar la ruta acá:
  [string]$MysqlPath = ""
)

# ---------------- Utilidades ----------------
function Resolve-ProjectRoot {
  if ($PSScriptRoot -and $PSScriptRoot -ne '') { return $PSScriptRoot }
  else { return (Get-Location).Path }
}

function Ensure-Dir([string]$path) {
  if (-not (Test-Path $path)) { New-Item -ItemType Directory -Path $path | Out-Null }
}

function Pick-BackupFile([string]$dir, [string]$pattern, [string]$filter) {
  $items = Get-ChildItem $dir -File | Where-Object { $_.Name -like $pattern }
  if ($filter) { $items = $items | Where-Object { $_.Name -match [regex]::Escape($filter) } }
  return $items | Sort-Object LastWriteTime -Descending | Select-Object -First 1
}

function Resolve-MySqlExe([string]$hint) {
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

# ------------- Inicio script ----------------
$here = Resolve-ProjectRoot
Set-Location -Path $here

$backupDir = Join-Path $here "backups"
if (-not (Test-Path $backupDir)) {
  Write-Host "No se encontró la carpeta de backups en: $backupDir"
  exit 1
}

if ($Interactive) {
  Write-Host "=== Backups en $backupDir ==="
  Get-ChildItem $backupDir -File | Sort-Object LastWriteTime -Descending | Select-Object Name, LastWriteTime
  if (-not $Filter) { $Filter = Read-Host "Ingresá parte del nombre para filtrar (ej: dddbed7 o 20250916_1437)" }
}

# 1) Seleccionar últimos (o filtrados) .env, uploads y DB
$envBackup      = Pick-BackupFile $backupDir ".env*"         $Filter
$uploadsBackup  = Pick-BackupFile $backupDir "uploads*.zip"   $Filter
$sqlBackup      = Pick-BackupFile $backupDir "*.sql*"         $Filter  # .sql o .sql.zip

if (-not $envBackup -and -not $uploadsBackup -and -not $sqlBackup) {
  Write-Host "No se encontraron archivos de backup que coincidan." ; exit 1
}

# 2) Restaurar .env
$envPath = Join-Path $here ".env"
if ($envBackup) {
  Copy-Item $envBackup.FullName $envPath -Force
  Write-Host "✅ Restaurado .env desde: $($envBackup.Name)"
} else { Write-Host "⚠️ No se encontró backup de .env (omitido)." }

# 3) Restaurar uploads
$uploadsPath = Join-Path $here "storage\app\public"
if ($uploadsBackup) {
  Ensure-Dir $uploadsPath
  Expand-Archive -Path $uploadsBackup.FullName -DestinationPath $uploadsPath -Force
  Write-Host "✅ Restaurados uploads desde: $($uploadsBackup.Name)"
} else { Write-Host "⚠️ No se encontró backup de uploads (omitido)." }

# 4) Restaurar base de datos (soporta .sql y .sql.zip)
if ($sqlBackup) {
  $mysqlExe = Resolve-MySqlExe $MysqlPath
  if (-not $mysqlExe) {
    Write-Host "⚠️ No se encontró 'mysql.exe'. Definí -MysqlPath o agregá MySQL al PATH. Omitiendo restauración de BD."
  } else {
    $sqlFileToImport = $null
    $tempDir = Join-Path $env:TEMP ("ksoft_restore_" + [guid]::NewGuid().ToString())
    Ensure-Dir $tempDir

    if ($sqlBackup.Extension -ieq ".zip") {
      # Extraer el .sql desde el zip
      Expand-Archive -Path $sqlBackup.FullName -DestinationPath $tempDir -Force
      $sqlFileToImport = Get-ChildItem $tempDir -Recurse -Filter *.sql | Select-Object -First 1
    } elseif ($sqlBackup.Extension -ieq ".sql") {
      $sqlFileToImport = $sqlBackup
    } else {
      # Podrías agregar soporte a .gz con 7zip si querés.
      Write-Host "⚠️ Formato no soportado para BD: $($sqlBackup.Name). Se esperan .sql o .sql.zip"
    }

    if ($sqlFileToImport) {
      Write-Host "🔁 Restaurando BD '$DbName' desde: $($sqlBackup.Name)"

      # Construir comando (usar cmd /c para soportar redirección '<')
      $pwdArg = ""
      if ($DbPass -ne "") { $pwdArg = "-p$DbPass" }  # sin espacio

      $cmd = "`"$mysqlExe`" -h $DbHost -u $DbUser $pwdArg $DbName < `"$($sqlFileToImport.FullName)`""
      cmd.exe /c $cmd

      if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ BD restaurada correctamente."
      } else {
        Write-Host "❌ Error restaurando la BD (código $LASTEXITCODE). Revisá credenciales o el archivo SQL."
      }
    }

    # Limpiar temporales
    if (Test-Path $tempDir) { Remove-Item $tempDir -Recurse -Force | Out-Null }
  }
} else {
  Write-Host "⚠️ No se encontró backup de BD (.sql/.sql.zip). Omitido."
}

Write-Host "=== Restauración de archivos completa ==="

# 5) Post-proceso Laravel
Write-Host "🔄 Ejecutando comandos de Laravel..."
php artisan storage:link     | Out-Null
php artisan optimize:clear   | Out-Null
Write-Host "✅ Laravel listo después de la restauración."



