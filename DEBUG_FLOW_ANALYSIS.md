# Analisi Completa del Flusso del Calendario

## Flusso dei Dati

### 1. BACKEND: Salvataggio Esperienza
```
Admin Panel → ExperienceMetaBoxes::save_calendar_meta()
    ↓
    Salva _fp_exp_recurrence con:
    - frequency: 'weekly'
    - start_date: '2025-10-08'
    - end_date: ''
    - days: ['monday', 'wednesday', 'friday']
    - time_sets: [{ times: ['09:00', '14:00'], capacity: 10 }]
    ↓
    sync_recurrence_to_availability()
    ↓
    Salva _fp_exp_availability con:
    - frequency: 'weekly'
    - times: ['09:00', '14:00']
    - days_of_week: ['monday', 'wednesday', 'friday']
    - slot_capacity: 10
    - start_date: '2025-10-08'  ← IMPORTANTE
    - end_date: ''               ← IMPORTANTE
```

### 2. BACKEND: Rendering Template Calendar
```
[fp_exp_calendar id="X"] shortcode
    ↓
CalendarShortcode::get_context()
    ↓
generate_calendar_months(experience_id, 1)
    ↓
get_post_meta(_fp_exp_availability) → verifica se ci sono times
    ↓
Se times vuoto → return [] (veloce)
Se times presente → continua
    ↓
Per ogni mese (default 1):
    ↓
    AvailabilityService::get_virtual_slots(exp_id, start_month, end_month)
        ↓
        Legge _fp_exp_availability
        ↓
        Applica filtri start_date e end_date
        ↓
        Genera slot per i giorni configurati
        ↓
        Return array di slot
    ↓
Raggruppa slot per giorno
    ↓
Return array: ['2025-10' => ['days' => ['2025-10-08' => [slot1, slot2]]]]
```

### 3. FRONTEND: Template Rendering
```
calendar.php template
    ↓
Riceve $months array dal backend
    ↓
Crea $slots_map: { '2025-10-08': [slot1, slot2] }
    ↓
Output HTML con data-slots="JSON"
```

### 4. FRONTEND: JavaScript Initialization
```
front.js carica
    ↓
Legge data-config con calendar.slots_map
    ↓
availability.js init()
    ↓
Popola _calendarMap da slots_map
```

### 5. FRONTEND: Navigazione Calendario Widget
```
Utente naviga al mese successivo
    ↓
updateCalendarMonth(date)
    ↓
prefetchMonth('2025-11')
    ↓
Chiamata API: /wp-json/fp-exp/v1/availability?experience=X&start=2025-11-01&end=2025-11-30
    ↓
Backend: RestRoutes::get_virtual_availability()
    ↓
AvailabilityService::get_virtual_slots(X, '2025-11-01', '2025-11-30')
    ↓
Return slots con start_date e end_date applicati
    ↓
Frontend: raggruppa per giorno in _calendarMap
    ↓
Aggiorna pulsanti giorni con data-available
```

## Punti Critici da Verificare

### ✓ Backend PHP
1. ✓ AvailabilityService legge start_date e end_date
2. ✓ AvailabilityService applica filtri di data
3. ✓ ExperienceMetaBoxes sincronizza le date
4. ✓ CalendarShortcode controlla se ci sono times prima di generare

### ✓ Frontend JavaScript
1. ✓ availability.js salva gli slot nella _calendarMap durante prefetch
2. ✓ front.js usa prefetchMonth invece di fetchAvailability per singolo giorno
3. ✓ Ridotto numero di chiamate API da 30+ a 1 per mese

### ⚠️ Da Verificare
1. Template calendar.php passa correttamente i dati?
2. Widget shortcode passa i dati al frontend?
3. API endpoint funziona correttamente?
