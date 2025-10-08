# Aggiornamento Importer - Supporto Calendario e Slot

## Modifiche Apportate

L'importer CSV è stato aggiornato per supportare le nuove configurazioni di calendario, slot e ricorrenze introdotte nel sistema.

### Nuovi Campi CSV

Sono stati aggiunti 8 nuovi campi al template CSV:

#### Campi di Ricorrenza
1. **recurrence_frequency** (string): Frequenza della ricorrenza
   - Valori: `daily`, `weekly`, `custom`
   - Default: `weekly`
   - Esempio: `weekly`

2. **recurrence_times** (string pipe-separated): Orari degli slot
   - Formato: `HH:MM|HH:MM|HH:MM`
   - Esempio: `09:00|14:00|16:00`
   - Validazione: formato orario valido (HH:MM o HH:MM:SS)

3. **recurrence_days** (string pipe-separated): Giorni della settimana (solo per `weekly`)
   - Valori: `monday`, `tuesday`, `wednesday`, `thursday`, `friday`, `saturday`, `sunday`
   - Formato: `day|day|day`
   - Esempio: `monday|wednesday|friday`
   - Note: minuscolo, nomi inglesi

4. **recurrence_start_date** (string): Data inizio validità ricorrenza
   - Formato: `YYYY-MM-DD`
   - Esempio: `2025-01-01`
   - Validazione: regex per formato ISO

5. **recurrence_end_date** (string): Data fine validità ricorrenza
   - Formato: `YYYY-MM-DD`
   - Esempio: `2025-12-31`
   - Validazione: regex per formato ISO

#### Campi di Disponibilità e Buffer
6. **buffer_before** (integer): Buffer prima dello slot in minuti
   - Esempio: `15`
   - Default: `0`
   - Uso: tempo di preparazione prima dello slot

7. **buffer_after** (integer): Buffer dopo lo slot in minuti
   - Esempio: `15`
   - Default: `0`
   - Uso: tempo di pulizia/preparazione dopo lo slot

8. **lead_time_hours** (integer): Ore di preavviso minimo per prenotare
   - Esempio: `24`
   - Default: `0`
   - Uso: impedisce prenotazioni dell'ultimo minuto

### Strutture Metadata Generate

#### _fp_exp_recurrence
```php
[
    'frequency' => 'weekly',
    'time_slots' => [
        ['time' => '09:00'],
        ['time' => '14:00'],
        ['time' => '16:00']
    ],
    'days' => ['monday', 'wednesday', 'friday'],
    'start_date' => '2025-01-01',
    'end_date' => '2025-12-31'
]
```

#### _fp_exp_availability
```php
[
    'slot_capacity' => 15,  // da capacity_slot esistente
    'buffer_before_minutes' => 15,
    'buffer_after_minutes' => 15,
    'lead_time_hours' => 24
]
```

### File Modificati

1. **src/Admin/ImporterPage.php**
   - Aggiunto `update_recurrence_meta()` - gestisce configurazione ricorrenza
   - Aggiunto `update_availability_meta()` - gestisce buffer e lead time
   - Aggiornato `generate_template_csv()` - include nuove colonne
   - Aggiornato `render_guide()` - documenta nuovi campi
   - Aggiunti use statements: `absint`, `sanitize_key`, `preg_match`

2. **templates/admin/csv-examples/esperienze-esempio.csv**
   - Aggiornato con esempi realistici per ogni esperienza
   - Include configurazioni diverse:
     - Tour giornalieri: `daily` con slot multipli ogni giorno
     - Tour settimanali: `weekly` con giorni specifici
     - Eventi stagionali: date inizio/fine configurate
     - Buffer variabili: 0-30 minuti a seconda del tipo esperienza
     - Lead time variabili: 12-48 ore

### Esempi Pratici

#### Tour Giornaliero
```csv
"Tour del Colosseo",publish,...,weekly,"09:00|14:00|16:00","monday|tuesday|wednesday|thursday|friday|saturday|sunday",2025-01-01,2025-12-31,15,15,24
```
- Disponibile tutti i giorni
- 3 slot al giorno (mattina, pomeriggio, sera)
- Buffer di 15 minuti prima e dopo
- Prenotazione con 24h di preavviso

#### Cooking Class Settimanale
```csv
"Cooking Class",publish,...,weekly,"18:00","tuesday|thursday|saturday",2025-01-15,2025-11-30,30,30,48
```
- Solo martedì, giovedì e sabato
- Un solo slot serale (18:00)
- Buffer di 30 minuti (tempo pulizia/preparazione)
- Prenotazione con 48h di preavviso

#### Evento Stagionale
```csv
"Tramonto al Gianicolo",publish,...,daily,"18:30",,,2025-04-01,2025-09-30,10,5,12
```
- Solo periodo estivo (aprile-settembre)
- Un solo slot al giorno (tramonto)
- Buffer brevi (tramonto rapido)
- Prenotazione con 12h di preavviso

### Compatibilità

- **Retrocompatibilità**: Campi opzionali, esperienze senza questi campi funzionano normalmente
- **Legacy support**: `capacity_slot` mappato sia su `_fp_capacity_slot` che su `_fp_exp_availability['slot_capacity']`
- **Lead time**: Salvato sia in `_fp_exp_availability['lead_time_hours']` che in `_fp_lead_time_hours` separato

### Validazione

Il sistema valida automaticamente:
- ✅ Formato orari (HH:MM o HH:MM:SS)
- ✅ Nomi giorni (solo nomi inglesi validi)
- ✅ Date (formato ISO YYYY-MM-DD)
- ✅ Frequenza (solo daily, weekly, custom)
- ✅ Valori numerici (absint per buffer e lead time)

Righe con dati invalidi non bloccano l'import, ma vengono logate come errori.

### Interfaccia Utente

La guida nell'importer è stata aggiornata con:
- ✨ Nuova sezione "Campi Calendario e Slot"
- 💡 Suggerimento per separatore pipe
- 📅 Box informativo su configurazione calendario

### Testing

Per testare l'import aggiornato:

1. Scarica il nuovo template CSV
2. Compila i nuovi campi per le tue esperienze
3. Carica il file
4. Verifica che i meta `_fp_exp_recurrence` e `_fp_exp_availability` siano stati salvati correttamente
5. Controlla nel calendario che gli slot virtuali vengano generati secondo la configurazione

### Note Implementative

- I campi sono tutti opzionali per non rompere import esistenti
- La validazione è permissiva: dati invalidi vengono ignorati, non bloccano l'import
- Il formato `time_slots` usa array di oggetti con chiave `time` per compatibilità con AvailabilityService
- I giorni sono normalizzati a minuscolo
- Date validate con regex semplice (formato base, non validazione semantica completa)

## Prossimi Passi

Possibili estensioni future:
- [ ] Supporto per custom slots (date+ora specifiche)
- [ ] Import di slot già persistiti in tabella wp_fp_exp_slots
- [ ] Configurazione capacity_per_type (capacità per tipo biglietto)
- [ ] Import di eccezioni/blackout dates
- [ ] Supporto price_rules per prezzi dinamici
- [ ] Validazione incrociata date (start < end)