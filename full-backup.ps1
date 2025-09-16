
param(
  # Qué incluir (ambos true por defecto)
  [switch]$IncludeDB = $true,
  [switch]$IncludeFiles = $true,

  # BD (ajustá si hace falta)
  [string]$DbHost = "127.0.0.1",
  [string]$DbUser = "root",
  [string]$DbPass = "",
  [string]$DbName = "kinesiosoft",
  [string]$MysqldumpPath = "",

  # Comprimir el .sql a .zip
  [switch]$Zip = $true,

  # Etiqueta opcional (si querés forzar un sufijo en vez de fecha+hash)
  [string]$Tag = ""
)

# ---------- Utilidades ----------
function Here {
  if ($PSScriptRoot -and $PSScriptRoot -ne '') { $PSScriptRoot } else { (Get-Location).Path }
}
function Ensure-Dir([string]$path) { if (-not (Test-Path $path)) { New-Item -ItemType Directory -Path $path | Out-Null } }
function Resolve-MysqldumpExe([string]$hint) {
  if ($hint -and (Test-Path $hint)) { return $hint }
  $cmd = Get-Command mysqldump -ErrorAction SilentlyContinue
  if ($cmd) { return $cmd.Source }
  $candidates = @(
    "C:\xampp\mysql\bin\mysqldump.exe",
    "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe",
    "C:\Program Files\MySQL\MySQL Server 5.7\bin\mysqldump.exe"
  )
  foreach ($c in $candidates) { if (Test-Path $c) { return $c } }
  return $null
}

# ---------- Inicio ----------
$root = Here
Set-Location -Path $root
$backups = Join-Path $root "backups"
Ensure-Dir $backups

# Sello único compartido (fecha + hash) o Tag custom
if ([string]::IsNullOrWhiteSpace($Tag)) {
  $DATE = Get-Date -Format "yyyyMMdd_HHmm"
  try { $HASH = (git rev-parse --short HEAD) } catch { $HASH = "nohash" }
  $STAMP = "${DATE}_${HASH}"
} else {
  $STAMP = $Tag
}

Write-Host "📦 Carpeta de backups: $backups"
Write-Host "🏷️  Sello de backup:   $STAMP"
Write-Host ""

# ---------- Backup de .env + uploads ----------
if ($IncludeFiles) {
  # .env
  $envSrc = Join-Path $root ".env"
  if (Test-Path $envSrc) {
    $envDst = Join-Path $backups (".env_{0}" -f $STAMP)
    Copy-Item $envSrc $envDst -Force
    Write-Host "✅ .env guardado en: $envDst"
  } else {
    Write-Host "⚠️  No se encontró .env (omitido)."
  }

  # uploads
  $uploadsSrc = Join-Path $root "storage\app\public"
  if (Test-Path $uploadsSrc) {
    $uploadsZip = Join-Path $backups ("uploads_{0}.zip" -f $STAMP)
    Compress-Archive -Path (Join-Path $uploadsSrc "*") -DestinationPath $uploadsZip -Force
    Write-Host "✅ Uploads comprimidos en: $uploadsZip"
  } else {
    Write-Host "⚠️  No se encontró storage\app\public (omitido)."
  }
  Write-Host ""
}

# ---------- Backup de la BD ----------
if ($IncludeDB) {
  $mysqldump = Resolve-MysqldumpExe $MysqldumpPath
  if (-not $mysqldump) {
    Write-Host "❌ No se encontró 'mysqldump'. Indicá -MysqldumpPath o agregalo al PATH."
  } else {
    $sqlName = "{0}_{1}.sql" -f $DbName, $STAMP
    $sqlPath = Join-Path $backups $sqlName

    $pwdArg = ""
    if ($DbPass -ne "") { $pwdArg = "-p$DbPass" } # sin espacio

    $args = @(
      "-h", $DbHost,
      "-u", $DbUser,
      $pwdArg,
      "--databases", $DbName,
      "--result-file=$sqlPath",
      "--single-transaction",
      "--skip-lock-tables",
      "--triggers",
      "--routines",
      "--events",
      "--add-drop-table",
      "--default-character-set=utf8mb4",
      "--set-gtid-purged=OFF"
    )

    Write-Host "🔄 Generando dump de BD '$DbName'..."
    & "$mysqldump" @args
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $sqlPath)) {
      Write-Host "❌ Falló mysqldump (código $LASTEXITCODE). Verificá credenciales o permisos."
    } else {
      Write-Host "✅ Dump creado: $sqlPath"
      if ($Zip) {
        $zipPath = [IO.Path]::ChangeExtension($sqlPath, ".zip")
        Compress-Archive -Path $sqlPath -DestinationPath $zipPath -Force
        if (Test-Path $zipPath) {
          Remove-Item $sqlPath -Force
          Write-Host "🗜️  Comprimido: $zipPath"
        } else {
          Write-Host "⚠️  No se pudo comprimir; se deja el .sql."
        }
      }
    }
  }
}

Write-Host ""
Write-Host "🎉 Full backup finalizado."
Write-Host "📁 Archivos en $backups:"
Get-ChildItem $backups | Sort-Object LastWriteTime -Descending | Select-Object LastWriteTime, Name | Format-Table -AutoSize
