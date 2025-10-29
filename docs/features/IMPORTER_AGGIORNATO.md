# ✅ Importer Aggiornato con Supporto Calendario e Slot

## Riepilogo Modifiche

L'importer CSV è stato **completamente aggiornato** per supportare la configurazione di calendario, slot e ricorrenze in linea con le modifiche al backend.

## 🎯 Cosa È Stato Fatto

### 1. Nuovi Campi CSV (8 campi aggiunti)

#### Ricorrenza
- ✅ `recurrence_frequency` - daily/weekly/custom
- ✅ `recurrence_times` - orari slot separati da pipe (es: `09:00|14:00|16:00`)
- ✅ `recurrence_days` - giorni settimana (es: `monday|wednesday|friday`)
- ✅ `recurrence_start_date` - data inizio validità (YYYY-MM-DD)
- ✅ `recurrence_end_date` - data fine validità (YYYY-MM-DD)

#### Disponibilità e Buffer
- ✅ `buffer_before` - minuti di buffer prima dello slot
- ✅ `buffer_after` - minuti di buffer dopo lo slot
- ✅ `lead_time_hours` - ore di preavviso minimo

### 2. Codice Modificato

#### `src/Admin/ImporterPage.php`
- ✅ Aggiunto metodo `update_recurrence_meta()` - crea struttura `_fp_exp_recurrence`
- ✅ Aggiunto metodo `update_availability_meta()` - crea struttura `_fp_exp_availability`
- ✅ Aggiornato `generate_template_csv()` - include nuove colonne
- ✅ Aggiornato `render_guide()` - documenta nuovi campi nell'interfaccia
- ✅ Aggiunti use statements: `absint`, `sanitize_key`, `preg_match`
- ✅ Validazione automatica:
  - Orari (formato HH:MM)
  - Giorni (nomi inglesi validi)
  - Date (formato ISO YYYY-MM-DD)
  - Frequenza (daily/weekly/custom)

#### `templates/admin/csv-examples/esperienze-esempio.csv`
- ✅ Aggiornato con 8 nuove colonne
- ✅ 6 esempi realistici con configurazioni diverse:
  - Tour giornaliero (3 slot/giorno, 7 giorni/settimana)
  - Cooking class settimanale (solo 3 giorni)
  - Bike tour weekend (sabato/domenica + mercoledì)
  - Wine tasting (venerdì/sabato sera)
  - Tramonto stagionale (solo periodo estivo)
  - Street art tour draft (2 slot weekend)

### 3. Documentazione

#### `docs/IMPORTER_CALENDAR_UPDATE.md` (NUOVO)
- ✅ Documento tecnico completo con:
  - Specifiche campi
  - Strutture metadata generate
  - Esempi pratici
  - Note implementative
  - Validazione e compatibilità

#### `docs/admin/IMPORTER-COMPLETO.md`
- ✅ Aggiunta sezione "Campi Calendario e Slot"
- ✅ Aggiunta sezione "Campi Disponibilità e Buffer"
- ✅ 3 nuovi esempi CSV completi con risultati attesi
- ✅ 5 nuove FAQ specifiche su calendario e slot
- ✅ Checklist aggiornata con validazione campi calendario
- ✅ Sezione limitazioni aggiornata (calendario NON più limitazione!)
- ✅ Workflow aggiornato (calendario si configura nell'import!)

#### `docs/CHANGELOG.md`
- ✅ Nuova versione 0.3.5 con changelog dettagliato
- ✅ Esempi d'uso
- ✅ Lista file modificati

## 📊 Strutture Metadata Generate

### `_fp_exp_recurrence`
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

### `_fp_exp_availability`
```php
[
    'slot_capacity' => 15,
    'buffer_before_minutes' => 15,
    'buffer_after_minutes' => 15,
    'lead_time_hours' => 24
]
```

## 🎨 Interfaccia Utente

Nell'importer page sono stati aggiunti:
- 📅 Nuova sezione "Campi Calendario e Slot" nella guida
- 💡 Box informativo sui separatori pipe
- 📅 Box informativo blu sulla configurazione calendario

## ✅ Validazione Automatica

Il sistema valida:
- ✅ Formato orari: `HH:MM` o `HH:MM:SS`
- ✅ Giorni settimana: solo nomi inglesi validi (monday-sunday)
- ✅ Date: formato ISO `YYYY-MM-DD`
- ✅ Frequenza: solo `daily`, `weekly`, `custom`
- ✅ Valori numerici: conversione automatica con `absint`

Dati invalidi vengono **ignorati** (non bloccano l'import).

## 🔄 Compatibilità

- ✅ **Retrocompatibilità totale**: tutti i campi sono opzionali
- ✅ **Legacy support**: `capacity_slot` salvato in entrambi i formati
- ✅ **Lead time**: salvato sia in `_fp_exp_availability` che in `_fp_lead_time_hours`
- ✅ **Formato time_slots**: usa struttura array di oggetti con chiave `time`

## 📝 Esempi Pratici

### Tour Giornaliero (tutti i giorni, 3 slot)
```csv
recurrence_frequency,recurrence_times,recurrence_days,recurrence_start_date,recurrence_end_date,buffer_before,buffer_after,lead_time_hours
weekly,"09:00|14:00|16:00","monday|tuesday|wednesday|thursday|friday|saturday|sunday",2025-01-01,2025-12-31,15,15,24
```

### Cooking Class Settimanale (3 giorni, 1 slot serale)
```csv
recurrence_frequency,recurrence_times,recurrence_days,buffer_before,buffer_after,lead_time_hours
weekly,"18:00","tuesday|thursday|saturday",30,30,48
```

### Evento Stagionale (ogni giorno, solo estate)
```csv
recurrence_frequency,recurrence_times,recurrence_start_date,recurrence_end_date,lead_time_hours
daily,"18:30",2025-04-01,2025-09-30,12
```

## 🎯 Risultato Finale

### Prima dell'aggiornamento
- ❌ Calendario non supportato nell'importer
- ❌ Configurazione manuale post-import necessaria
- ❌ Slot non disponibili fino a configurazione manuale

### Dopo l'aggiornamento
- ✅ Calendario completamente configurabile via CSV
- ✅ Esperienze importate **pronte per prenotazioni**
- ✅ Slot virtuali generati automaticamente
- ✅ Nessuna configurazione post-import necessaria (calendari standard)

## 📦 File Modificati

1. ✅ `src/Admin/ImporterPage.php` - Logica import
2. ✅ `templates/admin/csv-examples/esperienze-esempio.csv` - Esempi
3. ✅ `docs/IMPORTER_CALENDAR_UPDATE.md` - Doc tecnica (nuovo)
4. ✅ `docs/admin/IMPORTER-COMPLETO.md` - Guida utente
5. ✅ `docs/CHANGELOG.md` - Changelog versione 0.3.5

## ✨ Testing

Per testare:
1. Vai su **FP Experiences → Importer Esperienze**
2. Scarica il nuovo template CSV
3. Compila i campi (inclusi quelli di calendario)
4. Importa il CSV
5. Verifica esperienza creata
6. Vai nel calendario e controlla che gli slot siano generati correttamente

## 🎉 Conclusione

L'importer è ora **completamente allineato** con il backend di calendario e slot. Le esperienze importate sono pronte per ricevere prenotazioni immediatamente, senza necessità di configurazione manuale aggiuntiva per calendari standard.

---

**Data aggiornamento**: 2025-10-08  
**Versione**: 0.3.5  
**Compatibilità**: Retrocompatibile al 100%  
**Linting**: ✅ Nessun errore