# Test Piano per il Fix del Calendario

## Prerequisiti
- WordPress installato e funzionante
- Plugin FP Experiences attivo
- Almeno un'esperienza creata

## Test Case 1: Verifica Start Date

### Obiettivo
Verificare che il calendario mostri solo i giorni a partire dalla data di inizio configurata.

### Passi
1. Nel backend WordPress, vai a "Esperienze" > "Tutte le esperienze"
2. Seleziona o crea un'esperienza di test
3. Vai alla tab "Calendario & Slot"
4. Nella sezione "Ricorrenza slot":
   - **Data inizio**: Imposta a domani (es. 2025-10-08)
   - **Data fine**: Lascia vuoto
   - **Frequenza**: Settimanale
   - **Giorni attivi**: Seleziona Lunedì, Mercoledì, Venerdì
5. Nella sezione "Set di orari e capienza":
   - Clicca "Aggiungi orario"
   - Inserisci "09:00"
   - Clicca "Aggiungi orario" di nuovo
   - Inserisci "14:00"
   - **Capienza per slot**: 10
6. Salva l'esperienza
7. Crea una pagina di test con lo shortcode: `[fp_exp_calendar id="X"]` (sostituisci X con l'ID dell'esperienza)
8. Visualizza la pagina

### Risultato Atteso
- Il calendario dovrebbe mostrare solo i giorni a partire da domani
- I giorni di oggi e precedenti dovrebbero essere disabilitati (grigi)
- Solo Lunedì, Mercoledì e Venerdì dovrebbero essere disponibili (evidenziati)
- Cliccando su un giorno disponibile, dovrebbero apparire le fasce orarie 09:00 e 14:00

### Debug
Se il test fallisce, verifica i metadati dell'esperienza:
```php
// Aggiungi questo codice in un file PHP temporaneo o in functions.php
$experience_id = X; // Sostituisci con l'ID
$availability = get_post_meta($experience_id, '_fp_exp_availability', true);
var_dump($availability);
// Dovrebbe mostrare:
// ['start_date' => '2025-10-08', 'times' => ['09:00', '14:00'], ...]
```

---

## Test Case 2: Verifica End Date

### Obiettivo
Verificare che il calendario mostri solo i giorni fino alla data di fine configurata.

### Passi
1. Modifica l'esperienza del Test Case 1
2. Nella sezione "Ricorrenza slot":
   - **Data inizio**: Oggi (es. 2025-10-07)
   - **Data fine**: Tra 7 giorni (es. 2025-10-14)
3. Salva l'esperienza
4. Aggiorna la pagina di test

### Risultato Atteso
- Il calendario dovrebbe mostrare solo i giorni tra oggi e tra 7 giorni
- I giorni dopo la data di fine dovrebbero essere disabilitati
- Solo i giorni configurati (Lunedì, Mercoledì, Venerdì) all'interno del periodo dovrebbero essere disponibili

---

## Test Case 3: Verifica Performance

### Obiettivo
Verificare che il caricamento del calendario sia veloce.

### Passi
1. Apri la pagina di test con lo shortcode del calendario
2. Apri Developer Tools (F12)
3. Vai alla tab "Network"
4. Ricarica la pagina
5. Misura il tempo di caricamento della pagina

### Risultato Atteso
- Il caricamento dovrebbe essere significativamente più veloce rispetto a prima
- La risposta HTML dovrebbe essere generata in meno di 1-2 secondi (dipende dal server)
- Non dovrebbero esserci timeout o rallentamenti visibili

### Confronto
Se possibile, confronta il tempo di caricamento con la versione precedente:
- **Prima**: Caricava 2 mesi di slot
- **Dopo**: Carica 1 mese di slot
- **Aspettativa**: Riduzione del tempo di caricamento del ~50%

---

## Test Case 4: Verifica Slot Vuoti

### Obiettivo
Verificare che il calendario gestisca correttamente il caso di nessun slot configurato.

### Passi
1. Crea una nuova esperienza
2. Vai alla tab "Calendario & Slot"
3. Nella sezione "Ricorrenza slot":
   - **Data inizio**: Oggi
   - **Giorni attivi**: Seleziona alcuni giorni
   - **NON** aggiungere orari (lascia vuoto)
4. Salva l'esperienza
5. Crea una pagina con lo shortcode: `[fp_exp_calendar id="X"]`
6. Visualizza la pagina

### Risultato Atteso
- Il calendario dovrebbe essere visualizzato
- Tutti i giorni dovrebbero essere disabilitati (nessun giorno disponibile)
- Non dovrebbero esserci errori PHP o JavaScript nella console

---

## Test Case 5: Verifica Retrocompatibilità

### Obiettivo
Verificare che le esperienze create prima del fix continuino a funzionare.

### Passi
1. Trova un'esperienza creata prima di questo fix
2. **NON** modificare i dati
3. Visualizza il calendario per questa esperienza

### Risultato Atteso
- Il calendario dovrebbe funzionare come prima
- Se l'esperienza aveva slot configurati, dovrebbero essere visibili
- Non dovrebbero esserci errori

---

## Test Case 6: Verifica Timezone

### Obiettivo
Verificare che il calendario gestisca correttamente i timezone.

### Passi
1. Vai in WordPress > Impostazioni > Generali
2. Verifica che il "Fuso orario" sia impostato correttamente (es. Roma)
3. Modifica un'esperienza con:
   - **Data inizio**: Oggi alle 00:00
   - **Orario**: 09:00
4. Visualizza il calendario

### Risultato Atteso
- Gli slot dovrebbero apparire nell'orario locale configurato
- Non dovrebbero esserci discrepanze di orario tra backend e frontend

---

## Test Case 7: Verifica Navigazione Mesi

### Obiettivo
Verificare che la navigazione tra i mesi funzioni correttamente.

### Passi
1. Visualizza una pagina con il calendario
2. Se presente, clicca sul pulsante "Mese successivo" (›)
3. Verifica che il calendario si aggiorni

### Risultato Atteso
- Il calendario dovrebbe caricare il mese successivo
- I giorni disponibili dovrebbero essere aggiornati correttamente
- Il caricamento dovrebbe essere veloce

---

## Test Case 8: Verifica Limite Mesi

### Obiettivo
Verificare che il limite massimo di 3 mesi funzioni.

### Passi
1. Crea una pagina con lo shortcode: `[fp_exp_calendar id="X" months="5"]`
2. Visualizza la pagina

### Risultato Atteso
- Il calendario dovrebbe mostrare solo 1 mese (il default), non 5
- Non dovrebbero esserci errori

---

## Checklist Finale

Prima di considerare il fix completo, verifica che:

- [ ] Test Case 1: Start date funziona
- [ ] Test Case 2: End date funziona
- [ ] Test Case 3: Performance migliorata
- [ ] Test Case 4: Slot vuoti gestiti correttamente
- [ ] Test Case 5: Retrocompatibilità OK
- [ ] Test Case 6: Timezone corretto
- [ ] Test Case 7: Navigazione mesi OK
- [ ] Test Case 8: Limite mesi OK
- [ ] Non ci sono errori PHP nel log
- [ ] Non ci sono errori JavaScript nella console
- [ ] Il calendario si visualizza correttamente su desktop
- [ ] Il calendario si visualizza correttamente su mobile

---

## Debug Avanzato

### Verifica Metadati

Aggiungi questo snippet in un file PHP temporaneo per verificare i metadati:

```php
<?php
// Verifica metadati esperienza
$experience_id = 123; // Sostituisci con l'ID reale

echo "<h3>Availability</h3>";
$availability = get_post_meta($experience_id, '_fp_exp_availability', true);
echo '<pre>';
print_r($availability);
echo '</pre>';

echo "<h3>Recurrence</h3>";
$recurrence = get_post_meta($experience_id, '_fp_exp_recurrence', true);
echo '<pre>';
print_r($recurrence);
echo '</pre>';

// Test generazione slot
echo "<h3>Virtual Slots (prossimi 30 giorni)</h3>";
$start = date('Y-m-d');
$end = date('Y-m-d', strtotime('+30 days'));
$slots = \FP_Exp\Booking\AvailabilityService::get_virtual_slots($experience_id, $start, $end);
echo '<pre>';
print_r($slots);
echo '</pre>';
?>
```

### Verifica Query Performance

Se il caricamento è ancora lento, verifica le query con Query Monitor:

1. Installa il plugin "Query Monitor"
2. Attivalo
3. Visualizza la pagina con il calendario
4. Clicca su "Queries" nella toolbar di Query Monitor
5. Verifica:
   - Numero di query eseguite
   - Tempo totale delle query
   - Query duplicate o lente

---

## Risoluzione Problemi

### Problema: Il calendario non mostra giorni disponibili

**Possibili cause**:
1. La data di inizio è nel futuro (oltre il mese visualizzato)
2. Non ci sono orari configurati
3. I giorni della settimana selezionati non corrispondono al mese visualizzato
4. La capienza è 0

**Soluzione**:
Verifica i metadati con lo snippet di debug sopra e assicurati che:
- `availability['times']` non sia vuoto
- `availability['start_date']` sia valida
- `availability['days_of_week']` contenga giorni validi
- `availability['slot_capacity']` sia > 0

### Problema: Il caricamento è ancora lento

**Possibili cause**:
1. Il server è lento
2. Ci sono molti slot da generare (es. slot ogni 15 minuti per un mese)
3. Ci sono molte prenotazioni da contare

**Soluzione**:
1. Verifica con Query Monitor il tempo di esecuzione
2. Considera l'implementazione di una cache
3. Riduci il numero di slot (es. aumenta l'intervallo tra gli slot)

### Problema: Gli slot non rispettano il timezone

**Possibili cause**:
1. Il timezone di WordPress non è configurato correttamente
2. Il server ha un timezone diverso da quello configurato

**Soluzione**:
1. Vai in WordPress > Impostazioni > Generali
2. Imposta correttamente il "Fuso orario"
3. Verifica con `echo wp_timezone_string();` che il timezone sia corretto
