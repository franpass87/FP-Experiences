# Funzionalità: Checkbox "Prezzo da..." per Tipologie Biglietto

## Descrizione

È stato implementato un checkbox per ogni tipologia di biglietto nel backend che permette di controllare quale prezzo viene usato come prezzo di partenza ("da...") nelle visualizzazioni pubbliche.

## Cosa è stato modificato

### 1. Backend - Metabox Esperienza

**File modificati:**
- `/src/Admin/ExperienceMetaBoxes.php`
- `/build/fp-experiences/src/Admin/ExperienceMetaBoxes.php`

**Modifiche:**
- Aggiunto un checkbox "Prezzo 'da...'" per ogni tipologia di biglietto nella tab "Biglietti & Prezzi"
- Il campo viene salvato come `use_as_price_from` nell'array del biglietto
- Se spuntato, quel biglietto verrà usato come prezzo di partenza

### 2. Logica di Calcolo Prezzo "da..."

**File modificati:**
- `/src/Shortcodes/SimpleArchiveShortcode.php`
- `/src/Shortcodes/ListShortcode.php`
- `/src/Shortcodes/ExperienceShortcode.php`
- `/src/Shortcodes/WidgetShortcode.php`
- `/templates/front/widget.php`
- Corrispondenti file in `/build/fp-experiences/`

**Nuova logica:**
1. **Prima verifica**: Cerca un biglietto con il checkbox "Prezzo 'da...'" spuntato
2. **Se trovato**: Usa quel prezzo come prezzo di partenza
3. **Altrimenti**: Usa il prezzo più basso tra tutte le tipologie di biglietto

## Come usare la funzionalità

### Nel Backend

1. Vai nella pagina di modifica di un'esperienza
2. Apri la tab "Biglietti & Prezzi"
3. Per ogni tipologia di biglietto vedrai ora un checkbox "Prezzo 'da...'"
4. Spunta il checkbox per la tipologia che vuoi usare come prezzo di partenza
5. Salva l'esperienza

**Nota:** È consigliato spuntare solo un checkbox per esperienza. Se ne spunti più di uno, verrà usato il primo trovato.

### Comportamento

**Esempio 1: Checkbox spuntato su "Adulto"**
- Adulto: €50 ✓ (checkbox spuntato)
- Bambino: €25
- Senior: €40

→ Prezzo mostrato: "Da €50"

**Esempio 2: Nessun checkbox spuntato**
- Adulto: €50
- Bambino: €25
- Senior: €40

→ Prezzo mostrato: "Da €25" (prezzo più basso)

**Esempio 3: Solo una tipologia**
- Adulto: €50

→ Prezzo mostrato: "Da €50"

## Vantaggi

1. **Controllo totale**: Puoi decidere esattamente quale prezzo mostrare come punto di partenza
2. **Flessibilità**: Non sei più limitato al prezzo bambino o al prezzo più basso
3. **Marketing**: Puoi scegliere il prezzo più rappresentativo della tua esperienza
4. **Backward compatible**: Se non spunti nessun checkbox, il sistema usa automaticamente il prezzo più basso

## Note Tecniche

- Il campo `use_as_price_from` è salvato sia nell'array `_fp_exp_pricing` che in `_fp_ticket_types`
- Il valore è un booleano (true/false)
- La cache dei prezzi viene gestita automaticamente dal sistema esistente
- Non richiede modifiche al database (usa i meta esistenti)

## Test Consigliati

1. Crea un'esperienza con più tipologie di biglietto
2. Spunta il checkbox su una tipologia specifica
3. Verifica che il prezzo "da..." corrisponda a quella tipologia
4. Deseleziona il checkbox e verifica che torni al prezzo più basso
5. Verifica anche nelle listing e negli archivi