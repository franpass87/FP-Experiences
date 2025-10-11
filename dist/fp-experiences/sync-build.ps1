# Script PowerShell per sincronizzare le modifiche nel build senza bump versione
param(
    [switch]$Force
)

$ROOT_DIR = $PSScriptRoot
$BUILD_ROOT = Join-Path $ROOT_DIR "build"
$TARGET_DIR = Join-Path $BUILD_ROOT "fp-experiences"

# Verifica che la directory build esista
if (-not (Test-Path $TARGET_DIR)) {
    Write-Error "Build directory non trovata. Esegui prima: bash build.sh"
    exit 1
}

Write-Host "Sincronizzazione modifiche nel build..." -ForegroundColor Yellow

try {
    # Copia ricorsiva delle directory modificate
    Write-Host "  Copiando src/..." -ForegroundColor Cyan
    if (Test-Path "src") {
        Copy-Item -Path "src\*" -Destination "$TARGET_DIR\src\" -Recurse -Force
    }

    Write-Host "  Copiando assets/..." -ForegroundColor Cyan
    if (Test-Path "assets") {
        Copy-Item -Path "assets\*" -Destination "$TARGET_DIR\assets\" -Recurse -Force
    }

    Write-Host "  Copiando templates/..." -ForegroundColor Cyan
    if (Test-Path "templates") {
        Copy-Item -Path "templates\*" -Destination "$TARGET_DIR\templates\" -Recurse -Force
    }

    # Copia anche i file root che potrebbero cambiare
    Write-Host "  Copiando file root..." -ForegroundColor Cyan
    $rootFiles = @("fp-experiences.php", "readme.txt", "uninstall.php")
    foreach ($file in $rootFiles) {
        if (Test-Path $file) {
            Copy-Item -Path $file -Destination "$TARGET_DIR\" -Force
        }
    }

    # Rigenera l'autoloader per assicurare che le modifiche vengano caricate
    $vendorPath = Join-Path $TARGET_DIR "vendor"
    if (Test-Path $vendorPath) {
        Write-Host "  Rigenerando autoloader..." -ForegroundColor Cyan
        try {
            Push-Location $TARGET_DIR
            composer dump-autoload -o 2>$null
            Pop-Location
        } catch {
            Write-Warning "  Impossibile rigenerare autoloader (opzionale)"
        }
    }

    Write-Host "Build sincronizzato con successo!" -ForegroundColor Green
    Write-Host "Modifiche applicate in: $TARGET_DIR" -ForegroundColor Green

} catch {
    Write-Error "Errore durante la sincronizzazione: $_"
    exit 1
}
