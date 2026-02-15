# FP Experiences — Audit tracking GTM/GA4 (diffuso)

**Data:** 2026-02-15  
**Scope:** Tutti i punti in cui il plugin emette eventi dataLayer / GTM / GA4.

---

## 1. Architettura

- **Config frontend:** `fpExpConfig.tracking` (da `Helpers::tracking_config()`) con `enabled.ga4` che considera canale abilitato + consent (`Consent::granted(GA4)`).
- **Modulo JS:** `assets/js/front/tracking.js` — tutti i `dataLayer.push` passano da `window.FPFront.tracking`. I metodi fanno no-op se `!tracking.isEnabled()` (cioè `!fpExpConfig.tracking.enabled.ga4`).
- **Snippet GTM/GA4:** `Integrations\GA4::output_snippet()` in `wp_head` — inietta GTM (se `gtm_id`) o gtag GA4 (se `measurement_id`) solo se `Consent::granted(GA4)` e canale enabled + ID valorizzato.
- **Purchase (thank-you):** `GA4::render_purchase_event()` su `woocommerce_thankyou` — push evento `purchase` con ecommerce solo se consent GA4.

---

## 2. Mappa eventi dataLayer / GTM

| Evento | Dove | Quando | Consent check |
|--------|------|--------|----------------|
| **view_item_list** | `front.js` → `tracking.viewItemList()` | Listing caricata, da `data-fp-items` | ✅ JS `isEnabled()` |
| **select_item** | `front.js` → `tracking.selectItem()` | Click su card in listing | ✅ JS `isEnabled()` |
| **view_item** | Template `experience.php` inline script | Caricamento pagina singola esperienza | ❌ **Manca** — push sempre |
| **add_to_cart** | `front.js` dopo cart/set OK | Dopo aggiunta al carrello, prima redirect checkout | ✅ JS `isEnabled()` — ⚠️ **price/value assenti** |
| **begin_checkout** | `checkout.php` inline script | Caricamento pagina checkout FP | ✅ JS `isEnabled()` |
| **purchase** | `GA4::render_purchase_event()` | Thank-you WooCommerce | ✅ PHP `Consent::granted(GA4)` |
| **gift_purchase** | `front.js` dopo gift/purchase OK | Prima redirect a WooCommerce per voucher | ✅ JS `isEnabled()` — ⚠️ **value assente** |
| **fpExp.request_submit** | `front.js` → `tracking.rtbSubmit()` | Invio form RTB | ✅ JS `isEnabled()` (pushCustomEvent) |
| **fpExp.request_success** | `front.js` → `tracking.rtbSuccess()` | Risposta RTB OK | ✅ idem |
| **fpExp.request_error** | `front.js` → `tracking.rtbError()` | Errore RTB | ✅ idem |

---

## 3. File coinvolti (solo sorgente, esclusi build/dist)

| File | Ruolo |
|------|--------|
| `src/Integrations/GA4.php` | Snippet GTM/gtag in head; evento `purchase` in thank-you |
| `src/Integrations/GoogleAds.php` | Conversion gtag su thank-you (consent + conversion_id/label) |
| `src/Utils/Helpers.php` | `tracking_config()`, `tracking_settings()` |
| `src/Utils/Helpers/TrackingHelper.php` | `getSettings()`, getConfig (gtm_id/ga_id root-level, legacy) |
| `src/Utils/Consent.php` | `Consent::granted($channel)` + filter `fp_exp_tracking_consent` |
| `src/Shortcodes/Assets.php` | Inietta `fpExpConfig.tracking`; enqueue `tracking.js` prima di `front.js` |
| `src/Shortcodes/ListShortcode.php` | `build_tracking_items()` → `data-fp-items` per listing |
| `src/Shortcodes/ExperienceShortcode.php` | Costruisce `$data_layer` per `view_item` (passato al template) |
| `templates/front/experience.php` | Script inline che fa `dataLayer.push($data_layer)` — **senza check consent** |
| `templates/front/list.php` | Output `data-fp-items` per listing tracking |
| `templates/front/checkout.php` | Script inline `beginCheckout()` con items/value/currency |
| `assets/js/front/tracking.js` | Tutti i metodi `viewItemList`, `selectItem`, `addToCart`, `beginCheckout`, `giftPurchase`, `rtb*` |
| `assets/js/front.js` | Chiamate a `FPFront.tracking.*` (listing, add_to_cart, begin_checkout, RTB, gift) |
| `src/Admin/SettingsPage.php` | Tab Tracking, campi GA4 (gtm_id, measurement_id), consent_defaults, sanitize_tracking |

---

## 4. Problemi rilevati e correzioni

### 4.1 view_item (pagina esperienza) — consent

- **Problema:** In `experience.php` il push di `view_item` avviene sempre, senza verificare se il tracking GA4/GTM è abilitato e con consent.
- **Rischio:** In assenza di GTM/GA4 il push è innocuo; con GTM caricato da CMP dopo consent, l’evento sarebbe comunque inviato anche prima del consent se lo script fosse eseguito prima del CMP. Coerenza: tutti gli altri eventi passano da `tracking.isEnabled()`.
- **Correzione:** Eseguire il push solo se `window.FPFront && window.FPFront.tracking && window.FPFront.tracking.isEnabled()` (stesso criterio del resto del frontend).

### 4.2 add_to_cart — price / value

- **Problema:** In `front.js` la chiamata a `tracking.addToCart()` non passa `price` (né `value`). In `tracking.js` si usa `data.price != null ? Number(data.price) : 0`, quindi `value` risulta 0.
- **Correzione:** Calcolare il prezzo (es. da `config.priceFrom` del widget) e la quantity già usata, e passare `price` (e opzionalmente `value = price * quantity`) a `addToCart()`.

### 4.3 gift_purchase — value

- **Problema:** `tracking.giftPurchase()` viene chiamato senza `value`. In `tracking.js` si usa `data.value != null ? Number(data.value) : 0`, quindi value 0.
- **Correzione:** Passare `value` quando disponibile (es. da `result.value` / `result.data.total` dopo gift/purchase, o da `giftConfig.priceFrom * quantity` come fallback).

### 4.4 Filtro `fp_exp_datalayer_purchase`

- **Stato:** Citato in `readme.txt` come filtro per il payload purchase in dataLayer; **non risulta implementato** nel codice (nessun `apply_filters('fp_exp_datalayer_purchase', ...)`).
- **Suggerimento:** Se serve estensibilità sul payload `purchase`, aggiungere in `GA4::render_purchase_event()` un `apply_filters('fp_exp_datalayer_purchase', $payload)` prima del push.

---

## 5. Flusso consent e caricamento script

1. **PHP:** `Consent::granted(Consent::CHANNEL_GA4)` legge `fp_exp_tracking` (consent_defaults + filter `fp_exp_tracking_consent`). Se false, GA4 non registra hook → nessuno snippet in head, nessun purchase in thank-you.
2. **Frontend:** `fpExpConfig.tracking.enabled.ga4` è calcolato lato server con lo stesso consent. Quindi se il consent è false, `tracking.isEnabled()` è false e tutti i metodi in `tracking.js` non fanno push.
3. **Eccezione:** Solo `view_item` in `experience.php` era pushato senza guardare `isEnabled()`; con la correzione 4.1 anche questo è allineato.

---

## 6. Google Ads

- **File:** `src/Integrations/GoogleAds.php`
- **Comportamento:** Su `woocommerce_thankyou` (priority 25) inietta gtag e evento `conversion` con `send_to`, `value`, `currency` solo se `Consent::granted(Consent::CHANNEL_GOOGLE_ADS)` e conversion_id/label valorizzati.
- **Nota:** Non usa il payload ecommerce del dataLayer; invia una conversion dedicata. Coerente con GA4 (entrambi rispettano consent).

---

## 7. Checklist post-audit

- [x] Elencati tutti gli eventi dataLayer/GTM e i file coinvolti
- [x] Verificato consent su snippet GA4/GTM e su evento purchase
- [x] Verificato che gli eventi JS passino da `tracking.isEnabled()`
- [x] Corretto view_item (push condizionato a `isEnabled()`)
- [x] Corretto add_to_cart (passaggio price/value)
- [x] Corretto gift_purchase (passaggio value quando disponibile)
- [x] Implementare filtro `fp_exp_datalayer_purchase` in `GA4::render_purchase_event()` per payload purchase

---

*Audit completato il 2026-02-15. Modifiche applicate in: `templates/front/experience.php`, `assets/js/front.js`, `src/Integrations/GA4.php`.*
