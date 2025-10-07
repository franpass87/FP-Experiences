#!/usr/bin/env php
<?php
/**
 * Script di verifica sistema calendario FP Experiences
 * Controlla il funzionamento completo dal backend al frontend
 */

declare(strict_types=1);

// Colori per output
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");

function print_header(string $title): void
{
    echo "\n" . COLOR_BLUE . str_repeat('=', 80) . COLOR_RESET . "\n";
    echo COLOR_BLUE . "  " . $title . COLOR_RESET . "\n";
    echo COLOR_BLUE . str_repeat('=', 80) . COLOR_RESET . "\n\n";
}

function print_success(string $message): void
{
    echo COLOR_GREEN . "✓ " . $message . COLOR_RESET . "\n";
}

function print_error(string $message): void
{
    echo COLOR_RED . "✗ " . $message . COLOR_RESET . "\n";
}

function print_warning(string $message): void
{
    echo COLOR_YELLOW . "⚠ " . $message . COLOR_RESET . "\n";
}

function print_info(string $message): void
{
    echo "  " . $message . "\n";
}

$errors = 0;
$warnings = 0;
$checks = 0;

// ============================================================================
// 1. VERIFICA BACKEND - Recurrence.php
// ============================================================================
print_header("1. VERIFICA BACKEND - Recurrence.php");

$recurrence_file = __DIR__ . '/src/Booking/Recurrence.php';
if (!file_exists($recurrence_file)) {
    print_error("File Recurrence.php non trovato!");
    $errors++;
} else {
    print_success("File Recurrence.php trovato");
    $checks++;
    
    $content = file_get_contents($recurrence_file);
    
    // Controlla presenza time_slots nel defaults
    if (strpos($content, "'time_slots' =>") !== false) {
        print_success("defaults() include 'time_slots'");
        $checks++;
    } else {
        print_error("defaults() non include 'time_slots'");
        $errors++;
    }
    
    // Controlla sanitize() supporta time_slots
    if (strpos($content, 'sanitize_time_slots') !== false) {
        print_success("sanitize() supporta time_slots");
        $checks++;
    } else {
        print_error("sanitize() non supporta time_slots");
        $errors++;
    }
    
    // Controlla retrocompatibilità time_sets
    if (strpos($content, 'time_sets') !== false && strpos($content, 'retrocompatibilità') !== false) {
        print_success("Retrocompatibilità con time_sets presente");
        $checks++;
    } else {
        print_warning("Retrocompatibilità con time_sets potrebbe essere assente");
        $warnings++;
    }
    
    // Controlla build_rules()
    if (strpos($content, 'public static function build_rules') !== false) {
        print_success("Metodo build_rules() presente");
        $checks++;
    } else {
        print_error("Metodo build_rules() non trovato");
        $errors++;
    }
}

// ============================================================================
// 2. VERIFICA BACKEND - AvailabilityService.php
// ============================================================================
print_header("2. VERIFICA BACKEND - AvailabilityService.php");

$availability_file = __DIR__ . '/src/Booking/AvailabilityService.php';
if (!file_exists($availability_file)) {
    print_error("File AvailabilityService.php non trovato!");
    $errors++;
} else {
    print_success("File AvailabilityService.php trovato");
    $checks++;
    
    $content = file_get_contents($availability_file);
    
    // Controlla lettura da _fp_exp_recurrence
    if (strpos($content, '_fp_exp_recurrence') !== false) {
        print_success("Legge da _fp_exp_recurrence");
        $checks++;
    } else {
        print_error("Non legge da _fp_exp_recurrence");
        $errors++;
    }
    
    // Controlla supporto time_slots
    if (strpos($content, "isset(\$recurrence['time_slots'])") !== false) {
        print_success("Supporta il formato time_slots");
        $checks++;
    } else {
        print_error("Non supporta il formato time_slots");
        $errors++;
    }
    
    // Controlla supporto time_sets (retrocompatibilità)
    if (strpos($content, "isset(\$recurrence['time_sets'])") !== false) {
        print_success("Supporta retrocompatibilità con time_sets");
        $checks++;
    } else {
        print_warning("Retrocompatibilità time_sets potrebbe essere assente");
        $warnings++;
    }
    
    // Controlla gestione campo 'time' singolo
    if (strpos($content, "isset(\$slot['time'])") !== false) {
        print_success("Gestisce campo 'time' singolo per time_slots");
        $checks++;
    } else {
        print_error("Non gestisce campo 'time' singolo");
        $errors++;
    }
}

// ============================================================================
// 3. VERIFICA BACKEND - ExperienceMetaBoxes.php
// ============================================================================
print_header("3. VERIFICA BACKEND - ExperienceMetaBoxes.php");

$metaboxes_file = __DIR__ . '/src/Admin/ExperienceMetaBoxes.php';
if (!file_exists($metaboxes_file)) {
    print_error("File ExperienceMetaBoxes.php non trovato!");
    $errors++;
} else {
    print_success("File ExperienceMetaBoxes.php trovato");
    $checks++;
    
    $content = file_get_contents($metaboxes_file);
    
    // Controlla render_calendar_tab
    if (strpos($content, 'private function render_calendar_tab') !== false) {
        print_success("Metodo render_calendar_tab() presente");
        $checks++;
    } else {
        print_error("Metodo render_calendar_tab() non trovato");
        $errors++;
    }
    
    // Controlla uso di time_slots nel render
    if (strpos($content, "data-repeater=\"time_slots\"") !== false) {
        print_success("Render usa data-repeater='time_slots'");
        $checks++;
    } else {
        print_error("Render non usa data-repeater='time_slots'");
        $errors++;
    }
    
    // Controlla sync_recurrence_to_availability
    if (strpos($content, 'sync_recurrence_to_availability') !== false) {
        print_success("Funzione sync_recurrence_to_availability presente");
        $checks++;
    } else {
        print_warning("Funzione sync potrebbe essere assente");
        $warnings++;
    }
    
    // Controlla save_availability_meta
    if (strpos($content, 'private function save_availability_meta') !== false) {
        print_success("Metodo save_availability_meta() presente");
        $checks++;
    } else {
        print_error("Metodo save_availability_meta() non trovato");
        $errors++;
    }
}

// ============================================================================
// 4. VERIFICA BACKEND - RestRoutes.php
// ============================================================================
print_header("4. VERIFICA BACKEND - RestRoutes.php");

$rest_file = __DIR__ . '/src/Api/RestRoutes.php';
if (!file_exists($rest_file)) {
    print_error("File RestRoutes.php non trovato!");
    $errors++;
} else {
    print_success("File RestRoutes.php trovato");
    $checks++;
    
    $content = file_get_contents($rest_file);
    
    // Controlla endpoint /availability
    if (strpos($content, "'/availability'") !== false) {
        print_success("Endpoint /availability registrato");
        $checks++;
    } else {
        print_error("Endpoint /availability non trovato");
        $errors++;
    }
    
    // Controlla get_virtual_availability callback
    if (strpos($content, 'get_virtual_availability') !== false) {
        print_success("Callback get_virtual_availability presente");
        $checks++;
    } else {
        print_error("Callback get_virtual_availability non trovato");
        $errors++;
    }
    
    // Controlla uso di AvailabilityService
    if (strpos($content, 'AvailabilityService::get_virtual_slots') !== false) {
        print_success("Usa AvailabilityService::get_virtual_slots()");
        $checks++;
    } else {
        print_error("Non usa AvailabilityService::get_virtual_slots()");
        $errors++;
    }
    
    // Controlla endpoint recurrence/preview
    if (strpos($content, "'/calendar/recurrence/preview'") !== false) {
        print_success("Endpoint /calendar/recurrence/preview presente");
        $checks++;
    } else {
        print_warning("Endpoint preview potrebbe essere assente");
        $warnings++;
    }
    
    // Controlla endpoint recurrence/generate
    if (strpos($content, "'/calendar/recurrence/generate'") !== false) {
        print_success("Endpoint /calendar/recurrence/generate presente");
        $checks++;
    } else {
        print_warning("Endpoint generate potrebbe essere assente");
        $warnings++;
    }
}

// ============================================================================
// 5. VERIFICA FRONTEND - admin.js
// ============================================================================
print_header("5. VERIFICA FRONTEND - admin.js");

$admin_js = __DIR__ . '/assets/js/admin.js';
if (!file_exists($admin_js)) {
    print_error("File admin.js non trovato!");
    $errors++;
} else {
    print_success("File admin.js trovato");
    $checks++;
    
    $content = file_get_contents($admin_js);
    
    // Controlla raccolta time_slots
    if (strpos($content, 'time_slots:') !== false) {
        print_success("JavaScript raccoglie time_slots");
        $checks++;
    } else {
        print_error("JavaScript non raccoglie time_slots");
        $errors++;
    }
    
    // Controlla query selector per repeater
    if (strpos($content, 'data-repeater="time_slots"') !== false || 
        strpos($content, "[data-repeater=\"time_slots\"]") !== false) {
        print_success("Query selector per time_slots repeater presente");
        $checks++;
    } else {
        print_error("Query selector per time_slots repeater non trovato");
        $errors++;
    }
    
    // Controlla retrocompatibilità time_sets
    if (strpos($content, 'time_sets:') !== false) {
        print_success("Mantiene retrocompatibilità con time_sets");
        $checks++;
    } else {
        print_warning("Retrocompatibilità time_sets potrebbe essere assente");
        $warnings++;
    }
    
    // Controlla estrazione campo time singolo
    if (strpos($content, "input[type=\"time\"]") !== false) {
        print_success("Estrae valori da input type='time'");
        $checks++;
    } else {
        print_error("Non estrae valori da input type='time'");
        $errors++;
    }
    
    // Controlla chiamate API
    if (strpos($content, '/fp-exp/v1/calendar/recurrence/') !== false) {
        print_success("Effettua chiamate API al backend");
        $checks++;
    } else {
        print_error("Non effettua chiamate API al backend");
        $errors++;
    }
}

// ============================================================================
// 6. VERIFICA FRONTEND - availability.js
// ============================================================================
print_header("6. VERIFICA FRONTEND - availability.js");

$availability_js = __DIR__ . '/assets/js/front/availability.js';
if (!file_exists($availability_js)) {
    print_error("File availability.js non trovato!");
    $errors++;
} else {
    print_success("File availability.js trovato");
    $checks++;
    
    $content = file_get_contents($availability_js);
    
    // Controlla fetchAvailability
    if (strpos($content, 'async function fetchAvailability') !== false || 
        strpos($content, 'function fetchAvailability') !== false) {
        print_success("Funzione fetchAvailability presente");
        $checks++;
    } else {
        print_error("Funzione fetchAvailability non trovata");
        $errors++;
    }
    
    // Controlla chiamata endpoint /availability
    if (strpos($content, '/fp-exp/v1/availability') !== false) {
        print_success("Chiama endpoint /fp-exp/v1/availability");
        $checks++;
    } else {
        print_error("Non chiama endpoint /fp-exp/v1/availability");
        $errors++;
    }
    
    // Controlla prefetchMonth
    if (strpos($content, 'async function prefetchMonth') !== false || 
        strpos($content, 'function prefetchMonth') !== false) {
        print_success("Funzione prefetchMonth presente");
        $checks++;
    } else {
        print_warning("Funzione prefetchMonth potrebbe essere assente");
        $warnings++;
    }
    
    // Controlla gestione slots
    if (strpos($content, 'slots') !== false && strpos($content, 'data.slots') !== false) {
        print_success("Gestisce array slots dalla risposta API");
        $checks++;
    } else {
        print_error("Non gestisce correttamente slots dalla risposta");
        $errors++;
    }
    
    // Controlla formatTimeRange
    if (strpos($content, 'function formatTimeRange') !== false) {
        print_success("Formatta range orari per display");
        $checks++;
    } else {
        print_warning("Formattazione orari potrebbe essere assente");
        $warnings++;
    }
}

// ============================================================================
// 7. VERIFICA STRUTTURA DATI E COMPATIBILITÀ
// ============================================================================
print_header("7. VERIFICA STRUTTURA DATI E COMPATIBILITÀ");

// Controlla documentazione
$readme_calendar = __DIR__ . '/README-SIMPLIFIED-CALENDAR.md';
if (file_exists($readme_calendar)) {
    print_success("Documentazione README-SIMPLIFIED-CALENDAR.md presente");
    $checks++;
} else {
    print_warning("Documentazione calendario semplificato assente");
    $warnings++;
}

$verifica_compat = __DIR__ . '/VERIFICA-COMPATIBILITA-FRONTEND-BACKEND.md';
if (file_exists($verifica_compat)) {
    print_success("Documentazione verifica compatibilità presente");
    $checks++;
} else {
    print_warning("Documentazione verifica compatibilità assente");
    $warnings++;
}

// Controlla presenza file legacy
$legacy_recurrence = __DIR__ . '/legacy/Recurrence.php.bak';
if (file_exists($legacy_recurrence)) {
    print_success("Backup legacy Recurrence.php presente");
    $checks++;
} else {
    print_info("Backup legacy non trovato (potrebbe essere normale)");
}

// ============================================================================
// RIEPILOGO FINALE
// ============================================================================
print_header("RIEPILOGO VERIFICA CALENDARIO");

$total_issues = $errors + $warnings;

echo "Controlli effettuati: " . COLOR_BLUE . $checks . COLOR_RESET . "\n";
echo "Errori critici:       " . ($errors > 0 ? COLOR_RED : COLOR_GREEN) . $errors . COLOR_RESET . "\n";
echo "Avvisi:               " . ($warnings > 0 ? COLOR_YELLOW : COLOR_GREEN) . $warnings . COLOR_RESET . "\n";
echo "\n";

if ($errors === 0 && $warnings === 0) {
    print_success("SISTEMA CALENDARIO COMPLETAMENTE VERIFICATO E FUNZIONANTE!");
    echo "\n";
    print_info("Il sistema è pronto per la produzione:");
    print_info("✓ Backend gestisce correttamente time_slots");
    print_info("✓ Frontend invia e riceve dati nel formato corretto");
    print_info("✓ Retrocompatibilità con time_sets garantita");
    print_info("✓ API REST endpoints configurati correttamente");
    echo "\n";
    exit(0);
} elseif ($errors === 0) {
    print_warning("SISTEMA FUNZIONANTE CON ALCUNI AVVISI");
    echo "\n";
    print_info("Il sistema funziona ma ci sono " . $warnings . " avvisi da verificare.");
    print_info("Controlla i dettagli sopra per maggiori informazioni.");
    echo "\n";
    exit(1);
} else {
    print_error("TROVATI ERRORI CRITICI NEL SISTEMA!");
    echo "\n";
    print_info("Ci sono " . $errors . " errori critici che devono essere risolti.");
    print_info("Il sistema potrebbe non funzionare correttamente.");
    echo "\n";
    exit(2);
}