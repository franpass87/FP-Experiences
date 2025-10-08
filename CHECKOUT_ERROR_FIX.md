# Fix per Errore Checkout WooCommerce

## Problema
Quando si cliccava su "Procedi al pagamento", veniva generato questo errore in console:

```
[FP-EXP] Errore checkout WooCommerce: SyntaxError: Failed to execute 'json' on 'Response': Unexpected end of JSON input
```

## Causa
Il problema era causato da una gestione inadeguata delle risposte HTTP vuote o malformate dal server durante il processo di checkout. Quando gli endpoint REST API `/wp-json/fp-exp/v1/cart/set` o `/wp-json/fp-exp/v1/checkout` restituivano risposte vuote o non valide (ad esempio in caso di errori di autenticazione o permessi), il codice JavaScript tentava di parsare la risposta come JSON senza verificare prima che ci fosse effettivamente del contenuto.

## Soluzione Implementata

### Modifiche a `assets/js/front.js`

1. **Gestione errori per `/cart/set` (righe 742-751)**:
   - Aggiunto controllo per risposta vuota usando `.text()` invece di `.json()`
   - Parsing sicuro del JSON solo se c'è contenuto
   - Messaggio di errore più descrittivo che include lo status code HTTP

2. **Gestione errori per `/checkout` (righe 782-810)**:
   - Stesso approccio per le risposte di errore (non-ok)
   - Gestione sicura della risposta di successo con verifica del contenuto
   - Errori più informativi per debugging

### Codice Migliorato

**Prima:**
```javascript
const result = await checkoutResponse.json(); // ❌ Fallisce con risposta vuota
```

**Dopo:**
```javascript
let result = {};
try {
    const text = await checkoutResponse.text();
    if (!text) {
        throw new Error('Risposta vuota dal server');
    }
    result = JSON.parse(text);
} catch (e) {
    console.error('[FP-EXP] Impossibile parsare risposta checkout:', e);
    throw new Error('Risposta non valida dal server');
}
```

## Benefici

1. ✅ **Nessun crash JavaScript**: L'errore viene gestito correttamente
2. ✅ **Messaggi di errore chiari**: L'utente vede un messaggio comprensibile invece di un errore tecnico
3. ✅ **Logging migliorato**: Gli sviluppatori possono identificare meglio i problemi
4. ✅ **Esperienza utente migliorata**: Il pulsante torna disponibile dopo 3 secondi con la possibilità di riprovare

## Problemi Correlati che Potrebbero Richiedere Attenzione

### 1. Autenticazione Nonce
Se continui a ricevere risposte vuote, potrebbe essere un problema di autenticazione. Verifica che:

- `fpExpConfig.restNonce` sia correttamente inizializzato (nonce `wp_rest`)
- `fpExpConfig.checkoutNonce` sia correttamente inizializzato (nonce `fp-exp-checkout`)

Controlla nel sorgente HTML della pagina:
```javascript
console.log('restNonce:', fpExpConfig.restNonce);
console.log('checkoutNonce:', fpExpConfig.checkoutNonce);
```

### 2. Permessi REST API
Il file `docs/AUDIT_PLUGIN.json` documenta un problema noto con i permessi REST. Se il problema persiste, verifica che la funzione `check_checkout_permission` in `src/Booking/Checkout.php` stia funzionando correttamente.

### 3. Debug del Server
Per capire perché il server restituisce risposte vuote, puoi:

1. Abilitare il debug di WordPress in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. Controllare i log del server per errori PHP
3. Usare gli strumenti di sviluppo del browser (Network tab) per vedere la risposta completa

## Testing

Dopo l'applicazione della fix:

1. ✅ Seleziona data e orario per un'esperienza
2. ✅ Scegli i biglietti
3. ✅ Clicca "Procedi al pagamento"
4. ✅ Verifica che il messaggio di errore (se presente) sia chiaro e descrittivo
5. ✅ Verifica che il pulsante si riabiliti dopo l'errore

## File Modificati

- `/workspace/assets/js/front.js` (righe 742-810)
- `/workspace/build/fp-experiences/assets/js/front.js` (sincronizzato automaticamente)

## Build

I file sono stati compilati e sincronizzati con successo:
```bash
npm run build
bash sync-build.sh
```