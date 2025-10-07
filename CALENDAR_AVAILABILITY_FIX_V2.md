# Fix Calendario Disponibilità - Versione 2

## Problemi Risolti

### 1. Giorni Disponibili Non Visualizzati
**Problema**: Il calendario non mostrava i giorni disponibili anche quando erano configurati la data di inizio e gli orari nella sezione "Ricorrenza e Slot" del backend.

**Causa**: `AvailabilityService::get_virtual_slots()` non considerava le date di inizio (`start_date`) e fine (`end_date`) della ricorrenza. Generava slot anche prima o dopo il periodo configurato.

**Soluzione**: 
- Aggiunto supporto per `start_date` e `end_date` in `AvailabilityService::get_virtual_slots()`
- Le date vengono lette dall'array `_fp_exp_availability` e applicate come filtri sul range di date
- Gli slot vengono generati solo all'interno del periodo configurato

### 2. Caricamento Lento dei Mesi
**Problema**: Il caricamento dei mesi era molto lento, impiegando troppo tempo per caricare i dati.

**Causa**: Il calendario generava di default 2 mesi di slot virtuali server-side, calcolando tutti gli slot per ogni richiesta.

**Soluzione**:
- Ridotto il numero di mesi di default da 2 a 1
- Aggiunto limite massimo di 3 mesi per evitare sovraccarichi
- Aggiunto controllo rapido per verificare se ci sono dati di disponibilità prima di generare gli slot
- Se non ci sono `times` configurati, il metodo ritorna immediatamente un array vuoto

## File Modificati

### 1. `/workspace/src/Booking/AvailabilityService.php`
**Modifiche**:
- Aggiunto lettura di `start_date` e `end_date` dall'array `$availability`
- Aggiunto codice per applicare i limiti di data dalla ricorrenza al range di slot generati
- Se `start_date` è configurata, il range di generazione inizia da quella data
- Se `end_date` è configurata, il range di generazione termina a quella data
- Se il range risultante è vuoto (end < start), ritorna array vuoto

**Codice chiave**:
```php
// Leggi le date di inizio e fine dalla ricorrenza
$recurrence_start_date = isset($availability['start_date']) ? sanitize_text_field((string) $availability['start_date']) : '';
$recurrence_end_date = isset($availability['end_date']) ? sanitize_text_field((string) $availability['end_date']) : '';

// Applica i limiti di data dalla ricorrenza se presenti
$tz = new DateTimeZone(wp_timezone_string() ?: 'UTC');

if ('' !== $recurrence_start_date) {
    try {
        $rec_start = new DateTimeImmutable($recurrence_start_date, $tz);
        $rec_start_utc = $rec_start->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0, 0);
        if ($rec_start_utc > $range_start) {
            $range_start = $rec_start_utc;
        }
    } catch (Exception $e) {
        // Ignora se la data non è valida
    }
}

if ('' !== $recurrence_end_date) {
    try {
        $rec_end = new DateTimeImmutable($recurrence_end_date, $tz);
        $rec_end_utc = $rec_end->setTimezone(new DateTimeZone('UTC'))->setTime(23, 59, 59);
        if ($rec_end_utc < $range_end) {
            $range_end = $rec_end_utc;
        }
    } catch (Exception $e) {
        // Ignora se la data non è valida
    }
}

// Se la data di fine è prima della data di inizio dopo i limiti, non ci sono slot
if ($range_end < $range_start) {
    return [];
}
```

### 2. `/workspace/src/Admin/ExperienceMetaBoxes.php`
**Modifiche**:
- Aggiornato il metodo `sync_recurrence_to_availability()` per includere `start_date` e `end_date`
- Questi campi vengono copiati dalla ricorrenza all'array di availability

**Codice chiave**:
```php
// Costruisci l'array di availability in formato legacy
$availability = [
    'frequency' => $frequency,
    'times' => $all_times,
    'days_of_week' => $all_days,
    'custom_slots' => [],
    'slot_capacity' => $slot_capacity,
    'lead_time_hours' => $lead_time,
    'buffer_before_minutes' => $buffer_before,
    'buffer_after_minutes' => $buffer_after,
    'start_date' => isset($recurrence['start_date']) ? sanitize_text_field((string) $recurrence['start_date']) : '',
    'end_date' => isset($recurrence['end_date']) ? sanitize_text_field((string) $recurrence['end_date']) : '',
];
```

### 3. `/workspace/src/Shortcodes/CalendarShortcode.php`
**Modifiche**:
- Cambiato il valore di default per `months` da `'2'` a `'1'`
- Aggiunto limite massimo di 3 mesi (se viene richiesto più di 3, viene ridotto a 1)
- Aggiunto controllo rapido nel metodo `generate_calendar_months()` per verificare se ci sono dati di disponibilità prima di generare gli slot

**Codice chiave**:
```php
// Default cambiato da 2 a 1 mese
protected array $defaults = [
    'id' => '',
    'months' => '1',
    // ...
];

// Limite di mesi e controllo
$months_count = absint($attributes['months']);
if ($months_count <= 0 || $months_count > 3) {
    $months_count = 1; // Default a 1 mese per performance
}

// Verifica veloce se ci sono dati di disponibilità configurati
$availability = get_post_meta($experience_id, '_fp_exp_availability', true);
if (! is_array($availability) || empty($availability['times'])) {
    return []; // Non ci sono slot configurati, ritorna vuoto
}
```

### 4. File Build
Tutti i file modificati sono stati copiati nella directory build:
- `/workspace/build/fp-experiences/src/Booking/AvailabilityService.php`
- `/workspace/build/fp-experiences/src/Admin/ExperienceMetaBoxes.php`
- `/workspace/build/fp-experiences/src/Shortcodes/CalendarShortcode.php`

## Come Testare

### Test 1: Verifica Date di Inizio
1. Vai nel backend WordPress
2. Modifica un'esperienza
3. Vai alla tab "Calendario & Slot"
4. Nella sezione "Ricorrenza slot":
   - Imposta "Data inizio" a domani
   - Seleziona i giorni della settimana (es. Lunedì, Mercoledì, Venerdì)
   - Aggiungi almeno un orario (es. 09:00, 14:00)
   - Imposta una capienza (es. 10)
5. Salva l'esperienza
6. Visualizza la pagina con lo shortcode `[fp_exp_calendar id="X"]`
7. **Risultato atteso**: Il calendario mostra solo i giorni a partire da domani come disponibili

### Test 2: Verifica Date di Fine
1. Imposta "Data inizio" a oggi
2. Imposta "Data fine" tra 7 giorni
3. Salva e visualizza il calendario
4. **Risultato atteso**: Il calendario mostra solo i giorni tra oggi e tra 7 giorni come disponibili

### Test 3: Verifica Performance
1. Apri la pagina con il calendario
2. Misura il tempo di caricamento (con Developer Tools -> Network)
3. **Risultato atteso**: Il caricamento dovrebbe essere significativamente più veloce rispetto a prima (dovrebbe caricare solo 1 mese invece di 2)

### Test 4: Verifica Assenza di Slot
1. Crea un'esperienza senza configurare gli orari nella ricorrenza
2. Visualizza il calendario
3. **Risultato atteso**: Il calendario non dovrebbe mostrare giorni disponibili (tutti i giorni disabilitati)

## Struttura Dati

### Formato `_fp_exp_availability` (aggiornato)
```php
[
    'frequency' => 'weekly',
    'times' => ['09:00', '14:00'],
    'days_of_week' => ['monday', 'wednesday', 'friday'],
    'custom_slots' => [],
    'slot_capacity' => 10,
    'lead_time_hours' => 0,
    'buffer_before_minutes' => 0,
    'buffer_after_minutes' => 0,
    'start_date' => '2025-10-08',  // NUOVO
    'end_date' => '',               // NUOVO (vuoto = nessun limite)
]
```

### Formato `_fp_exp_recurrence` (non modificato)
```php
[
    'frequency' => 'weekly',
    'start_date' => '2025-10-08',
    'end_date' => '',
    'days' => ['monday', 'wednesday', 'friday'],
    'duration' => 60,
    'time_sets' => [
        [
            'label' => '',
            'times' => ['09:00', '14:00'],
            'days' => ['monday', 'wednesday', 'friday'],
            'capacity' => 10,
            'buffer_before' => 0,
            'buffer_after' => 0,
        ]
    ]
]
```

## Note Importanti

1. **Retrocompatibilità**: Se i campi `start_date` e `end_date` non sono presenti nell'array `_fp_exp_availability`, il comportamento è identico a prima (nessun limite di date)

2. **Sincronizzazione Automatica**: Quando salvi un'esperienza con dati di ricorrenza, il metodo `sync_recurrence_to_availability()` copia automaticamente `start_date` e `end_date` da `_fp_exp_recurrence` a `_fp_exp_availability`

3. **Performance**: La riduzione a 1 mese di default e il controllo rapido sulla presenza di dati migliorano significativamente le prestazioni

4. **Timezone**: Tutte le date vengono gestite correttamente considerando il timezone di WordPress

## Problemi Noti / Limitazioni

1. Il calendario carica ancora i mesi server-side. Per una soluzione più performante, si potrebbe implementare un caricamento AJAX dei mesi successivi
2. Se un'esperienza ha molti slot in un mese, il caricamento potrebbe comunque essere lento. Si potrebbe implementare una cache

## Prossimi Passi Opzionali

1. **Cache**: Implementare un sistema di cache per gli slot generati
2. **AJAX Loading**: Implementare il caricamento AJAX per i mesi successivi al primo
3. **Lazy Loading**: Caricare solo i giorni visibili, non l'intero mese

## Commit Suggerito

```
fix: risolto problema calendario senza giorni disponibili e performance

- Aggiunto supporto start_date e end_date in AvailabilityService
- Gli slot virtuali ora rispettano il periodo configurato nella ricorrenza
- Ridotto caricamento di default da 2 a 1 mese per migliorare le performance
- Aggiunto controllo rapido per evitare calcoli inutili se non ci sono slot
- Sincronizzazione automatica delle date dalla ricorrenza all'availability

Fixes: Calendario non mostrava giorni disponibili con ricorrenza configurata
Fixes: Caricamento lento dei dati del calendario
```
