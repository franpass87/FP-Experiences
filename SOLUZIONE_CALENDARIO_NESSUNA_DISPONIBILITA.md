# Soluzione: Calendario Nessuna Disponibilità

## 🔍 Problema

Il calendario non mostra giorni disponibili nonostante siano configurati:
- Data inizio
- Giorni della settimana
- Orari nei time sets
- Capienza

URL problema: https://fpdevelopmentenviron6716.live-website.com/experience/test/

## 📋 Diagnosi

### Fase 1: Esegui lo Script di Debug

Ho creato uno script di debug per identificare esattamente dove si trova il problema:

```bash
# Copia lo script sulla tua installazione WordPress
cd /path/to/wordpress

# Esegui via WP-CLI (metodo raccomandato)
wp eval-file debug-calendar-data.php [ID_ESPERIENZA]

# OPPURE via browser
# 1. Carica il file nella root di WordPress
# 2. Accedi a: https://tuosito.com/debug-calendar-data.php?id=[ID_ESPERIENZA]
```

Lo script verificherà:
1. ✅ Dati di ricorrenza (`_fp_exp_recurrence`)
2. ✅ Dati di availability (`_fp_exp_availability`)
3. ✅ Generazione slot virtuali
4. ✅ Slot persistenti nel database
5. ✅ Raccomandazioni specifiche per il tuo caso

### Output Atteso

Lo script ti dirà esattamente cosa manca:
- ❌ Nessun time set configurato
- ❌ Campo "times" vuoto in availability
- ❌ Capienza a 0
- ❌ Sincronizzazione non avvenuta

## 🔧 Soluzioni Comuni

### Problema 1: Sincronizzazione Non Avvenuta

**Sintomo**: `_fp_exp_availability` è vuoto o non ha il campo `times`

**Causa**: Il metodo `sync_recurrence_to_availability()` non viene eseguito o non estrae correttamente i dati dai time_sets

**Soluzione Immediata**:
```bash
# Salva di nuovo l'esperienza dall'admin
# Questo forza la ri-sincronizzazione
```

**Soluzione Programmatica** (se il problema persiste):

Aggiungi questo codice a `ExperienceMetaBoxes.php` alla riga 2639, PRIMA della chiamata a `sync_recurrence_to_availability`:

```php
// DEBUG: Forza sempre la sincronizzazione
error_log('FP_EXP DEBUG: Syncing recurrence to availability for post ' . $post_id);
error_log('FP_EXP DEBUG: Recurrence data: ' . print_r($recurrence_meta, true));
```

E alla riga 2738 in `sync_recurrence_to_availability`, PRIMA della condizione di salvataggio:

```php
// DEBUG: Log dei dati estratti
error_log('FP_EXP DEBUG: Extracted times: ' . print_r($all_times, true));
error_log('FP_EXP DEBUG: Extracted days: ' . print_r($all_days, true));
error_log('FP_EXP DEBUG: Slot capacity: ' . $slot_capacity);
```

Poi ri-salva l'esperienza e controlla i log WordPress per vedere cosa viene estratto.

### Problema 2: Time Sets Vuoti

**Sintomo**: La ricorrenza è salvata ma `time_sets` è un array vuoto

**Causa**: Il frontend admin non salva correttamente i time_sets

**Soluzione**:
1. Apri l'esperienza nell'admin
2. Vai alla tab "Calendario & Slot"
3. Nella sezione "Ricorrenza slot" → "Set di orari e capienza":
   - Clicca su "Aggiungi orario"
   - Inserisci almeno un orario (es. 09:00)
   - Imposta una capienza (es. 10)
   - Seleziona i giorni della settimana
4. Salva l'esperienza
5. Verifica con lo script di debug

### Problema 3: Capienza a 0

**Sintomo**: Tutto è configurato ma `slot_capacity` è 0

**Causa**: Il campo capienza non viene salvato correttamente

**Soluzione**:
1. Nell'admin, vai a "Calendario & Slot"
2. Nella sezione "Capienza slot globale", imposta un numero > 0
3. Salva

**OPPURE** imposta la capienza nel time set specifico:
1. Nella sezione "Set di orari e capienza"
2. Imposta il campo "Capienza" per ogni time set
3. Salva

### Problema 4: Data Inizio nel Passato

**Sintomo**: Gli slot vengono generati ma sono tutti nel passato

**Causa**: La `start_date` è impostata a una data passata

**Soluzione**:
1. Vai a "Calendario & Slot" → "Ricorrenza slot"
2. Imposta "Data inizio" a oggi o nel futuro
3. Salva

### Problema 5: Lead Time Troppo Alto

**Sintomo**: Gli slot vengono generati ma filtrati dal lead time

**Causa**: Il `lead_time_hours` filtra tutti gli slot

**Soluzione**:
1. Vai a "Calendario & Slot" → "Tempo di anticipo richiesto"
2. Riduci il valore o impostalo a 0
3. Salva

## 🛠️ Fix Definitivo al Codice

Se dopo aver verificato con lo script di debug il problema persiste, potrebbe esserci un bug nel codice di sincronizzazione.

### Fix 1: Forza Sincronizzazione Anche con Recurrence Default

Nel file `/workspace/src/Admin/ExperienceMetaBoxes.php`, modifica la linea 2634-2642:

**PRIMA:**
```php
if ($recurrence_meta !== Recurrence::defaults()) {
    update_post_meta($post_id, '_fp_exp_recurrence', $recurrence_meta);
    
    // Trasforma i dati di ricorrenza nel formato compatibile con AvailabilityService
    // per permettere la visualizzazione nel calendario frontend
    $this->sync_recurrence_to_availability($post_id, $recurrence_meta, $slot_capacity, $lead_time, $buffer_before, $buffer_after);
} else {
    delete_post_meta($post_id, '_fp_exp_recurrence');
}
```

**DOPO:**
```php
if ($recurrence_meta !== Recurrence::defaults()) {
    update_post_meta($post_id, '_fp_exp_recurrence', $recurrence_meta);
} else {
    delete_post_meta($post_id, '_fp_exp_recurrence');
}

// Sincronizza SEMPRE, anche se la ricorrenza è vuota/default
// Questo assicura che i dati legacy siano sempre aggiornati
$this->sync_recurrence_to_availability($post_id, $recurrence_meta, $slot_capacity, $lead_time, $buffer_before, $buffer_after);
```

### Fix 2: Migliora Estrazione Orari da Time Sets

Nel file `/workspace/src/Admin/ExperienceMetaBoxes.php`, alla linea 2670-2696, verifica che il codice sia corretto:

```php
if (isset($recurrence['time_sets']) && is_array($recurrence['time_sets'])) {
    foreach ($recurrence['time_sets'] as $set) {
        if (!is_array($set)) {
            continue;
        }
        
        // Raccogli gli orari
        if (isset($set['times']) && is_array($set['times'])) {
            foreach ($set['times'] as $time) {
                $time_str = trim((string) $time);
                if ($time_str !== '' && !in_array($time_str, $all_times, true)) {
                    $all_times[] = $time_str;
                }
            }
        }
        
        // Raccogli i giorni (per ricorrenze settimanali)
        if (isset($set['days']) && is_array($set['days'])) {
            foreach ($set['days'] as $day) {
                $day_str = trim((string) $day);
                if ($day_str !== '' && !in_array($day_str, $all_days, true)) {
                    $all_days[] = $day_str;
                }
            }
        }
    }
}
```

### Fix 3: Salva Sempre l'Availability (Non Solo se Ha Dati)

Nel file `/workspace/src/Admin/ExperienceMetaBoxes.php`, alla linea 2738, modifica:

**PRIMA:**
```php
// Salva solo se ci sono dati validi
if (!empty($availability['times']) || !empty($availability['custom_slots']) || $slot_capacity > 0) {
    update_post_meta($post_id, '_fp_exp_availability', $availability);
}
```

**DOPO:**
```php
// Salva sempre per mantenere la sincronizzazione
// Questo permette anche di cancellare i dati se necessario
if (!empty($availability['times']) || !empty($availability['custom_slots']) || $slot_capacity > 0) {
    update_post_meta($post_id, '_fp_exp_availability', $availability);
} else {
    // Se non ci sono dati, salva comunque un array vuoto per indicare che l'esperienza
    // è stata configurata ma al momento non ha disponibilità
    update_post_meta($post_id, '_fp_exp_availability', [
        'frequency' => 'weekly',
        'times' => [],
        'days_of_week' => [],
        'custom_slots' => [],
        'slot_capacity' => 0,
    ]);
}
```

## 🧪 Test della Soluzione

Dopo aver applicato i fix:

### 1. Test Backend
```bash
# Esegui lo script di debug
wp eval-file debug-calendar-data.php [ID_ESPERIENZA]

# Verifica che output mostri:
# ✅ Dati di ricorrenza trovati
# ✅ Dati di availability trovati
# ✅ X slot generati con successo
```

### 2. Test Frontend
1. Vai alla pagina con lo shortcode `[fp_exp_calendar id="X"]`
2. Apri Developer Tools (F12)
3. Nella Console, verifica:
```javascript
// Verifica che i moduli siano caricati
console.log(window.FPFront);
// Output atteso: { availability, slots, calendar, calendarStandalone }

// Verifica i dati del calendario
const calendar = document.querySelector('[data-fp-shortcode="calendar"]');
if (calendar) {
    console.log('Experience ID:', calendar.getAttribute('data-experience'));
    console.log('Slots data:', JSON.parse(calendar.getAttribute('data-slots')));
}
```

### 3. Test Visuale
1. Il calendario dovrebbe mostrare i mesi configurati
2. I giorni con disponibilità dovrebbero essere evidenziati (non grigi)
3. Cliccando su un giorno disponibile, dovrebbero apparire le fasce orarie
4. Ogni fascia dovrebbe mostrare i posti disponibili

## 🚀 Applicazione Rapida dei Fix

### Opzione A: Patch Automatica (Raccomandato)

```bash
cd /workspace

# Backup del file originale
cp src/Admin/ExperienceMetaBoxes.php src/Admin/ExperienceMetaBoxes.php.backup

# Applica i fix...
# (Usa l'editor o lo script di patch)
```

### Opzione B: Fix Manuale

1. Apri `/workspace/src/Admin/ExperienceMetaBoxes.php`
2. Applica i 3 fix sopra descritti
3. Salva il file
4. Copia il file nella build:
```bash
cp src/Admin/ExperienceMetaBoxes.php build/fp-experiences/src/Admin/ExperienceMetaBoxes.php
```
5. Svuota cache e ricarica

## 📞 Se il Problema Persiste

Se dopo aver applicato tutti i fix il problema persiste:

1. **Esegui lo script di debug** e salva l'output completo
2. **Controlla i log PHP** per errori:
```bash
tail -f /var/log/php-error.log
# O il percorso specifico del tuo server
```
3. **Controlla la console del browser** per errori JavaScript
4. **Verifica la risposta API**:
   - Apri Network tab in Developer Tools
   - Ricarica la pagina
   - Cerca chiamate a `/wp-json/fp-exp/v1/availability`
   - Controlla il JSON di risposta

5. **Fornisci i seguenti dati**:
   - Output completo dello script di debug
   - Screenshot della console del browser
   - Log PHP (se presenti errori)
   - Screenshot della configurazione nell'admin

## 📝 Checklist Finale

Prima di considerare il problema risolto, verifica:

- [ ] Script di debug mostra ✅ per tutti i punti
- [ ] `_fp_exp_availability` ha il campo `times` popolato
- [ ] `_fp_exp_availability` ha il campo `days_of_week` popolato
- [ ] `slot_capacity` > 0
- [ ] `start_date` è oggi o nel futuro
- [ ] `AvailabilityService::get_virtual_slots()` ritorna slot
- [ ] Il calendario frontend mostra giorni evidenziati
- [ ] Cliccando su un giorno appaiono le fasce orarie
- [ ] Nessun errore nella console del browser
- [ ] Nessun errore nei log PHP

## 🎯 Soluzione Alternativa Temporanea

Se hai urgenza e non puoi modificare il codice, come workaround temporaneo:

1. Invece di usare solo la ricorrenza, aggiungi anche slot persistenti:
   - Vai nell'admin → Modifica esperienza
   - Tab "Calendario & Slot"
   - Clicca su "Genera/Rigenera Slot"
   - Questo crea slot persistenti nel database
   
2. Oppure usa l'API REST per forzare la sincronizzazione:
```bash
curl -X POST 'https://tuosito.com/wp-json/fp-exp/v1/admin/sync-availability' \
  -H 'Content-Type: application/json' \
  -H 'X-WP-Nonce: [NONCE]' \
  -d '{"experience_id": [ID]}'
```

---

**Autore**: Fix Debug Calendario
**Data**: 2025-10-07
**Versione**: 1.0
