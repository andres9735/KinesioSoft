param(
    # Credenciales / conexión (ajustá si hace falta)
    [string]$DbHost = "127.0.0.1",
    [string]$DbUser = "root",
    [string]$DbPass = "4G63fdx9735",
    [string]$DbName = "kinesiosoft",

    # Si mysqldump no está en el PATH, podés pasar la ruta
    [string]$MysqldumpPath = "",

    # Comprimir a .zip el resultado y borrar el .sql original
    [switch]$Zip = $true
)

# ---------------- Utilidades ----------------
function Resolve-ProjectRoot {
    if ($PSScriptRoot -and $PSScriptRoot -ne '') { return $PSScriptRoot }
    else { return (Get-Location).Path }
}

function Ensure-Dir([string]$path) {
    if (-not (Test-Path $path)) { New-Item -ItemType Directory -Path $path | Out-Null }
}

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

# ---------------- Inicio ----------------
$here = Resolve-ProjectRoot
Set-Location -Path $here

$backupDir = Join-Path $here "backups"
Ensure-Dir $backupDir

# Marcas de tiempo y hash de git (si falla git, usa 'nohash')
$DATE = Get-Date -Format "yyyyMMdd_HHmm"
try { $HASH = (git rev-parse --short HEAD 2>$null) } catch { $HASH = "nohash" }

# Resolver mysqldump
$mysqldump = Resolve-MysqldumpExe $MysqldumpPath
if (-not $mysqldump) {
    Write-Host "❌ No se encontró 'mysqldump'. Indicá -MysqldumpPath o agregalo al PATH." ; exit 1
}

# Rutas de salida
$sqlName = "{0}_{1}_{2}.sql" -f $DbName, $DATE, $HASH
$sqlPath = Join-Path $backupDir $sqlName

# --------- Armar argumentos compatibles según vendor ---------
# Detectar vendor/versión de mysqldump
$dumpVer = & "$mysqldump" --version 2>$null
$esMariaDB = $dumpVer -match "MariaDB"

# Password sin espacio
$pwdArg = ""
if ($DbPass -ne "") { $pwdArg = "-p$DbPass" }

# Base args (válidos para MySQL y MariaDB)
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
    "--no-tablespaces"
)

# Solo para MySQL (no MariaDB) agregar flags de MySQL 8
if (-not $esMariaDB) {
    $args += @(
        "--column-statistics=0",
        "--set-gtid-purged=OFF"
    )
}

Write-Host "🔄 Generando dump de '$DbName'..."
# Ejecutar mysqldump
& "$mysqldump" @args

if ($LASTEXITCODE -ne 0 -or -not (Test-Path $sqlPath)) {
    Write-Host "❌ Falló mysqldump (código $LASTEXITCODE). Verificá credenciales o permisos."
    exit 1
}

Write-Host "✅ Dump creado: $sqlPath"

# Comprimir a ZIP si corresponde
if ($Zip) {
    $zipPath = [IO.Path]::ChangeExtension($sqlPath, ".zip")
    Compress-Archive -Path $sqlPath -DestinationPath $zipPath -Force
    if (Test-Path $zipPath) {
        Remove-Item $sqlPath -Force
        Write-Host "🗜️ Comprimido: $zipPath"
    } else {
        Write-Host "⚠️ No se pudo comprimir; se deja el .sql."
    }
}

Write-Host "🎉 Backup de BD finalizado."

