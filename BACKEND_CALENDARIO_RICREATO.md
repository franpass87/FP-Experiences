# Backend Calendario Ricreato - Riepilogo

## 🎯 Problema Risolto

Il calendario continuava a mostrare **zero disponibilità** a causa di:
1. Logica complessa e fragile nel backend
2. Dati non correttamente sincronizzati tra vecchio e nuovo formato
3. Mancanza di logging dettagliato per debugging

## ✨ Soluzione Implementata

### 1. **AvailabilityService.php** - Completamente Ricreato

**File**: `/workspace/src/Booking/AvailabilityService.php`

**Miglioramenti**:
- ✅ Logica **semplificata e robusta** 
- ✅ Supporto **dual-format**: legge sia `_fp_exp_recurrence` (nuovo) che `_fp_exp_availability` (legacy)
- ✅ **Logging dettagliato** per ogni passo (attivato se `WP_DEBUG` è true)
- ✅ **Gestione errori** migliorata con try-catch su ogni punto critico
- ✅ Codice ben **documentato** con PHPDoc

**Come funziona ora**:
```
1. Legge configurazione da _fp_exp_recurrence (nuovo formato)
2. Fallback automatico a _fp_exp_availability (vecchio formato)
3. Genera occorrenze basate su frequenza (daily/weekly/custom)
4. Applica filtri: lead_time, date limits, buffer
5. Calcola capacità rimanente per ogni slot
6. Ritorna slot virtuali pronti per il calendario
```

### 2. **Tool di Sincronizzazione Dati**

**Aggiunto nell'admin**: `FP Experiences → Impostazioni → Tools`

**Funzionalità**:
- 🔄 Migra automaticamente dal vecchio al nuovo formato
- 📊 Verifica tutte le esperienze
- ✅ Mostra report dettagliato
- 🚀 Eseguibile con un click

**Endpoint REST**: `POST /wp-json/fp-exp/v1/tools/sync-availability`

### 3. **Endpoint REST Aggiornato**

**File**: `/workspace/src/Api/RestRoutes.php`

**Nuovo metodo**: `tool_sync_availability()`
- Sincronizza tutte le esperienze in un colpo solo
- Statistiche dettagliate: migrati, già aggiornati, errori
- Rate limiting per evitare abusi
- Logging completo

### 4. **Script CLI Standalone** (Opzionale)

**File**: `/workspace/sync-availability-data.php`

Può essere eseguito via:
- **WP-CLI**: `wp eval-file sync-availability-data.php`
- **PHP CLI**: `php sync-availability-data.php` (se eseguito da root WordPress)
- **Browser**: Carica il file e aprilo (modalità web)

## 📋 Come Usare

### Passo 1: Sincronizza i Dati

1. Vai su **WordPress Admin → FP Experiences → Impostazioni**
2. Clicca sulla tab **"Tools"**
3. Trova la card **"Sync availability data"**
4. Clicca su **"Run sync"**
5. Attendi il completamento e leggi il report

### Passo 2: Verifica il Calendario

1. Vai su **FP Experiences → Operazioni → Calendar**
2. Seleziona un'esperienza dal filtro
3. Dovresti vedere la disponibilità correttamente visualizzata

### Passo 3: Debug (Se Necessario)

Se continui a vedere zero disponibilità:

1. **Abilita WP_DEBUG** in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. **Controlla i log** in `/wp-content/debug.log`:
   Cerca linee che iniziano con `[FP_EXP AvailabilityService]`

3. **Verifica i meta** dell'esperienza:
   - Vai sull'esperienza in WordPress admin
   - Controlla che ci siano **orari configurati**
   - Controlla che ci siano **giorni selezionati** (per weekly)
   - Controlla che la **capacità** sia > 0

## 🔍 Struttura Dati

### Nuovo Formato Unificato: `_fp_exp_recurrence`

```php
[
    'frequency' => 'weekly', // 'daily', 'weekly', 'custom'
    'start_date' => '2025-01-01',
    'end_date' => '2025-12-31',
    'days' => ['monday', 'wednesday', 'friday'],
    'time_sets' => [
        [
            'times' => ['09:00', '14:00', '16:00'],
            'days' => ['monday', 'wednesday', 'friday'],
            'capacity' => 10,
            'buffer_before' => 30,
            'buffer_after' => 15,
        ]
    ]
]
```

### Vecchio Formato Legacy: `_fp_exp_availability`

```php
[
    'frequency' => 'weekly',
    'times' => ['09:00', '14:00', '16:00'],
    'days_of_week' => ['monday', 'wednesday', 'friday'],
    'slot_capacity' => 10,
    'lead_time_hours' => 2,
    'buffer_before_minutes' => 30,
    'buffer_after_minutes' => 15,
    'start_date' => '2025-01-01',
    'end_date' => '2025-12-31',
]
```

## 📊 Diagnostica

### Verifica Manualmente i Dati

```php
// In WordPress console o script
$exp_id = 123; // ID esperienza

// Controlla nuovo formato
$recurrence = get_post_meta($exp_id, '_fp_exp_recurrence', true);
var_dump($recurrence);

// Controlla vecchio formato
$availability = get_post_meta($exp_id, '_fp_exp_availability', true);
var_dump($availability);

// Test API availability
$start = '2025-01-01';
$end = '2025-12-31';
$slots = \FP_Exp\Booking\AvailabilityService::get_virtual_slots($exp_id, $start, $end);
var_dump($slots);
```

### Endpoint API da Testare

```bash
# Test disponibilità esperienza
curl -X GET "https://tuo-sito.com/wp-json/fp-exp/v1/availability?experience=123&start=2025-01-01&end=2025-01-31"

# Test sincronizzazione (richiede autenticazione admin)
curl -X POST "https://tuo-sito.com/wp-json/fp-exp/v1/tools/sync-availability" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

## 🚀 Prossimi Passi

1. ✅ **Esegui la sincronizzazione** dal pannello Tools
2. ✅ **Verifica il calendario** con diverse esperienze
3. ✅ **Controlla i log** se hai problemi
4. ✅ **Configura gli orari** per le esperienze senza disponibilità

## 📝 File Modificati

1. **src/Booking/AvailabilityService.php** - Ricreato completamente
2. **src/Api/RestRoutes.php** - Aggiunto endpoint sync-availability
3. **src/Admin/SettingsPage.php** - Aggiunto tool nell'interfaccia
4. **sync-availability-data.php** - Script standalone per CLI

## 🎓 Documentazione Tecnica

### Flusso di Lettura Dati

```
get_virtual_slots()
  ↓
read_recurrence_config()
  ↓
parse_unified_format() OR parse_legacy_format()
  ↓
generate_occurrences()
  ↓
apply_lead_time()
  ↓
calculate_capacity()
  ↓
return slots[]
```

### Logging Dettagliato

Con `WP_DEBUG` abilitato, il servizio logga:
- ✅ Configurazione caricata
- ✅ Numero di occorrenze generate
- ✅ Filtri applicati (lead time, date limits)
- ✅ Numero finale di slot
- ✅ Errori e warning

## ✨ Benefici della Nuova Architettura

1. **Più Robusta**: Gestione errori migliore, nessun crash
2. **Più Veloce**: Logica semplificata, meno query
3. **Più Debuggabile**: Logging dettagliato integrato
4. **Più Mantenibile**: Codice ben documentato e organizzato
5. **Più Flessibile**: Supporto dual-format garantisce compatibilità

---

**Ultima modifica**: 7 Ottobre 2025  
**Versione**: 1.0.0
