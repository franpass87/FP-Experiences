# Fix: Ultimo giorno del calendario non disponibile

## Problema
L'ultimo giorno del calendario risultava non disponibile (disabled) anche quando ci erano slot configurati per quel giorno.

## Causa
Il bug era causato da un problema di gestione dei timezone in `AvailabilityService::get_virtual_slots()`:

1. Quando il timezone locale è dietro UTC (es. America/Los_Angeles, America/New_York), gli slot vicini alla fine della giornata vengono convertiti dal timezone locale a UTC, spostandosi al giorno successivo.

2. Esempio pratico:
   - Timezone: America/Los_Angeles (UTC-8)
   - Slot locale: 31 ottobre 2024, 22:00 PST
   - Conversione UTC: 1 novembre 2024, 06:00 UTC

3. Il codice originale filtrava gli slot con il check:
   ```php
   if ($end < $range_start || $start > $range_end) {
       continue;
   }
   ```
   Dove `$range_end` era impostato a `2024-10-31 23:59:59 UTC`.

4. Lo slot con start time `2024-11-01 06:00:00 UTC` veniva quindi escluso perché `$start > $range_end`, anche se apparteneva effettivamente al 31 ottobre nel timezone locale.

## Soluzione
Il fix è stato applicato in due fasi:

### 1. Estensione del range_end
Nei metodi `get_virtual_slots()` e `get_virtual_slots_legacy()`:

```php
// Prima (BUG):
$range_end = new DateTimeImmutable($end_utc . ' 23:59:59', new DateTimeZone('UTC'));

// Dopo (FIX):
$range_end = new DateTimeImmutable($end_utc . ' 23:59:59', new DateTimeZone('UTC'));
$range_end = $range_end->add(new DateInterval('P1D')); // +1 giorno
```

Questo permette di catturare tutti gli slot che, quando convertiti da locale a UTC, potrebbero spostarsi al giorno successivo.

### 2. Filtro basato sulla data locale
Dopo aver generato tutti gli slot, viene applicato un filtro che verifica la data nel timezone locale:

```php
foreach ($occurrences as [$start, $end]) {
    // Verifica che lo slot appartenga al range originale nel timezone locale
    $start_local = $start->setTimezone($tz);
    $start_date_local = $start_local->format('Y-m-d');
    
    // Salta gli slot che cadono dopo la data finale richiesta nel timezone locale
    if ($start_date_local > $end_utc) {
        continue;
    }
    
    // ... resto del codice ...
}
```

Questo assicura che:
- Gli slot dell'ultimo giorno del mese vengano inclusi anche se shiftati in UTC
- Gli slot del mese successivo vengano esclusi correttamente

## File modificati
- `src/Booking/AvailabilityService.php`:
  - Righe 158-161: Estensione range_end in `get_virtual_slots()`
  - Righe 287-298: Filtro basato su data locale in `get_virtual_slots()`
  - Righe 364-367: Estensione range_end in `get_virtual_slots_legacy()`
  - Righe 490-497: Filtro basato su data locale in `get_virtual_slots_legacy()`

## Test
È stato creato un test in `tests/Booking/AvailabilityServiceTest.php` che verifica:
1. Gli slot dell'ultimo giorno del mese sono inclusi con timezone dietro UTC
2. Gli slot del mese successivo non vengono inclusi erroneamente

## Impatto
- ✅ Nessun breaking change
- ✅ Compatibilità retroattiva mantenuta
- ✅ Funziona con tutti i timezone (davanti e dietro UTC)
- ✅ Nessun impatto sulle performance (O(n) come prima)

## Verifica
Per verificare che il fix funzioni:

1. Imposta il timezone del sito a uno dietro UTC (es. America/Los_Angeles)
2. Crea un'esperienza con ricorrenza settimanale e slot alle 22:00 o 23:00
3. Visualizza il calendario per un mese
4. Verifica che l'ultimo giorno del mese mostri gli slot disponibili

## Data
8 ottobre 2025