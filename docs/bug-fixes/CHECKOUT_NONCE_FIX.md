# Fix Errore Checkout - Risposta Vuota dal Server

## Problema
Quando si cliccava su "Procedi al pagamento", si verificavano i seguenti errori nella console del browser:

```
[FP-EXP] Risposta checkout ricevuta: (vuota)
[FP-EXP] Impossibile parsare risposta checkout: Error: Risposta vuota dal server
[FP-EXP] Errore checkout WooCommerce: Error: Risposta non valida dal server
```

## Causa Radice
Il problema era causato da un conflitto nella verifica dei nonce REST API:

1. Il codice JavaScript inviava **due nonce diversi**:
   - Header `X-WP-Nonce`: contenente il nonce `wp_rest`
   - Parametro `nonce` nel body: contenente il nonce `fp-exp-checkout`

2. Il backend verificava il nonce con la funzione `Helpers::verify_rest_nonce($request, 'fp-exp-checkout')` che:
   - Prima controlla il header `X-WP-Nonce` 
   - Prova a verificarlo con l'action `fp-exp-checkout`
   - **Fallisce** perché il nonce nell'header era creato con l'action `wp_rest` diversa

3. Anche se la funzione dovrebbe poi verificare il nonce nel body, WordPress REST API potrebbe bloccare la richiesta prima che il callback venga eseguito, restituendo una risposta vuota o un errore 403.

4. Il risultato era una risposta vuota dal server, causando gli errori JavaScript.

## Soluzione Implementata
Ho rimosso l'header `X-WP-Nonce` dalla richiesta fetch, lasciando solo il nonce corretto nel body:

### Prima (Errore)
```javascript
const checkoutResponse = await fetch(checkoutUrl, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': fpExpConfig.restNonce  // ❌ Nonce wp_rest
    },
    body: JSON.stringify({
        nonce: fpExpConfig.checkoutNonce,     // ✅ Nonce fp-exp-checkout
        // ... altri dati
    })
});
```

### Dopo (Corretto)
```javascript
const checkoutResponse = await fetch(checkoutUrl, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
        // ✅ Nessun header X-WP-Nonce
    },
    credentials: 'same-origin',              // ✅ Mantiene le credenziali
    body: JSON.stringify({
        nonce: fpExpConfig.checkoutNonce,     // ✅ Nonce fp-exp-checkout
        // ... altri dati
    })
});
```

## Perché Funziona
1. Senza l'header `X-WP-Nonce`, la funzione `verify_rest_nonce` salta la verifica dell'header
2. Passa direttamente alla verifica del parametro `nonce` nel body
3. Il nonce `fp-exp-checkout` viene verificato correttamente con l'action `fp-exp-checkout`
4. Se la verifica del nonce specifico fallisce, viene chiamata `verify_public_rest_request` che:
   - Verifica il referer per confermare che la richiesta proviene dallo stesso sito
   - Accetta richieste con `credentials: 'same-origin'`

## File Modificati
- ✅ `/workspace/assets/js/front.js` (righe 758-764)
- ✅ `/workspace/build/fp-experiences/assets/js/front.js` (righe 758-764)

## Come Testare
1. Aprire una pagina con il widget esperienze
2. Selezionare data, orario e numero di biglietti
3. Cliccare su "Procedi al pagamento"
4. Verificare nella console del browser:
   - ✅ Nessun errore `Risposta vuota dal server`
   - ✅ Log `[FP-EXP] Risposta checkout ricevuta: {...}`
   - ✅ Log `[FP-EXP] Risposta checkout parsata: {...}`
   - ✅ Log `[FP-EXP] Reindirizzamento a: [URL pagamento]`
5. Verificare il reindirizzamento alla pagina di pagamento WooCommerce

## Note Tecniche
### Sicurezza
La rimozione dell'header `X-WP-Nonce` **non compromette la sicurezza** perché:
- Il nonce `fp-exp-checkout` nel body viene comunque verificato
- La richiesta include `credentials: 'same-origin'` per l'autenticazione
- Il referer viene verificato da `verify_public_rest_request`
- Il rate limiting è attivo (`checkout_` + fingerprint)

### Riferimento Issue
Questo fix risolve parzialmente **ISSUE-001** dal documento `/workspace/docs/AUDIT_PLUGIN.json`:
- **Categoria**: bug, rest, nonce
- **Severità**: high
- **Diagnosis**: "REST permission callbacks expect nonces for actions fp-exp-checkout/fp-exp-rtb while the front-end only emits wp_rest nonces."

## Documentazione Correlata
- `/workspace/CHECKOUT_PAYMENT_FIX.md` - Fix precedente per gestione errori
- `/workspace/CHECKOUT_PAYMENT_FIX_SUMMARY.md` - Riepilogo fix precedente
- `/workspace/docs/AUDIT_PLUGIN.json` - Audit completo del plugin (ISSUE-001)
- `/workspace/CHECKOUT_ERROR_FIX.md` - Fix iniziale per parsing JSON

## Data
**2025-10-08** - Fix implementato da Background Agent