# Fix del Calendario Frontend - Giorni Disponibili Non Visualizzati

## Problema
Il calendario frontend si popolava ma non mostrava nessun giorno disponibile anche quando erano stati configurati la data di inizio e gli orari nella sezione "Ricorrenza e Slot" del backend.

## Causa Root
Il problema era causato da due fattori:

1. **CalendarShortcode restituiva un array vuoto**: Il file `CalendarShortcode.php` restituiva `'months' => []`, quindi il template `calendar.php` non aveva dati da renderizzare.

2. **Mancata sincronizzazione tra formato recurrence e availability**: L'admin salvava i dati nel nuovo formato di ricorrenza (con `time_sets`), ma `AvailabilityService::get_virtual_slots()` leggeva dal vecchio formato `_fp_exp_availability` (con `frequency`, `times`, `days_of_week`). Non c'era sincronizzazione tra i due formati.

## Soluzioni Implementate

### 1. CalendarShortcode.php
**File**: `/workspace/src/Shortcodes/CalendarShortcode.php`

**Modifiche**:
- Aggiunto il metodo `generate_calendar_months()` che genera la struttura dei mesi del calendario
- Questo metodo chiama `AvailabilityService::get_virtual_slots()` per ottenere gli slot disponibili per ogni mese
- Gli slot vengono raggruppati per giorno e passati al template

**Risultato**: Il calendario ora renderizza correttamente la struttura dei mesi con i dati di disponibilità.

### 2. ExperienceMetaBoxes.php
**File**: `/workspace/src/Admin/ExperienceMetaBoxes.php`

**Modifiche**:
- Aggiunto il metodo privato `sync_recurrence_to_availability()` 
- Quando vengono salvati i dati di ricorrenza, questi vengono automaticamente trasformati nel formato legacy che `AvailabilityService` si aspetta
- Questo permette la retrocompatibilità e assicura che i dati configurati nell'admin siano immediatamente disponibili per il calendario frontend

**Funzionamento**:
- Estrae tutti gli orari dai `time_sets`
- Estrae tutti i giorni della settimana configurati
- Crea un array `_fp_exp_availability` nel formato compatibile con `AvailabilityService`
- Salva entrambe le strutture dati (nuova e legacy)

## Come Testare

1. **Nel Backend**:
   - Vai a "Esperienze" > Modifica un'esperienza
   - Vai alla tab "Calendario e Slot"
   - Nella sezione "Ricorrenza slot":
     - Imposta una "Data inizio" (es. oggi o domani)
     - Opzionalmente imposta una "Data fine"
     - Seleziona i "Giorni attivi" (es. Lunedì, Mercoledì, Venerdì)
     - Nella sezione "Set di orari e capienza", aggiungi almeno un orario (es. "09:00", "14:00")
     - Imposta una capienza (es. 10 persone)
   - Salva l'esperienza

2. **Nel Frontend**:
   - Crea o modifica una pagina
   - Inserisci lo shortcode: `[fp_exp_calendar id="ID_ESPERIENZA"]` (sostituisci ID_ESPERIENZA con l'ID numerico della tua esperienza)
   - Pubblica/aggiorna la pagina
   - Visualizza la pagina

3. **Verifica**:
   - Il calendario dovrebbe mostrare i mesi configurati
   - I giorni con disponibilità dovrebbero essere evidenziati (non disabilitati)
   - Cliccando su un giorno disponibile, dovrebbero apparire le fasce orarie configurate

## Strutture Dati

### Nuovo Formato (Ricorrenza)
Salvato in `_fp_exp_recurrence`:
```php
[
    'frequency' => 'weekly',
    'start_date' => '2025-01-15',
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

### Formato Legacy (Availability)
Salvato in `_fp_exp_availability`:
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
]
```

## Note Importanti

1. **Retrocompatibilità**: La soluzione mantiene entrambi i formati per garantire la retrocompatibilità con il codice esistente.

2. **Salvataggio Automatico**: Ogni volta che salvi un'esperienza con dati di ricorrenza configurati, il metodo `sync_recurrence_to_availability()` viene automaticamente chiamato per sincronizzare i dati.

3. **Slot Virtuali**: Il calendario usa "slot virtuali" (non persistiti nel database) generati al volo da `AvailabilityService` basandosi sui dati di `_fp_exp_availability`.

4. **Generazione Slot Persistenti**: Se vuoi creare slot persistenti nel database, usa il pulsante "Genera/Rigenera Slot" nell'admin (questo usa il formato di ricorrenza per creare record nella tabella `wp_fp_exp_slots`).

## File Modificati

1. `/workspace/src/Shortcodes/CalendarShortcode.php`
2. `/workspace/src/Admin/ExperienceMetaBoxes.php`
3. `/workspace/build/fp-experiences/src/Shortcodes/CalendarShortcode.php` (copia build)
4. `/workspace/build/fp-experiences/src/Admin/ExperienceMetaBoxes.php` (copia build)

## Risoluzione di Eventuali Problemi

Se il calendario ancora non mostra giorni disponibili:

1. **Verifica i dati salvati**: Controlla che i metadati siano stati salvati correttamente:
   ```php
   $availability = get_post_meta($experience_id, '_fp_exp_availability', true);
   var_dump($availability);
   ```

2. **Verifica il timezone**: Assicurati che il timezone di WordPress sia configurato correttamente in "Impostazioni" > "Generali"

3. **Controlla la data di inizio**: La data di inizio deve essere oggi o nel futuro

4. **Verifica il lead time**: Se hai impostato un lead time (tempo di anticipo per la prenotazione), potrebbe impedire la visualizzazione di slot troppo vicini

5. **Cache**: Se usi plugin di caching, svuota la cache dopo aver salvato le modifiche

## Commit Message Suggerito

```
fix: risolto problema calendario frontend senza giorni disponibili

- Aggiunto metodo generate_calendar_months() in CalendarShortcode per popolare i mesi
- Aggiunto sync_recurrence_to_availability() in ExperienceMetaBoxes per sincronizzare 
  i dati di ricorrenza nel formato legacy richiesto da AvailabilityService
- Il calendario ora mostra correttamente i giorni disponibili basandosi sui dati 
  di ricorrenza configurati nell'admin
```
