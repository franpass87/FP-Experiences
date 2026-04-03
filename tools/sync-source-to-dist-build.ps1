#requires -Version 5.1
<#
.SYNOPSIS
    Allinea cartelle dist/fp-experiences e build/fp-experiences con le sorgenti nella junction (root plugin).

.DESCRIPTION
    La sorgente "vera" è in assets/, templates/, languages/ nella root del repo.
    Se nel repo sono presenti snapshot dist/ o build/ (mirror o vecchi workflow), questo script
    copia i file che più spesso divergono dopo modifiche frontend/template/traduzioni.

    Lo ZIP di release (.github/scripts/build-zip.sh) rsynca dalla root ed esclude dist/build,
    quindi lo ZIP è sempre aggiornato; questo script serve a tenere allineate le cartelle
    dist/build se vengono committate o usate da tool legacy.

.NOTES
    Esecuzione dalla root del plugin:
      powershell -ExecutionPolicy Bypass -File tools/sync-source-to-dist-build.ps1
#>

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$slugDirs = @(
    (Join-Path $Root 'dist\fp-experiences'),
    (Join-Path $Root 'build\fp-experiences')
)

function Copy-IfTargetDirExists {
    param(
        [string]$RelativeSrc,
        [string]$RelativeDest
    )
    $src = Join-Path $Root ($RelativeSrc -replace '/', '\')
    if (-not (Test-Path -LiteralPath $src)) {
        Write-Warning "Sorgente assente, skip: $RelativeSrc"
        return
    }
    foreach ($base in $slugDirs) {
        if (-not (Test-Path -LiteralPath $base)) {
            Write-Warning "Cartella assente, skip: $base"
            continue
        }
        $dest = Join-Path $base ($RelativeDest -replace '/', '\')
        $destDir = Split-Path -Parent $dest
        if (-not (Test-Path -LiteralPath $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        Copy-Item -LiteralPath $src -Destination $dest -Force
        Write-Host "OK $RelativeSrc -> $($base | Split-Path -Leaf)\$RelativeDest"
    }
}

# CSS / JS sorgente principale
Copy-IfTargetDirExists 'assets/css/front.css' 'assets/css/front.css'
Copy-IfTargetDirExists 'assets/js/front.js' 'assets/js/front.js'
# Copia anche sotto assets/js/dist/ dove alcuni mirror duplicano il bundle
Copy-IfTargetDirExists 'assets/js/front.js' 'assets/js/dist/front.js'

Copy-IfTargetDirExists 'templates/front/experience.php' 'templates/front/experience.php'

# Bootstrap e localizzazione (spesso toccati insieme a copy/template)
Copy-IfTargetDirExists 'fp-experiences.php' 'fp-experiences.php'
Copy-IfTargetDirExists 'src/Localization/AutoTranslator.php' 'src/Localization/AutoTranslator.php'

# Traduzioni (.po) — allinea tutti i file nella cartella languages
$langSrc = Join-Path $Root 'languages'
if (Test-Path -LiteralPath $langSrc) {
    foreach ($base in $slugDirs) {
        if (-not (Test-Path -LiteralPath $base)) { continue }
        $langDest = Join-Path $base 'languages'
        if (-not (Test-Path -LiteralPath $langDest)) {
            New-Item -ItemType Directory -Path $langDest -Force | Out-Null
        }
        Get-ChildItem -Path $langSrc -Filter '*.po' -File | ForEach-Object {
            Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $langDest $_.Name) -Force
            Write-Host "OK languages\$($_.Name) -> $($base | Split-Path -Leaf)\languages\$($_.Name)"
        }
        if (Test-Path (Join-Path $langSrc 'fp-experiences.pot')) {
            Copy-Item -LiteralPath (Join-Path $langSrc 'fp-experiences.pot') -Destination (Join-Path $langDest 'fp-experiences.pot') -Force
            Write-Host "OK languages\fp-experiences.pot -> $($base | Split-Path -Leaf)\languages\"
        }
    }
}

Write-Host "`nCompletato. Rigenera i .mo con: wp i18n make-mo languages (o msgfmt) se usi solo file compilati."
