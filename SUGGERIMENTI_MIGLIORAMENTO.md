# Suggerimenti di Miglioramento - Issue #154

## ✅ Problemi Risolti

### 1. **CRITICO - Errore di Sintassi JavaScript**
**Problema**: Apostrofi non escaped nelle stringhe JavaScript causavano errori di parsing
- Linea 1405: `l'operatore` 
- Linea 1458 e 1533: `l'assistenza`

**Soluzione Applicata**: Aggiunto escape (`\'`) agli apostrofi nelle stringhe delimitate da apice singolo

---

## 🔍 Problemi Logici e Funzionali Identificati

### 1. **ALTA PRIORITÀ - Selector Esperienza Richiede Validazione**
**File**: `assets/js/admin.js` (linee 1698-1873)

**Problema**: 
- Il selector dell'esperienza nel calendario admin non ha validazione
- Se nessuna esperienza è selezionata, la chiamata API fallisce silenziosamente perché il parametro `experience` è richiesto

**Codice Attuale**:
```javascript
const selectedExperience = experienceSelect && experienceSelect.value ? parseInt(String(experienceSelect.value), 10) || 0 : 0;
if (selectedExperience > 0) {
    url.searchParams.set('experience', String(selectedExperience));
}
```

**Suggerimento**:
```javascript
const selectedExperience = experienceSelect && experienceSelect.value ? parseInt(String(experienceSelect.value), 10) || 0 : 0;
if (selectedExperience <= 0) {
    // Mostra messaggio all'utente invece di fare una chiamata che fallirà
    showError(calendarConfig.i18n?.selectExperienceFirst || 'Seleziona un\'esperienza per visualizzare la disponibilità');
    setLoading(false);
    renderSlots([]);
    return;
}
url.searchParams.set('experience', String(selectedExperience));
```

### 2. **ALTA PRIORITÀ - Gestione Errori API Incompleta**
**File**: `assets/js/front.js` (linea 2502-2543)

**Problema**: 
- La funzione `loadSlotsForDate` non gestisce errori HTTP specifici (401, 403, 404, 500)
- Il messaggio di errore generico non aiuta l'utente a capire il problema

**Suggerimento**:
```javascript
const response = await fetch(url.toString(), {
    credentials: 'same-origin',
    headers: buildRestHeaders(),
});

if (!response.ok) {
    let errorMessage = container.getAttribute('data-error-label') || 'Impossibile caricare le fasce';
    if (response.status === 404) {
        errorMessage = 'Esperienza non trovata';
    } else if (response.status === 401 || response.status === 403) {
        errorMessage = 'Accesso negato. Ricarica la pagina e riprova.';
    } else if (response.status >= 500) {
        errorMessage = 'Errore del server. Riprova tra qualche minuto.';
    }
    throw new Error(errorMessage);
}
```

### 3. **MEDIA PRIORITÀ - Timezone Handling Potenzialmente Errato**
**File**: `assets/js/front.js` (linee 2517-2527)

**Problema**:
- Il codice usa `timeZone: 'UTC'` quando formatta date che dovrebbero essere locali
- La variabile `tz` è dichiarata ma non usata

**Codice Attuale**:
```javascript
const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
const toLocal = (sql) => {
    const d = new Date(String(sql).replace(' ', 'T') + 'Z');
    if (Number.isNaN(d.getTime())) return '';
    try {
        return new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZone: 'UTC' }).format(d);
    } catch (e) {
        // fallback
    }
};
```

**Suggerimento**:
```javascript
const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
const toLocal = (sql) => {
    const d = new Date(String(sql).replace(' ', 'T') + 'Z');
    if (Number.isNaN(d.getTime())) return '';
    try {
        // Usa il timezone locale invece di UTC
        return new Intl.DateTimeFormat(undefined, { 
            hour: '2-digit', 
            minute: '2-digit', 
            timeZone: tz  // <-- FIX: usa il timezone locale
        }).format(d);
    } catch (e) {
        const hh = String(d.getUTCHours()).padStart(2, '0');
        const mm = String(d.getUTCMinutes()).padStart(2, '0');
        return `${hh}:${mm}`;
    }
};
```

### 4. **MEDIA PRIORITÀ - Mancanza di Debouncing per le Chiamate API**
**File**: `assets/js/admin.js` (linea 1927-1929)

**Problema**:
- Ogni cambio di esperienza nel selector trigger immediatamente una chiamata API
- Se l'utente cambia rapidamente selezione, vengono fatte chiamate inutili

**Suggerimento**:
Aggiungere debouncing:
```javascript
let loadTimeout = null;
experienceSelect.addEventListener('change', () => {
    if (loadTimeout) {
        clearTimeout(loadTimeout);
    }
    loadTimeout = setTimeout(() => {
        loadMonth(currentMonth);
    }, 300); // Attendi 300ms prima di fare la chiamata
});
```

### 5. **MEDIA PRIORITÀ - Capacità Slot Non Mostrata**
**File**: `assets/js/front.js` (linea 2528-2534)

**Problema**:
- I dati dell'API includono `capacity_total` ma il frontend imposta sempre `remaining: 0`
- Gli utenti non possono vedere quanti posti sono disponibili

**Codice Attuale**:
```javascript
return {
    id: start,
    time: toLocal(start),
    remaining: 0,  // <-- Hardcoded a 0!
    start_iso: start.replace(' ', 'T') + 'Z',
    end_iso: end.replace(' ', 'T') + 'Z',
};
```

**Suggerimento**:
```javascript
return {
    id: start,
    time: toLocal(start),
    remaining: parseInt(s.capacity_total || 0, 10),  // <-- Usa la capacità dall'API
    start_iso: start.replace(' ', 'T') + 'Z',
    end_iso: end.replace(' ', 'T') + 'Z',
};
```

### 6. **MEDIA PRIORITÀ - AvailabilityService Non Considera Prenotazioni Esistenti**
**File**: `src/Booking/AvailabilityService.php` (linee 27-168)

**Problema**:
- `get_virtual_slots` calcola solo slot teorici basati su disponibilità configurata
- Non controlla quante prenotazioni/ordini esistono già per quello slot
- Il campo `capacity_total` è statico

**Suggerimento**:
Dopo aver generato gli slot virtuali, interrogare la tabella degli ordini/prenotazioni per calcolare la capacità rimanente effettiva:

```php
// Alla fine di get_virtual_slots(), prima del return:
foreach ($slots as &$slot) {
    $booked = Reservations::count_bookings_for_slot(
        $slot['experience_id'],
        $slot['start'],
        $slot['end']
    );
    $slot['capacity_remaining'] = max(0, $slot['capacity_total'] - $booked);
}
```

### 7. **BASSA PRIORITÀ - Manca Indicatore di Caricamento nel Calendar Selector**
**File**: `assets/js/admin.js`

**Problema**:
- Quando l'utente cambia esperienza, non c'è feedback visivo immediato
- L'indicatore di loading appare solo dopo il debounce/delay della richiesta

**Suggerimento**:
Aggiungere classe CSS immediatamente:
```javascript
experienceSelect.addEventListener('change', () => {
    setLoading(true); // Mostra loading subito
    loadMonth(currentMonth);
});
```

### 8. **BASSA PRIORITÀ - Date Picker Non Ha Attributi ARIA**
**File**: `assets/js/front.js` (linea 2105-2121)

**Problema**:
- Il date picker custom non ha etichette accessibili
- Gli screen reader non forniscono feedback adeguato

**Suggerimento**:
```javascript
dateInput.setAttribute('aria-label', localize('Seleziona data esperienza'));
dateInput.setAttribute('aria-describedby', 'date-picker-help');
```

---

## 🚀 Miglioramenti Funzionali Suggeriti

### 1. **Cache Locale per Slot**
Implementare una cache in-memory per evitare di richiedere gli stessi dati più volte:

```javascript
const slotsCache = new Map();
const CACHE_TTL = 60000; // 1 minuto

async function loadSlotsForDate(dateKey) {
    const cacheKey = `${experienceId}-${dateKey}`;
    const cached = slotsCache.get(cacheKey);
    
    if (cached && Date.now() - cached.timestamp < CACHE_TTL) {
        renderSlots(slotsContainer, cached.data, { selectedSlot: null });
        return;
    }
    
    // ... fetch come prima ...
    
    slotsCache.set(cacheKey, {
        data: mapped,
        timestamp: Date.now()
    });
}
```

### 2. **Pre-fetch dei Dati Adiacenti**
Quando l'utente seleziona un giorno, pre-caricare i dati del giorno successivo:

```javascript
// Dopo loadSlotsForDate(date)
const nextDay = addDays(date, 1);
if (isDateAvailable(nextDay)) {
    loadSlotsForDate(nextDay); // Pre-fetch silenzioso
}
```

### 3. **Retry Logic per Chiamate API Fallite**
Aggiungere retry automatico per errori di rete temporanei:

```javascript
async function fetchWithRetry(url, options, maxRetries = 3) {
    for (let i = 0; i < maxRetries; i++) {
        try {
            const response = await fetch(url, options);
            if (response.ok || response.status < 500) {
                return response; // Non ritentare per errori 4xx
            }
        } catch (err) {
            if (i === maxRetries - 1) throw err;
            await new Promise(resolve => setTimeout(resolve, 1000 * Math.pow(2, i)));
        }
    }
}
```

### 4. **Validazione Date Lato Server**
**File**: `src/Api/RestRoutes.php` (linea 409-435)

Aggiungere validazione formato date:

```php
public function get_virtual_availability(WP_REST_Request $request)
{
    $experience_id = absint((string) $request->get_param('experience'));
    $start = sanitize_text_field((string) $request->get_param('start'));
    $end = sanitize_text_field((string) $request->get_param('end'));

    if ($experience_id <= 0 || ! $start || ! $end) {
        return new WP_Error('fp_exp_availability_params', __('Parametri non validi.', 'fp-experiences'), ['status' => 400]);
    }

    // NUOVO: Validazione formato date
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        return new WP_Error('fp_exp_invalid_date_format', __('Formato data non valido. Usa YYYY-MM-DD.', 'fp-experiences'), ['status' => 400]);
    }

    // NUOVO: Validazione range temporale ragionevole
    $start_ts = strtotime($start);
    $end_ts = strtotime($end);
    if ($end_ts < $start_ts) {
        return new WP_Error('fp_exp_invalid_range', __('La data di fine deve essere successiva alla data di inizio.', 'fp-experiences'), ['status' => 400]);
    }
    
    // Limita il range a max 1 anno per evitare query pesanti
    if (($end_ts - $start_ts) > (365 * 24 * 60 * 60)) {
        return new WP_Error('fp_exp_range_too_large', __('Il range di date non può superare 1 anno.', 'fp-experiences'), ['status' => 400]);
    }

    $slots = AvailabilityService::get_virtual_slots($experience_id, $start, $end);
    // ... resto del codice ...
}
```

---

## 📊 Metriche di Qualità

### Copertura Problemi
- **Critici**: 1 (RISOLTO ✅)
- **Alta Priorità**: 2
- **Media Priorità**: 5
- **Bassa Priorità**: 2

### Impatto
- **Funzionalità**: Alta (capacità slot, timezone)
- **UX**: Media (feedback errori, loading states)
- **Performance**: Media (debouncing, cache)
- **Accessibilità**: Bassa (ARIA labels)

---

## 🎯 Raccomandazioni Immediate

1. ✅ **FATTO**: Risolvere errore sintassi JavaScript (apostrofi)
2. ⚠️ **URGENTE**: Implementare validazione selector esperienza (punto 1)
3. ⚠️ **URGENTE**: Migliorare gestione errori API (punto 2)
4. 📅 **BREVE TERMINE**: Correggere timezone handling (punto 3)
5. 📅 **BREVE TERMINE**: Mostrare capacità effettiva slot (punto 5 + 6)

---

## 📝 Note di Test

Per testare le modifiche:

1. **Test Timezone**: Verificare con utenti in timezone diversi da UTC
2. **Test Capacità**: Creare prenotazioni e verificare che la capacità si aggiorni
3. **Test Errori**: Simulare errori API (403, 500) e verificare i messaggi
4. **Test Accessibilità**: Usare screen reader (NVDA/JAWS) per verificare navigazione calendario

---

Generato automaticamente da Cursor AI - Background Agent
Data: 2025-10-05
Branch: cursor/check-implementation-correctness-1173
