# Changelog

All notable changes to FP Experiences will be documented in this file.

## [1.4.9] - 2026-03-14
### Added
- Gift voucher form now supports per-ticket quantities (for example Adult and Child in the same gift purchase flow).

### Changed
- Gift pricing now sums selected quantities for each ticket type and persists `ticket_quantities` metadata on orders and vouchers.

## [1.4.8] - 2026-03-14
### Added
- Gift voucher flow now requires selecting a ticket type (for example Adult/Child), and stores the selected ticket slug/label in order and voucher metadata.

### Changed
- Gift voucher pricing now uses the selected ticket type price instead of an automatic lowest-ticket fallback.
- Frontend asset resolution now prioritizes `assets/js/front.js` to ensure the updated gift payload is loaded even when minified builds are unavailable.

## [1.4.7] - 2026-03-14
### Fixed
- Gift voucher pricing: when ticket pricing is available, the voucher total no longer adds `_fp_base_price`, avoiding fixed extra amounts at checkout (for example +10 on top of expected ticket total).

## [1.4.6] - 2026-03-14
### Fixed
- RTB summary pricing: when ticket lines are present, the total no longer adds `_fp_base_price`, preventing inflated totals in the recap (for example `Adulto x2 = 120` with total shown as `180`).

## [1.4.5] - 2026-03-14
### Added
- Quantità predefinita e massima per biglietti (esperienza di coppia): nuovi campi "Quantità predefinita" e "Quantità massima" in Dettagli. Quando impostati (es. default 2, max 2), il primo tipo di biglietto viene pre-selezionato e limitato. Validazione backend su RTB e carrello WooCommerce.

## [1.4.4] - 2026-03-14
### Added
- Eventi a data singola: data dell'evento preselezionata nel calendario al caricamento (mese e giorno già selezionati)

## [1.4.3] - 2026-03-14
### Fixed
- Eventi a data singola: corretto caricamento `is_event` e `event_datetime` in `DetailsMetaBoxHandler::get_meta_data()` (variabili non definite)
- Eventi a data singola: calendario ora mostra correttamente gli slot (gestione in `AvailabilityService::get_virtual_slots()` e `CalendarShortcode`)

### Added
- Supporto completo eventi a data singola: `AvailabilityService` legge slot da DB quando `_fp_is_event` è attivo, con lead time e buffer

## [1.4.2] - 2026-03-13
### Fixed
- Gift voucher checkout: riallineato il prezzo item in carrello durante i ricalcoli WooCommerce per evitare mismatch tra importo mostrato e totale finale.

## [1.4.1] - 2026-03-09
### Changed
- Refactor: migrazione integrazioni tracking (GA4, Meta Pixel, Clarity, Google Ads) al layer centralizzato FP Marketing Tracking Layer
- Routing eventi tramite CustomEvent invece di chiamate dirette ai provider

### Fixed
- GiftCheckoutHandler: guard `function_exists('is_checkout')` prima di usare funzioni WooCommerce — evita Fatal 500 quando WooCommerce non è caricato

## [1.4.0] - 2026-03-02
### Added
- Campo URL recensione per email di follow-up
- Colore accent personalizzabile per branding email (header, bottoni, link)

### Fixed
- Rimosso `readonly` da `FieldDefinition` per compatibilità PHP 8.0
- Merge `sanitize_emails_settings` con valori esistenti
- WC Mailer dispatch e toggle rendering resilience

## [1.3.7] - 2026-03-01
### Added
- Bottone invio email di test
- Anteprime email raggruppate con trigger e template RTB/Gift

### Changed
- Centralizzato servizio Mailer con provider/SMTP settings e dependency injection
- Overhauling completo del sistema email

### Fixed
- Layout pagina impostazioni email con fix overflow aggressivo

## [1.3.6] - 2026-02-23
### Fixed
- Audit v1.3.7-v1.4.0: 30+ fix su tutti i flussi booking (calendar capacity, WC checkout guard, RTB status check, gift voucher email, Brevo fallback, logging)
- Fallback meta box con chiavi meta multiple
- Fallback meta box ordini da WC item meta + tool migrazione prenotazioni
- Branding (logo, header, footer) nelle email tramite filtro `fp_exp_email_branding`
- Riorganizzazione pagina email in sotto-tab con bottone salva visibile

## [1.3.0] - 2026-02-21
### Fixed
- Email notifications, order details meta box, calendar titles
- Meta box ordini, checkout vuoto, dettagli prenotazioni calendario

## [1.2.x] - 2026-02-15
### Added
- Tracking GTM completo: `view_item` con consent, `add_to_cart`/`gift_purchase` con value
- Filtro `fp_exp_datalayer_purchase`
- GA4 dataLayer tracking per GTM: `view_item_list`, `select_item`, `add_to_cart`, `begin_checkout`, `gift_purchase`, RTB events

### Fixed
- Registrazione 7 endpoint admin tool mancanti in RouteRegistry
- Registrazione endpoint gift REST mancanti
- Tracking tab: checkbox unchecked salvato come enabled (hidden value `no->0`)
- Carrello WooCommerce vuoto al checkout causato da Set-Cookie su ogni richiesta

## [1.1.x] - 2026-01-27
### Added
- Template HTML strutturati per tutte le email RTB (richiesta, approvata, rifiutata, pagamento)
- Conferma manuale RTB e localizzazione email completa ITA/ENG/TEDESCO
- Integrazione ruoli con FP Restaurant: operatori hanno accesso a entrambi i plugin
- Metabox e badge traducibili per WPML

### Fixed
- CTA RTB ora dice "Invia richiesta di prenotazione"
- RTB usa correttamente impostazione globale per esperienze
- Nascondere metadati tecnici ordine nel frontend (thank you page)
- Risolto errore "Controllo cookie fallito" nelle richieste RTB
- Rimosso nonce da RTB request/quote (protetto da rate limit)
- Colonna Azioni tabella richieste RTB: bottoni full width e font più piccolo
- Salvataggio meta RTB con underscore prefix per nasconderli automaticamente
- Disabilitare email WooCommerce per ordini esperienze (usa email FP-Experiences)
- Fix CRITICO: `calculate_price_from_meta` ora legge anche da `_fp_exp_pricing`
- Fix CRITICO: widget.php usa `price_from` calcolato correttamente

## [1.0.x] - 2025-10-xx
### Added
- Release iniziale: booking esperienze stile GetYourGuide
- Shortcode e blocchi Elementor
- Carrello e checkout isolati da WooCommerce standard
- Integrazione Brevo (opzionale) per email transazionali
- Integrazione Google Calendar (opzionale)
- Tracking marketing (opzionale)
- Sistema RTB (Request to Book)
- Gift voucher con integrazione WooCommerce
- Calendario disponibilità con gestione capacità
