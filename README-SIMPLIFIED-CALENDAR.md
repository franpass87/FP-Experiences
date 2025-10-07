# Sistema Calendario Semplificato

## Panoramica

Il sistema di gestione calendario è stato semplificato per renderlo più intuitivo e facile da usare. La versione complessa precedente è stata archiviata in `/legacy/`.

## Modifiche Principali

### 1. Eliminata gestione date inizio/fine
- **Prima**: Era necessario impostare una data di inizio e una data di fine per ogni ricorrenza
- **Ora**: Il sistema genera automaticamente slot per i prossimi 12 mesi, sempre attivo

### 2. Solo giorni della settimana
- **Prima**: Supportava ricorrenze giornaliere, settimanali e specifiche
- **Ora**: Solo ricorrenza settimanale - selezioni i giorni della settimana (Lunedì-Domenica)

### 3. Slot orari semplificati
- **Prima**: Sistema complesso di "time sets" con label, giorni multipli, ecc.
- **Ora**: Slot orari semplici con override opzionali per:
  - Capacità (sovrascrive la capacità generale)
  - Buffer prima (sovrascrive il buffer generale prima)
  - Buffer dopo (sovrascrive il buffer generale dopo)

## Struttura Dati

### Formato Recurrence Semplificato

```php
[
    'frequency' => 'weekly',           // Sempre weekly
    'duration' => 60,                  // Durata predefinita in minuti
    'days' => ['monday', 'wednesday', 'friday'], // Giorni attivi
    'time_slots' => [
        [
            'time' => '10:00',         // Orario slot
            'capacity' => 0,           // 0 = usa capacità generale
            'buffer_before' => 0,      // 0 = usa buffer generale
            'buffer_after' => 0,       // 0 = usa buffer generale
            'days' => []               // Opzionale: override giorni per questo slot
        ],
        [
            'time' => '14:00',
            'capacity' => 8,           // Override: max 8 persone per questo slot
            'buffer_before' => 30,     // Override: 30 min buffer prima
            'buffer_after' => 15,      // Override: 15 min buffer dopo
            'days' => []
        ]
    ]
]
```

### Interfaccia Admin

La pagina "Calendario & Slot" ora mostra:

1. **Impostazioni generali**
   - Capacità generale (usata come default per tutti gli slot)
   - Preavviso minimo (ore)
   - Buffer generale prima (minuti)
   - Buffer generale dopo (minuti)

2. **Giorni della settimana**
   - Checkboxes semplici per Lun-Dom
   - Seleziona i giorni in cui l'esperienza è disponibile

3. **Slot orari**
   - Durata predefinita slot (minuti)
   - Lista di slot orari con:
     - Campo time per l'orario (es. 10:00)
     - Campi opzionali per override capacità e buffer

## File Modificati

### `/src/Booking/Recurrence.php`
- Semplificato per gestire solo weekly frequency
- Rimossi parametri start_date/end_date
- Nuovo formato time_slots invece di time_sets

### `/src/Admin/ExperienceMetaBoxes.php`
- `render_calendar_tab()`: Interfaccia semplificata
- `render_simple_time_slot_row()`: Nuova funzione per render slot singolo
- `save_availability_meta()`: Salvataggio semplificato

### `/src/Booking/Slots.php`
- Compatibile con il nuovo formato tramite `Recurrence::build_rules()`

## Migrazione

I dati esistenti vengono mantenuti. Il sistema è retrocompatibile:
- I vecchi `time_sets` vengono convertiti in `time_slots` al salvataggio
- La funzione `Recurrence::build_rules()` converte il nuovo formato nel formato atteso da `Slots::generate_recurring_slots()`

## File Legacy

La versione complessa precedente è stata salvata in:
- `/legacy/Recurrence.php.bak` - Sistema complesso originale

## Vantaggi

1. **Più semplice**: Meno campi da compilare, interfaccia più pulita
2. **Più veloce**: Meno click per configurare la disponibilità
3. **Meno errori**: Meno parametri = meno possibilità di configurazioni errate
4. **Sempre attivo**: Non serve gestire manualmente le date, il sistema genera sempre 12 mesi avanti

## Front-end

Il front-end rimane invariato. Continua a funzionare con gli slot generati nel database dalla tabella `wp_fp_exp_slots`.
