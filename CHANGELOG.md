# Changelog

All notable changes to FP Experiences will be documented in this file.

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
