# Fix per Errore Checkout "Risposta vuota dal server"

## Problema
Durante il processo di checkout WooCommerce, si verificavano gli errori seguenti nella console del browser:

```
[FP-EXP] Impossibile parsare risposta checkout: Error: Risposta vuota dal server
[FP-EXP] Errore checkout WooCommerce: Error: Risposta non valida dal server
```

## Cause Identificate
1. **Gestione risposta REST API**: La risposta REST API poteva essere vuota o non formattata correttamente come JSON
2. **Mancanza validazione ordine**: Non c'era validazione dell'oggetto ordine prima di accedere ai suoi metodi
3. **Gestione errori insufficiente**: Gli errori non venivano formattati correttamente per la REST API
4. **Logging insufficiente**: Mancavano log per debugging lato frontend

## Modifiche Implementate

### 1. Backend (src/Booking/Checkout.php)

#### Risposta REST API Esplicita
```php
// Prima
return rest_ensure_response($result);

// Dopo
$response = new WP_REST_Response($result, 200);
$response->set_headers([
    'Content-Type' => 'application/json; charset=utf-8',
]);
return $response;
```

#### Validazione Ordine
Aggiunta validazione dell'oggetto ordine prima di accedere ai metodi:
```php
if (! is_object($order) || ! method_exists($order, 'get_id') || ! method_exists($order, 'get_checkout_payment_url')) {
    $this->cart->unlock();
    return new WP_Error('fp_exp_checkout_invalid_order', __('Ordine non valido.', 'fp-experiences'), [
        'status' => 500,
    ]);
}
```

#### Validazione Dati Risposta
Verifica che `order_id` e `payment_url` siano validi prima di restituirli:
```php
if (! $order_id || ! $payment_url) {
    $this->cart->unlock();
    return new WP_Error('fp_exp_checkout_invalid_response', __('Impossibile generare URL di pagamento.', 'fp-experiences'), [
        'status' => 500,
    ]);
}
```

#### Gestione Errori REST API
Migliorata la formattazione delle risposte di errore:
```php
if ($result instanceof WP_Error) {
    $status = (int) ($result->get_error_data()['status'] ?? 400);
    $response = new WP_REST_Response([
        'code' => $result->get_error_code(),
        'message' => $result->get_error_message(),
        'data' => ['status' => $status],
    ], $status);
    $response->set_headers([
        'Content-Type' => 'application/json; charset=utf-8',
    ]);
    return $response;
}
```

### 2. Frontend (assets/js/front.js)

#### Logging Migliorato
Aggiunto logging dettagliato per debugging:
```javascript
const text = await checkoutResponse.text();
console.log('[FP-EXP] Risposta checkout ricevuta:', text ? text.substring(0, 200) : '(vuota)');

result = JSON.parse(text);
console.log('[FP-EXP] Risposta checkout parsata:', result);
```

#### Gestione Risposta Flessibile
Supporto per `payment_url` sia al livello root che dentro `data`:
```javascript
const paymentUrl = result.payment_url || (result.data && result.data.payment_url);

if (paymentUrl) {
    console.log('[FP-EXP] Reindirizzamento a:', paymentUrl);
    window.location.href = paymentUrl;
} else {
    console.error('[FP-EXP] Risposta completa:', result);
    throw new Error('URL di pagamento non ricevuto');
}
```

#### Validazione Risposta Vuota
Controllo esplicito per risposte vuote:
```javascript
if (!text || text.trim() === '') {
    throw new Error('Risposta vuota dal server');
}
```

## Testing
Per testare il fix:

1. Aprire il widget esperienze su una pagina
2. Selezionare data/orario e quantità
3. Cliccare "Procedi al pagamento"
4. Verificare nella console del browser:
   - Log `[FP-EXP] Risposta checkout ricevuta`
   - Log `[FP-EXP] Risposta checkout parsata`
   - Log `[FP-EXP] Reindirizzamento a:`
5. Verificare il reindirizzamento alla pagina di pagamento WooCommerce

## Messaggi di Errore
Se si verificano ancora errori, i nuovi log nella console mostreranno:
- Il contenuto della risposta ricevuta dal server
- La risposta parsata (se parsing riuscito)
- L'URL di reindirizzamento (se presente)
- La risposta completa in caso di URL mancante

Questo facilita l'identificazione della causa del problema.

## Build
Per applicare le modifiche:
```bash
npm run build
```

I file aggiornati sono:
- `src/Booking/Checkout.php`
- `assets/js/front.js`
- `build/fp-experiences/src/Booking/Checkout.php`
- `assets/js/dist/fp-experiences-frontend.min.js`

## Note
- Le modifiche sono retrocompatibili
- Non richiedono aggiornamenti del database
- Migliorano la robustezza e debuggability del sistema di checkout