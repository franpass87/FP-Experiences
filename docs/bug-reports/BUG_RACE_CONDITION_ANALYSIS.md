# Analisi Race Condition - Sistema di Booking

## 🔴 Bug Critico Identificato: Race Condition in Gestione Capacità Slot

### Descrizione del Problema

È stata identificata una **race condition critica** nel sistema di gestione delle prenotazioni che può causare **overbooking** in scenari di alta concorrenza.

### Localizzazione del Bug

**File:** `src/Booking/Slots.php`  
**Funzione:** `check_capacity()` (righe 721-774)  
**Chiamata da:**
- `src/Booking/Checkout.php` (riga 338)
- `src/Booking/RequestToBook.php` (riga 137)

### Flusso del Bug

```
Tempo  | Richiesta A                    | Richiesta B
-------|--------------------------------|--------------------------------
T0     | check_capacity(slot_id=1)      |
T1     | → legge snapshot: 8/10 posti   |
T2     |                                | check_capacity(slot_id=1)
T3     |                                | → legge snapshot: 8/10 posti
T4     | ✓ Verifica OK: 2 posti liberi  |
T5     |                                | ✓ Verifica OK: 2 posti liberi
T6     | create_order() → +2 prenotati  |
T7     |                                | create_order() → +2 prenotati
T8     | Totale prenotati: 10/10 ✓      | Totale prenotati: 12/10 ❌
```

**Risultato:** Overbooking di 2 posti!

### Codice Problematico

```php
// src/Booking/Slots.php:736
public static function check_capacity(int $slot_id, array $requested): array
{
    $slot = self::get_slot($slot_id);
    // ...
    
    // BUG: Lettura non atomica della capacità
    $snapshot = self::get_capacity_snapshot($slot_id);
    
    $capacity_total = absint($slot['capacity_total']);
    
    // BUG: Check senza locking - vulnerabile a race condition
    if ($capacity_total > 0 && ($snapshot['total'] + $requested_total) > $capacity_total) {
        return ['allowed' => false, ...];
    }
    
    return ['allowed' => true, ...]; // ← Può essere true per entrambe le richieste!
}
```

Tra il momento del check e la creazione della prenotazione, un'altra richiesta può eseguire lo stesso processo, causando il superamento del limite.

### Impatto

- **Severità:** 🔴 **CRITICA**
- **Probabilità:** Media-Alta in scenari di alta concorrenza
- **Conseguenze:**
  - Overbooking di slot
  - Violazione di limiti di capacità
  - Problemi operativi per l'organizzatore
  - Esperienza utente negativa

### Scenario di Trigger

Il bug si manifesta quando:
1. Due o più utenti tentano di prenotare lo stesso slot simultaneamente
2. Il numero totale di posti richiesti supera la capacità disponibile
3. Le richieste arrivano in una finestra temporale molto ristretta (< 100ms)

**Probabilità aumenta con:**
- Eventi popolari (alta domanda)
- Slot con posti limitati
- Traffico da campagne marketing
- Vendite flash / apertura prenotazioni

### Soluzioni Possibili

#### Soluzione 1: Database Row Locking (RACCOMANDATA) ✅

```php
public static function check_capacity(int $slot_id, array $requested): array
{
    global $wpdb;
    
    // Inizia transazione
    $wpdb->query('START TRANSACTION');
    
    // Lock della riga dello slot con FOR UPDATE
    $slot = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fp_exp_slots WHERE id = %d FOR UPDATE",
            $slot_id
        ),
        ARRAY_A
    );
    
    if (!$slot) {
        $wpdb->query('ROLLBACK');
        return ['allowed' => false, ...];
    }
    
    // Check capacità (ora atomico)
    $snapshot = self::get_capacity_snapshot($slot_id);
    
    if ($capacity_total > 0 && ($snapshot['total'] + $requested_total) > $capacity_total) {
        $wpdb->query('ROLLBACK');
        return ['allowed' => false, ...];
    }
    
    // La transazione verrà committata dopo la creazione della prenotazione
    return ['allowed' => true, ...];
}
```

**Pro:**
- Soluzione robusta e affidabile
- Previene completamente la race condition
- Standard per database transazionali

**Contro:**
- Richiede refactoring significativo
- Necessita gestione transazioni in tutto il flusso
- Potenziale overhead su performance

#### Soluzione 2: Optimistic Locking con Version Number

Aggiungere campo `version` alla tabella slots e incrementarlo ad ogni modifica:

```php
UPDATE wp_fp_exp_slots 
SET version = version + 1, ... 
WHERE id = ? AND version = ?
```

Se l'UPDATE non modifica righe (version cambiato), retry o errore.

#### Soluzione 3: Double-Check Pattern (SOLUZIONE RAPIDA) ⚡

```php
// Dopo aver creato la prenotazione
$reservation_id = Reservations::create([...]);

// Double-check immediato
$snapshot_after = Slots::get_capacity_snapshot($slot_id);
if ($snapshot_after['total'] > $capacity_total) {
    // Overbooking rilevato!
    Reservations::delete($reservation_id);
    return new WP_Error('fp_exp_capacity', 'Slot esaurito durante la prenotazione');
}
```

**Pro:**
- Implementazione semplice
- Non richiede modifiche al database
- Rileva e corregge l'overbooking

**Contro:**
- Non previene la race condition, solo la rileva
- Possibile esperienza negativa per utente "perdente"
- Non ideale per eventi ad altissima concorrenza

#### Soluzione 4: Application-Level Locking

Usare WordPress transient o external cache (Redis) per implementare lock distribuiti.

### Raccomandazione

Per un fix immediato: **Soluzione 3** (Double-Check Pattern)  
Per soluzione definitiva: **Soluzione 1** (Database Row Locking)

### Workaround Temporaneo

Fino all'implementazione di una soluzione:

1. **Monitoraggio:** Implementare logging per rilevare overbooking
2. **Margine di sicurezza:** Considerare di ridurre leggermente la capacità pubblicizzata
3. **Notifiche:** Alerting quando capacità supera il 100%
4. **Gestione manuale:** Procedura per gestire overbooking (upgrade, rimborso, etc.)

### Test Plan

Per verificare il bug:

```php
// Test di concorrenza simulata
for ($i = 0; $i < 10; $i++) {
    // Spawn 10 richieste simultanee per lo stesso slot
    // Ciascuna richiede 1 posto
    // Capacità slot: 5 posti
    // Risultato atteso: 5 prenotazioni create, 5 rigettate
    // Risultato effettivo: Possibile > 5 prenotazioni (overbooking)
}
```

### Codice Modificato

Nessuna modifica implementata in questa analisi. Il bug rimane presente nel codice.

### Nota Importante

⚠️ **Questo bug non è stato risolto in questa sessione.** Richiede decisione architetturale e testing approfondito prima dell'implementazione.

---

**Data:** 13 Ottobre 2025  
**Analista:** AI Code Analyzer  
**Priorità:** 🔴 CRITICA  
**Status:** IDENTIFICATO - NON RISOLTO  
**Ticket raccomandato:** Creare issue prioritario per implementazione fix
