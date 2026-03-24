# Changelog

All notable changes to FP Experiences will be documented in this file.

## [1.5.8] - 2026-03-24
### Fixed
- Filtro `option_fp_exp_brevo`: `mergeBrevoFromTracking` accetta solo gli argomenti effettivamente passati da WordPress (2), evitando fatal su PHP 8 e ripristinando il boot dell’integrazione Brevo.

## [1.5.7] - 2026-03-24
### Changed
- GA4 WooCommerce (`Integrations\GA4`): evento `purchase` arricchito con `affiliation`, `fp_source`, `page_url` (thank you), `item_category` sugli articoli, `coupon` se presente.

## [1.5.6] - 2026-03-23
### Changed
- Notice FP Mail SMTP: aggiunto esplicito "Non compilare la sezione SMTP personalizzato" quando attivo.

## [1.5.5] - 2026-03-23
### Added
- Notice in sezione Email: se FP Mail SMTP è installato, avvisa che centralizza SMTP per tutti i plugin FP con link a Impostazioni.

## [1.5.4] - 2026-03-22
### Fixed
- Rimosso console.log in produzione (Sezione listing non trovata, Read More toggle)

## [1.5.3] - 2026-03-22
### Added
- Nuovo flusso check-in mobile con shortcode dedicato e template frontend per scansione/operatività sul campo.

### Changed
- Aggiornamento esteso UI/UX admin/frontend (dashboard, richieste, email, strumenti, listing/widget) con affinamenti layout e coerenza design system.
- Impostazioni tracking allineate al layer centralizzato FP Marketing Tracking Layer: rimosse dalla UI le credenziali canale locali (GA4/Ads/Meta/Clarity).

## [1.5.2] - 2026-03-19
### Changed
- Admin: gerarchia titoli allineata al design system FP (`h1.screen-reader-text` nel `.wrap`, titolo visibile in `h2` con `aria-hidden="true"`) su tutte le pagine admin principali; margine superiore del `.wrap` sotto le notice.

## [1.5.1] - 2026-03-17
### Added
- Added visual section separators in the WordPress admin submenu for FP Experiences to improve navigation scanning.

### Changed
- Reordered FP Experiences submenu entries to prioritize daily operator flows (calendar, requests, check-in, orders) before management/system pages.
- Simplified submenu labels to concise names for better readability.

## [1.5.0] - 2026-03-17
### Added
- Added local simulation mode for Google Calendar (no OAuth required) with simulated create/update/delete flow and diagnostic logs for reservation lifecycle events.
- Added local simulation mode for Brevo (no API key required) to simulate contact sync, transactional sends, and event tracking without external API calls.
- Added customer/staff reschedule templates and dedicated reschedule notification flow (customer + staff) with automatic reminder/follow-up rescheduling.
- Added a one-click Tools action to verify simulated tracking and render structured diagnostic details in the admin output panel.

### Changed
- Improved backend operator workflow for reservation reschedule with a calendar-oriented date selection and stronger server-side safeguards.
- Enhanced dashboard operational visibility with day-by-day agenda, quick actions, and KPI cards for conversion/no-show monitoring.
- Improved Google Calendar event payload quality (staff attendees, deduplication, extended properties) and sync diagnostics.

### Fixed
- Prevented invalid reschedule transitions to non-selectable slots (closed/past/not allowed states).
- Removed duplicate staff recipients in notification templates for reschedule and generic staff emails.

## [1.4.12] - 2026-03-16
### Added
- Extended AutoTranslator with IT/EN mappings for gift-redeem page, widget labels, calendar/slots fallbacks, and voucher emails so the frontend and emails are consistent in both languages.
- Voucher redemption labels (lookup, redeem, errors) now passed via `fpExpConfig.i18n.giftRedeem` and used in front.js for consistent translation.
- i18n keys for JS fallbacks: readMore, readLess, slotsEmpty, slotsEmptyShort, calendarError, slotsLoadError.

### Changed
- front.js, slots.js and calendar-standalone.js now use translated strings from fpExpConfig.i18n for placeholder and error messages instead of hardcoded Italian.

### Fixed
- Resolved IT/EN mix on voucher redeem section (title, description, labels and button now follow the active language).

## [1.4.11] - 2026-03-15
### Changed
- Refined gift purchase modal UI on desktop and mobile (cards, fields, add-ons grid, spacing, and responsive layout) for better readability and conversion flow.
- Gift voucher frontend now initializes redemption logic on the dedicated redeem page without depending on the single-experience widget bootstrap.

### Fixed
- Gift voucher lookup now returns the full payload with upcoming slots in `VoucherManager::get_voucher_by_code`, restoring slot selection in the redeem flow.
- Gift REST controller methods now allow `WP_Error` responses in type declarations to prevent fatal errors on error paths.
- Gift redemption order item creation no longer calls unsupported WooCommerce `set_type()` to avoid runtime fatal errors on redeem.
- Added stronger frontend asset cache-busting for `front.js` registration to reduce stale-JS issues after hotfix deployments.

## [1.4.10] - 2026-03-14
### Fixed
- REST gift purchase payload now forwards `ticket_slug` and `ticket_quantities` from `GiftController` to avoid critical checkout errors when submitting the gift form.
- Gift purchase sanitization now guards `purchaser`, `recipient`, and `delivery` payload structures before normalization, preventing type-related fatals on malformed requests.
- Multilanguage compatibility detection now avoids forced autoload during `class_exists` checks to prevent side effects from third-party classmap entries.

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
