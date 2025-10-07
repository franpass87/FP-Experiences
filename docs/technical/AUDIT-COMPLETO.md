# FP Experiences — Audit Completo

## Sommario
Questo documento consolida tutti gli audit di sicurezza, performance, accessibilità e integrazioni del plugin FP Experiences.

## 1. Security Audit

### Sommario
L'audit ha rivisitato gli endpoint REST, i flussi admin, il ciclo di vita Request-to-Book, il logging e gli hook di integrazione. Il plugin applica controlli di capability e validazione nonce, e ora include throttling per client sui endpoint di submission pubblici insieme alle salvaguardie di sanitizzazione esistenti.

### Risultati
| ID | Area | Severità | Stato | Note |
| -- | ---- | -------- | ------ | ----- |
| SEC-01 | Azioni Google Calendar connect/disconnect | Bassa | Risolto | Sanitizzato handling `_wpnonce` in `SettingsPage::maybe_handle_calendar_actions()` per rimuovere il nonce prima della verifica, prevenendo fallimenti con query vars slashati. |
| SEC-02 | Controlli REST capability | Info | Pass | Tutte le route di gestione usano capability dedicate (`fp_exp_operate`, `fp_exp_manage`). |
| SEC-03 | Nonce submission front-end | Info | Pass | Endpoint checkout (`fp-exp-checkout`) e RTB (`fp-exp-rtb`) richiedono nonce firmati e sanificano payload (`sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`). |
| SEC-04 | Logging di dati sensibili | Info | Pass | `Logger::scrub_context()` maschera email, telefono e campi credential prima di persistere nelle opzioni. |
| SEC-05 | Accesso SQL | Info | Pass | Query su tabelle custom si basano su `$wpdb->prepare()` e input sanificati attraverso i modelli `Reservations`, `Slots`, e `Resources`; nessun SQL non preparato rilevato negli spot check dell'audit. |
| SEC-06 | Throttling submission pubblici | Media | Risolto | Introdotto `Helpers::client_fingerprint()` con rate limiting per client per checkout e submission RTB, restituendo HTTP 429 sui burst e inviando `nocache_headers()` per scoraggiare cache intermedie. |

### Raccomandazioni
- Estendere la documentazione delle capability per aiutare i proprietari del sito ad assegnare i nuovi ruoli correttamente.
- Considerare l'aggiunta di test di integrazione per i fallimenti nonce per prevenire regressioni.

## 2. Performance Audit

### Osservazioni
- Il renderer shortcode differisce la registrazione degli asset fino all'invocazione e scopa CSS/JS attraverso `Assets::enqueue_front()` / `enqueue_checkout()`, prevenendo overhead di enqueue globale su pagine non correlate.
- Le risposte REST per disponibilità e strumenti emettono header `Cache-Control: no-store` via `RestRoutes::enforce_no_cache()`, assicurando che i dati dinamici non siano cachati dai proxy mentre le pagine catalogo rimangono cache-friendly.
- Le tabelle custom (`fp_exp_slots`, `fp_exp_reservations`, `fp_exp_resources`) includono indici compositi su colonne experience/date e status, corrispondendo ai filtri query nelle lookup slot/reservation.
- Il rate limiting negli handler REST admin (`Helpers::hit_rate_limit()`) riduce operazioni ripetute sui endpoint capacity, resync, e replay.

### Micro-ottimizzazioni applicate
- Nessuna richiesta; l'implementazione attuale carica già pigramente le impostazioni e usa iniezione CSS basata su scope. Continuare a monitorare la generazione slot per dataset grandi e considerare batching se le occorrenze giornaliere superano migliaia.

## 3. Accessibility Audit

### Risultati
| ID | Area | Severità | Stato | Note |
| -- | ---- | -------- | ------ | ----- |
| A11Y-01 | Feedback richiesta RTB | Bassa | Risolto | Aggiunto `role="status"` al container status RTB così gli screen reader annunciano i cambiamenti di stato submission mostrati via `aria-live`. |
| A11Y-02 | Controlli calendario e quantità | Info | Pass | I giorni calendario e i pulsanti quantità sono pulsanti keyboard-focusable con attributi `aria-label` descrittivi per il contesto screen-reader. |
| A11Y-03 | Aggiornamenti sommario checkout | Info | Pass | Il sommario ordine usa `aria-live="polite"` per annunciare i ricalcoli di prezzo senza interrompere il focus della tecnologia assistiva. |
| A11Y-04 | Gestione focus widget | Media | Risolto | I widget sticky/modal ora espongono controlli open/close, catturano focus mentre attivi, ripristinano focus al trigger, e rispondono al tasto Escape. |
| A11Y-05 | Guida errori form | Media | Risolto | I form checkout e RTB mostrano una lista errori sommata con anchor campo e impostano `aria-invalid` su ogni controllo offensivo. |
| A11Y-06 | Avviso contrasto branding | Media | Risolto | La tab branding renderizza un avviso accessibilità che evidenzia i rapporti contrasto sub-AA in tempo reale e conferma quando la palette passa. |

### Raccomandazioni
- Monitorare aggiunte future per nuove superfici interattive che potrebbero anche richiedere focus trapping o feedback errori sommato.
- Continuare a validare preset palette custom quando vengono introdotti nuovi colori per assicurare che i rapporti AA siano preservati.

### Verifica
- Widget sticky aperto via il nuovo launcher, catturato focus con Tab/Shift+Tab, e chiuso via Escape con focus che ritorna al trigger.
- Triggered checkout e submission RTB con campi richiesti vuoti per confermare che il sommario errori riceve focus e i link muovono focus agli input associati.
- Regolato colori branding a valori contrasto basso per osservare avvisi e verificato messaggi di successo quando i colori tornano a rapporti compliant.

## 4. Integrations Audit

### Brevo
- La route REST `fp-exp/v1/brevo` accetta callback POST quando firmati con il webhook secret condiviso; il contesto payload è mascherato prima del logging per evitare esposizione PII.
- Le email transazionali falliscono su template WooCommerce se la chiamata API Brevo fallisce; i fallimenti sono loggati via `Logger::log('brevo', ...)`.
- L'upsert contatto sanifica attributi (nome, telefono, tag UTM) prima di colpire l'API e rispetta il consenso marketing memorizzato sull'ordine.
- Le Impostazioni Admin mostrano stato connessione, copertura template, e qualsiasi errore API recente catturato via avvisi transient così gli operatori possono risolvere problemi velocemente.

### Google Calendar
- OAuth connect/disconnect protetto da capability e controlli nonce; token memorizzati nell'opzione `fp_exp_google_calendar` con refresh scadenza.
- Eventi creati solo quando le reservation raggiungono stati paid/approved; le cancellazioni triggerano chiamate delete con logging per diagnostica.
- Gli allegati ICS continuano a essere spediti indipendentemente dalla connettività calendario.
- Il refresh token o i fallimenti sync evento sollevano avvisi inline nella tab Calendar settings senza interrompere le prenotazioni.

### Diagnostica
- L'endpoint `fp-exp/v1/ping` GET è disponibile ai detentori di capability (`fp_exp_manage`) per controlli salute REST veloci.

## 5. Fix Changelog

| ID | File | Linea | Severità | Sommario Fix | Commit |
| --- | --- | --- | --- | --- | --- |
| ISSUE-004 | src/Shortcodes/BaseShortcode.php | 47 | Alta | Salta header no-store globali a meno che uno shortcode non opti in, mantenendo pagine cached intatte. | 0c4736f |
| ISSUE-001 | src/Utils/Helpers.php | 465 | Alta | Permetti ai permessi REST di fallire su payload nonce quando l'header X-WP-Nonce targeta un'altra azione. | 249a0bc |
| ISSUE-002 | src/Utils/Helpers.php | 492 | Alta | Richiedi un REST nonce valido o referer same-origin prima che gli endpoint REST gift voucher funzionino. | 1b332e6 |
| ISSUE-003 | src/Api/RestRoutes.php | 281 | Media | Limita override Cache-Control alle route fp-exp così le risposte REST core rimangono cacheable. | 9d30a16 |
| ISSUE-005 | src/Booking/Cart.php | 344 | Media | Marca il cookie sessione fp_exp_sid come HttpOnly per bloccare accesso script. | 8c208a4 |
| ISSUE-006 | templates/front/widget.php | 55 | Media | Formatta prezzi biglietto widget con il simbolo valuta store e posizionamento. | 4d0bf25 |
| ISSUE-006 | templates/front/list.php | 70 | Media | Renderizza prezzi listing con simbolo WooCommerce e rispetta posizione valuta. | 4d0bf25 |
| ISSUE-006 | templates/front/simple-archive.php | 29 | Media | Sostituisci simboli Euro hardcoded con output valuta guidato WooCommerce. | 4d0bf25 |
| ISSUE-007 | src/Gift/VoucherManager.php | 386 | Media | Processa reminder gift in batch paginati per evitare di caricare ogni voucher alla volta. | 3b75ce5 |
| ISSUE-008 | src/MeetingPoints/MeetingPointMetaBoxes.php | 102 | Bassa | Unslash payload meta meeting point prima di sanificare per rimuovere backslash vaganti. | 9876b84 |
| ISSUE-009 | src/Booking/Slots.php | 507 | Media | Aggiungi helper snapshot capacità bulk usato da shortcode front-end. | e0e6099 |
| ISSUE-009 | src/Shortcodes/CalendarShortcode.php | 156 | Media | Usa lo snapshot bulk per rimuovere query N+1 shortcode calendario. | e0e6099 |
| ISSUE-009 | src/Shortcodes/WidgetShortcode.php | 352 | Media | Riusa dati capacità slot aggregati quando costruendo liste slot widget. | e0e6099 |

### Sommario
Risolti 9 di 9 problemi auditati attraverso due batch di fix; nessun elemento in sospeso rimane dopo aver marcato la fase fix completa.

## Stato Finale
**TUTTI GLI AUDIT COMPLETATI CON SUCCESSO**

Ultimo aggiornamento: 2025-01-27
