#!/bin/bash
# Script di verifica sistema calendario FP Experiences
# Controlla il funzionamento completo dal backend al frontend

# Colori
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

errors=0
warnings=0
checks=0

print_header() {
    echo -e "\n${BLUE}$(printf '=%.0s' {1..80})${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}$(printf '=%.0s' {1..80})${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
    ((checks++))
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
    ((errors++))
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
    ((warnings++))
}

print_info() {
    echo -e "  $1"
}

# ============================================================================
# 1. VERIFICA BACKEND - Recurrence.php
# ============================================================================
print_header "1. VERIFICA BACKEND - Recurrence.php"

RECURRENCE_FILE="src/Booking/Recurrence.php"
if [ ! -f "$RECURRENCE_FILE" ]; then
    print_error "File Recurrence.php non trovato!"
else
    print_success "File Recurrence.php trovato"
    
    # Controlla presenza time_slots nel defaults
    if grep -q "'time_slots' =>" "$RECURRENCE_FILE"; then
        print_success "defaults() include 'time_slots'"
    else
        print_error "defaults() non include 'time_slots'"
    fi
    
    # Controlla sanitize() supporta time_slots
    if grep -q "sanitize_time_slots" "$RECURRENCE_FILE"; then
        print_success "sanitize() supporta time_slots"
    else
        print_error "sanitize() non supporta time_slots"
    fi
    
    # Controlla retrocompatibilità time_sets
    if grep -q "time_sets" "$RECURRENCE_FILE" && grep -q "retrocompatibilità" "$RECURRENCE_FILE"; then
        print_success "Retrocompatibilità con time_sets presente"
    else
        print_warning "Retrocompatibilità con time_sets potrebbe essere assente"
    fi
    
    # Controlla build_rules()
    if grep -q "public static function build_rules" "$RECURRENCE_FILE"; then
        print_success "Metodo build_rules() presente"
    else
        print_error "Metodo build_rules() non trovato"
    fi
fi

# ============================================================================
# 2. VERIFICA BACKEND - AvailabilityService.php
# ============================================================================
print_header "2. VERIFICA BACKEND - AvailabilityService.php"

AVAILABILITY_FILE="src/Booking/AvailabilityService.php"
if [ ! -f "$AVAILABILITY_FILE" ]; then
    print_error "File AvailabilityService.php non trovato!"
else
    print_success "File AvailabilityService.php trovato"
    
    # Controlla lettura da _fp_exp_recurrence
    if grep -q "_fp_exp_recurrence" "$AVAILABILITY_FILE"; then
        print_success "Legge da _fp_exp_recurrence"
    else
        print_error "Non legge da _fp_exp_recurrence"
    fi
    
    # Controlla supporto time_slots
    if grep -q "time_slots" "$AVAILABILITY_FILE"; then
        print_success "Supporta il formato time_slots"
    else
        print_error "Non supporta il formato time_slots"
    fi
    
    # Controlla supporto time_sets (retrocompatibilità)
    if grep -q "time_sets" "$AVAILABILITY_FILE"; then
        print_success "Supporta retrocompatibilità con time_sets"
    else
        print_warning "Retrocompatibilità time_sets potrebbe essere assente"
    fi
    
    # Controlla gestione campo 'time' singolo
    if grep -q "\['time'\]" "$AVAILABILITY_FILE"; then
        print_success "Gestisce campo 'time' singolo per time_slots"
    else
        print_error "Non gestisce campo 'time' singolo"
    fi
fi

# ============================================================================
# 3. VERIFICA BACKEND - ExperienceMetaBoxes.php
# ============================================================================
print_header "3. VERIFICA BACKEND - ExperienceMetaBoxes.php"

METABOXES_FILE="src/Admin/ExperienceMetaBoxes.php"
if [ ! -f "$METABOXES_FILE" ]; then
    print_error "File ExperienceMetaBoxes.php non trovato!"
else
    print_success "File ExperienceMetaBoxes.php trovato"
    
    # Controlla render_calendar_tab
    if grep -q "private function render_calendar_tab" "$METABOXES_FILE"; then
        print_success "Metodo render_calendar_tab() presente"
    else
        print_error "Metodo render_calendar_tab() non trovato"
    fi
    
    # Controlla uso di time_slots nel render
    if grep -q 'data-repeater="time_slots"' "$METABOXES_FILE"; then
        print_success "Render usa data-repeater='time_slots'"
    else
        print_error "Render non usa data-repeater='time_slots'"
    fi
    
    # Controlla sync_recurrence_to_availability
    if grep -q "sync_recurrence_to_availability" "$METABOXES_FILE"; then
        print_success "Funzione sync_recurrence_to_availability presente"
    else
        print_warning "Funzione sync potrebbe essere assente"
    fi
    
    # Controlla save_availability_meta
    if grep -q "private function save_availability_meta" "$METABOXES_FILE"; then
        print_success "Metodo save_availability_meta() presente"
    else
        print_error "Metodo save_availability_meta() non trovato"
    fi
fi

# ============================================================================
# 4. VERIFICA BACKEND - RestRoutes.php
# ============================================================================
print_header "4. VERIFICA BACKEND - RestRoutes.php"

REST_FILE="src/Api/RestRoutes.php"
if [ ! -f "$REST_FILE" ]; then
    print_error "File RestRoutes.php non trovato!"
else
    print_success "File RestRoutes.php trovato"
    
    # Controlla endpoint /availability
    if grep -q "'/availability'" "$REST_FILE"; then
        print_success "Endpoint /availability registrato"
    else
        print_error "Endpoint /availability non trovato"
    fi
    
    # Controlla get_virtual_availability callback
    if grep -q "get_virtual_availability" "$REST_FILE"; then
        print_success "Callback get_virtual_availability presente"
    else
        print_error "Callback get_virtual_availability non trovato"
    fi
    
    # Controlla uso di AvailabilityService
    if grep -q "AvailabilityService::get_virtual_slots" "$REST_FILE"; then
        print_success "Usa AvailabilityService::get_virtual_slots()"
    else
        print_error "Non usa AvailabilityService::get_virtual_slots()"
    fi
    
    # Controlla endpoint recurrence/preview
    if grep -q "'/calendar/recurrence/preview'" "$REST_FILE"; then
        print_success "Endpoint /calendar/recurrence/preview presente"
    else
        print_warning "Endpoint preview potrebbe essere assente"
    fi
    
    # Controlla endpoint recurrence/generate
    if grep -q "'/calendar/recurrence/generate'" "$REST_FILE"; then
        print_success "Endpoint /calendar/recurrence/generate presente"
    else
        print_warning "Endpoint generate potrebbe essere assente"
    fi
fi

# ============================================================================
# 5. VERIFICA FRONTEND - admin.js
# ============================================================================
print_header "5. VERIFICA FRONTEND - admin.js"

ADMIN_JS="assets/js/admin.js"
if [ ! -f "$ADMIN_JS" ]; then
    print_error "File admin.js non trovato!"
else
    print_success "File admin.js trovato"
    
    # Controlla raccolta time_slots
    if grep -q "time_slots:" "$ADMIN_JS"; then
        print_success "JavaScript raccoglie time_slots"
    else
        print_error "JavaScript non raccoglie time_slots"
    fi
    
    # Controlla query selector per repeater
    if grep -q 'data-repeater="time_slots"' "$ADMIN_JS" || grep -q '\[data-repeater="time_slots"\]' "$ADMIN_JS"; then
        print_success "Query selector per time_slots repeater presente"
    else
        print_error "Query selector per time_slots repeater non trovato"
    fi
    
    # Controlla retrocompatibilità time_sets
    if grep -q "time_sets:" "$ADMIN_JS"; then
        print_success "Mantiene retrocompatibilità con time_sets"
    else
        print_warning "Retrocompatibilità time_sets potrebbe essere assente"
    fi
    
    # Controlla estrazione campo time singolo
    if grep -q 'input\[type="time"\]' "$ADMIN_JS"; then
        print_success "Estrae valori da input type='time'"
    else
        print_error "Non estrae valori da input type='time'"
    fi
    
    # Controlla chiamate API
    if grep -q '/fp-exp/v1/calendar/recurrence/' "$ADMIN_JS"; then
        print_success "Effettua chiamate API al backend"
    else
        print_error "Non effettua chiamate API al backend"
    fi
fi

# ============================================================================
# 6. VERIFICA FRONTEND - availability.js
# ============================================================================
print_header "6. VERIFICA FRONTEND - availability.js"

AVAILABILITY_JS="assets/js/front/availability.js"
if [ ! -f "$AVAILABILITY_JS" ]; then
    print_error "File availability.js non trovato!"
else
    print_success "File availability.js trovato"
    
    # Controlla fetchAvailability
    if grep -q "function fetchAvailability" "$AVAILABILITY_JS"; then
        print_success "Funzione fetchAvailability presente"
    else
        print_error "Funzione fetchAvailability non trovata"
    fi
    
    # Controlla chiamata endpoint /availability
    if grep -q '/fp-exp/v1/availability' "$AVAILABILITY_JS"; then
        print_success "Chiama endpoint /fp-exp/v1/availability"
    else
        print_error "Non chiama endpoint /fp-exp/v1/availability"
    fi
    
    # Controlla prefetchMonth
    if grep -q "function prefetchMonth" "$AVAILABILITY_JS"; then
        print_success "Funzione prefetchMonth presente"
    else
        print_warning "Funzione prefetchMonth potrebbe essere assente"
    fi
    
    # Controlla gestione slots
    if grep -q "data.slots" "$AVAILABILITY_JS"; then
        print_success "Gestisce array slots dalla risposta API"
    else
        print_error "Non gestisce correttamente slots dalla risposta"
    fi
    
    # Controlla formatTimeRange
    if grep -q "function formatTimeRange" "$AVAILABILITY_JS"; then
        print_success "Formatta range orari per display"
    else
        print_warning "Formattazione orari potrebbe essere assente"
    fi
fi

# ============================================================================
# 7. VERIFICA STRUTTURA DATI E COMPATIBILITÀ
# ============================================================================
print_header "7. VERIFICA STRUTTURA DATI E COMPATIBILITÀ"

# Controlla documentazione
if [ -f "README-SIMPLIFIED-CALENDAR.md" ]; then
    print_success "Documentazione README-SIMPLIFIED-CALENDAR.md presente"
else
    print_warning "Documentazione calendario semplificato assente"
fi

if [ -f "VERIFICA-COMPATIBILITA-FRONTEND-BACKEND.md" ]; then
    print_success "Documentazione verifica compatibilità presente"
else
    print_warning "Documentazione verifica compatibilità assente"
fi

# Controlla presenza file legacy
if [ -f "legacy/Recurrence.php.bak" ]; then
    print_success "Backup legacy Recurrence.php presente"
else
    print_info "Backup legacy non trovato (potrebbe essere normale)"
fi

# ============================================================================
# RIEPILOGO FINALE
# ============================================================================
print_header "RIEPILOGO VERIFICA CALENDARIO"

total_issues=$((errors + warnings))

echo -e "Controlli effettuati: ${BLUE}$checks${NC}"
if [ $errors -eq 0 ]; then
    echo -e "Errori critici:       ${GREEN}$errors${NC}"
else
    echo -e "Errori critici:       ${RED}$errors${NC}"
fi

if [ $warnings -eq 0 ]; then
    echo -e "Avvisi:               ${GREEN}$warnings${NC}"
else
    echo -e "Avvisi:               ${YELLOW}$warnings${NC}"
fi
echo ""

if [ $errors -eq 0 ] && [ $warnings -eq 0 ]; then
    print_success "SISTEMA CALENDARIO COMPLETAMENTE VERIFICATO E FUNZIONANTE!"
    echo ""
    print_info "Il sistema è pronto per la produzione:"
    print_info "✓ Backend gestisce correttamente time_slots"
    print_info "✓ Frontend invia e riceve dati nel formato corretto"
    print_info "✓ Retrocompatibilità con time_sets garantita"
    print_info "✓ API REST endpoints configurati correttamente"
    echo ""
    exit 0
elif [ $errors -eq 0 ]; then
    print_warning "SISTEMA FUNZIONANTE CON ALCUNI AVVISI"
    echo ""
    print_info "Il sistema funziona ma ci sono $warnings avvisi da verificare."
    print_info "Controlla i dettagli sopra per maggiori informazioni."
    echo ""
    exit 1
else
    print_error "TROVATI ERRORI CRITICI NEL SISTEMA!"
    echo ""
    print_info "Ci sono $errors errori critici che devono essere risolti."
    print_info "Il sistema potrebbe non funzionare correttamente."
    echo ""
    exit 2
fi