=== FP Experiences ===

Contributors: francescopasseri
Tags: experiences, booking, wooocommerce, shortcodes, calendar
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 0.3.7
Last updated: 2025-10-13
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

FP Experiences brings GetYourGuide-style booking flows to WooCommerce without touching existing physical products. It delivers isolated carts, shortcodes, Elementor widgets, marketing integrations, and staff tooling tailored for tour operators and experience providers.

== Description ==

* Isolated booking cart that never mixes with WooCommerce products and uses a dedicated checkout shortcode.
* Experience discovery widgets with availability calendars, ticket types, add-ons (now with thumbnails), and schema-ready markup.
* “Gift Your Experience” vouchers with configurable validity, reminder cadence, transactional emails, and front-end purchase/redemption flows.
* Language badges with local SVG flags on experience cards, widgets, and admin taxonomy pages (text labels included for a11y).
* Experience editor enhancements with hero gallery management, inline language selection/creation, and streamlined essentials lists that lean on native bullets.
* Simple archive shortcode with grid/list cards, CTA buttons, and a wider desktop container for the advanced showcase.
* Automatic landing page creation for each published experience, with a Tools resync command to regenerate missing `[fp_exp_page]` destinations.
* Reusable meeting points with optional CSV import (advanced toggle), experience linking, shortcode, and Elementor widget.
* Optional Brevo transactional email delivery, Google Calendar sync, and marketing pixels (GA4, Google Ads, Meta, Clarity).
* Admin calendar with drag-and-drop rescheduling, recurrence previews/regeneration, manual booking creation with payment links, and operations roles for managers, operators, and guides.
* Consent-aware tracking, theming presets, CSS variable overrides, and Elementor controls for editors.

== Installation ==

1. Upload the `fp-experiences` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Visit **FP Experiences → Settings** to configure branding, emails, Brevo, calendar, tracking, and tools.
4. Add the shortcodes or Elementor widgets to the desired pages.

== Shortcodes ==

* `[fp_exp_list]` – Mobile-first experiences showcase with accessible filter form (themes, languages, duration, price range, family-friendly toggle, date picker, and text search), sorting controls, pagination, price badges, optional map links, and dataLayer tracking (`view_item_list` + `select_item`). Active theme/language/family selections appear as removable chips with a reset shortcut so visitors can adjust quickly. Attributes: `filters`, `per_page`, `page`, `search`, `order`, `orderby`, `view`, `show_map`, `cta`, `badge_lang`, `badge_duration`, `badge_family`, `show_price_from`, plus layout helpers (`columns_desktop`, `columns_tablet`, `columns_mobile`, `gap`). Price badges cache the lowest ticket price per experience via transients and respect dedicated experience pages when available. Examples:
  * `[fp_exp_list filters="theme,language,price,date,family" per_page="9" view="grid" orderby="price" order="ASC" show_price_from="1" show_map="1"]`
  * `[fp_exp_list filters="search,theme" per_page="12" view="list" cta="widget" gap="compact"]`
* `[fp_exp_simple_archive view="grid" columns="3" order="menu_order" order_direction="ASC"]` – Lightweight archive without filters that outputs responsive cards (image, title, duration, price badge, Dettagli/Prenota CTAs) in grid or list mode. Automatically stretches to a wider desktop container while keeping mobile-friendly spacing. Attributes: `view` (`grid`|`list`), `columns` (1–4, desktop grid), `order` (`menu_order`, `date`, `title`), `order_direction` (`ASC`|`DESC`).
* `[fp_exp_widget id="123"]` – Booking widget for a specific experience. Attributes: `sticky`, `show_calendar`, `primary`, `accent`, `radius`.
* `[fp_exp_calendar id="123" months="2"]` – Inline availability calendar for a single experience.
* `[fp_exp_checkout]` – Isolated checkout that finalises the FP Experiences cart only.
* `[fp_exp_gift_redeem]` – Voucher redemption form that looks up a gift code, lists prepaid add-ons, shows upcoming slots, and confirms the booking at zero cost.
* `[fp_exp_meeting_points id="123"]` – Outputs the primary meeting point and optional alternatives for an experience, with map links built client-side.
* `[fp_exp_page id="123" sections="hero,highlights,inclusions,meeting,extras,faq,reviews" sticky_widget="1" container="boxed" max_width="1200" gutter="24" sidebar="right"]` – Full experience detail page with hero gallery, highlights, inclusions/exclusions, meeting point block, FAQ accordion, reviews, and sticky availability widget. Supports theming overrides (`preset`, `mode`, color variables, `radius`, `shadow`, `font`) plus layout controls: `container` (`boxed` or `full`), `max_width`/`gutter` (pixels) and `sidebar` (`right`, `left`, `none`).

The `sections` attribute accepts a comma-separated list of sections to render (hero, highlights, inclusions, meeting, extras, faq, reviews). Meeting point data automatically reuses the Meeting Points module when enabled; otherwise the section is hidden. Set `sticky_widget="0"` to disable the mobile CTA bar.

== Elementor Widgets ==

Six Elementor widgets mirror the shortcodes: List, Widget, Calendar, Checkout, Meeting Points, and the new Experience Page layout. The List widget now bundles the full showcase controls (filters, search, ordering, map toggle, CTA behaviour) plus a one-click switch between the advanced archive and the new simple grid/list layout (with column selector). Responsive style controls for columns, card spacing, and badge/price visibility remain available in advanced mode, while both modes inherit theming overrides (colors, radius, fonts) and behavioural toggles (sticky mode, inline calendar, consent defaults). The Experience Page widget lets editors pick sections to display and toggle the sticky availability bar while reusing the `[fp_exp_page]` shortcode under the hood.

If your theme applies a narrow content container you can break the layout out to the full viewport with `container="full"` (optionally adjusting `max_width`/`gutter`).

== Admin menu ==

* **Dashboard** — visione rapida di KPI e ordini (solo ruoli con `fp_exp_manage`).
* **Esperienze** / **Nuova esperienza** — gestiscono il CPT `fp_experience` (`edit_fp_experiences`).
* **Meeting point** — appare quando l'opzione è attiva; richiede `fp_exp_manage`.
* **Calendario**, **Richieste**, **Check-in** — strumenti operativi per chi possiede `fp_exp_operate`.
* **Ordini** — scorciatoia agli ordini WooCommerce filtrati (necessita sia `fp_exp_manage` che `manage_woocommerce`).
* **Impostazioni**, **Tools**, **Logs** — pannelli amministrativi riservati ai manager (`fp_exp_manage`).
* **Guida & Shortcode** — documentazione interna accessibile a tutti i ruoli FP (`fp_exp_guide`).
* **Crea pagina esperienza** — azione rapida per generare una pagina con shortcode (`fp_exp_manage`).

La voce di menu viene replicata anche nella toolbar con collegamenti rapidi (Nuova esperienza, Calendario, Richieste, Impostazioni).

== Settings & Tools ==

* **General** – Structure and webmaster emails, locale preferences, VAT class filters, meeting points toggle, advanced meeting point import toggle, Experience Page layout defaults (container, max-width, gutter, sidebar).
* **Branding** – Color palette, section icon background/foreground colours, button radius, shadows, presets, contrast checker, optional Google Font.
* **Showcase** – Default filters, ordering, price badge toggle, and the shared badge library (edit preset labels/descriptions or add new entries for editors).
* **Gift** – Enable vouchers, set default validity (days), configure reminder offsets/time, and define the redemption landing page.
* **Tracking** – Enable/disable GA4, Google Ads, Meta Pixel, Clarity, enhanced conversions, and consent defaults.
* **Brevo** – API key, webhook secret (required for webhook callbacks), list ID, attribute mappings, transactional template IDs, webhook diagnostics.
* **Calendar** – Google OAuth client credentials, redirect URI, connect/disconnect, target calendar.
* **Tools** – Brevo resync, event replay, experience page resync (creates missing `[fp_exp_page]` entries), REST API ping, meeting point CSV import (visible only when the advanced toggle is on), and cache/log clearance with rate-limited REST endpoints.

== Admin UX ==

The Experience edit screen now groups meta fields into accessible tabs (“Dettagli”, “Biglietti & Prezzi”, “Calendario & Slot”, “Meeting Point”, “Extra”, “Policy/FAQ”, “SEO/Schema”) with a sticky navigation bar. Ticket types and add-ons use drag-and-drop repeaters with inline validation, tooltips, and non-blocking warnings when no ticket is configured. The tabs support deep linking, focus management, and keyboard navigation while keeping the original `_fp_*` meta keys untouched. Editors can also curate the hero gallery (multi-select + drag reorder), choose/display languages with live badge previews, and rely on reusable badge presets to highlight selling points across the template.

== Hooks ==

Filters:

* `fp_exp_cart_can_mix` – Allow WooCommerce cart mixing (default false).
* `fp_exp_vat_class` – Override VAT class used on experience line items.
* `fp_exp_email_recipients` – Modify staff email recipients array.
* `fp_exp_brevo_contact_payload`, `fp_exp_brevo_tx_payload` – Adjust Brevo payloads before sending.
* `fp_exp_calendar_event_payload` – Modify Google Calendar event payload before insert/update.
* `fp_exp_datalayer_purchase` – Filter purchase payload pushed to the dataLayer.

Actions:

* `fp_exp_reservation_created`
* `fp_exp_reservation_paid`
* `fp_exp_reservation_cancelled`
* `fp_exp_checkin_done`

== Frequently Asked Questions ==

= Does FP Experiences create WooCommerce products? =

No. Experiences are stored as a dedicated custom post type with isolated availability, pricing, and checkout logic.

= Can I keep my existing WooCommerce theme? =

Yes. The plugin scopes CSS and JS to its shortcodes/widgets, injects CSS variables only when needed, and exposes branding controls for overrides.

= How do gift vouchers work? =

Enable the **Gift** settings tab to define voucher validity and reminder cadence. Customers can purchase a gift directly from the experience page; the recipient receives a unique code and redemption link. The `[fp_exp_gift_redeem]` shortcode renders the lookup/booking form so recipients can pick a slot and complete a zero-cost checkout (prepaid add-ons included). Reminders fire automatically 30/7/1 days before expiry, and managers can extend or cancel vouchers from the dedicated admin screen.

= How are transactional emails delivered? =

If Brevo credentials are provided, confirmations, reminders, and cancellations use Brevo templates. Otherwise WooCommerce’s mailer delivers the bundled templates with ICS attachments.

== Privacy ==

FP Experiences stores reservation details inside custom tables linked to WooCommerce orders. Marketing consent is recorded per order (`_fp_exp_consent_marketing`) and forwarded to Brevo only when enabled. UTM parameters are captured in the `fp_exp_utm` cookie, copied to reservation/order meta, and never displayed publicly. Site owners can export or erase booking data through WooCommerce personal data tools; deleting an order removes the associated reservation payload. API credentials (Brevo, Google Calendar) are kept in WordPress options and can be revoked at any time from the Settings screen.

== Changelog ==

= 0.3.7 - 2025-10-13 =
* **🔴 CRITICO - Race Condition Fix**: Risolto bug critico nel sistema di booking che poteva causare overbooking in scenari di alta concorrenza
  - Implementato pattern di double-check con verifica capacità post-creazione prenotazione
  - Rollback automatico se viene rilevato overbooking
  - Nuovi codici errore: fp_exp_capacity_exceeded / fp_exp_rtb_capacity_exceeded
  - Aggiunto metodo Reservations::delete() per gestione atomica cancellazioni
  - Performance overhead minimo: ~20-50ms solo su slot con capacità limitata
* **Memory Leak Fix**: Risolto memory leak in frontend JavaScript causato da event listener resize non rimosso
  - Implementato cleanup automatico con evento beforeunload
  - Previene accumulo di listener in sessioni lunghe
* **Console Logging Cleanup**: Rimossi 32 console.log/warn/error dai file JavaScript di produzione
  - Codice più pulito e performante
  - Nessuna esposizione di informazioni di debug agli utenti
* **Security Audit**: Eseguito audit completo di sicurezza su tutto il codebase
  - Verificati: nonce verification, input sanitization, output escaping, SQL injection, XSS
  - 51,000+ linee di codice analizzate, 147 file verificati
  - 0 vulnerabilità trovate
* **Regression Testing**: Verificato che i fix non introducano regressioni
  - Backward compatibility completa
  - Hook WordPress chiamati correttamente
  - Nessun breaking change

= 0.3.4 - 2025-01-27 =
* **Ottimizzazione Documentazione**: Consolidati tutti i file di audit in un unico documento completo (AUDIT-COMPLETO.md)
* **Guida Importer Consolidata**: Unificati tutti i file dell'importer in una guida completa (IMPORTER-COMPLETO.md)
* **Verifica Completa**: Consolidati tutti i file di verifica in una guida unificata (VERIFICA-COMPLETA.md)
* **Riduzione File**: Eliminati 15+ file ridondanti per ottimizzare la struttura della documentazione
* **Aggiornamento README**: Tradotto e aggiornato il README principale in italiano con riferimenti alla nuova documentazione consolidata
* **Miglioramento Organizzazione**: La documentazione è ora più facile da navigare e mantenere

= 0.3.3 - 2025-01-27 =
* **Miglioramenti Admin Calendar**: Aggiunto supporto per filtraggio per esperienza nel calendario admin con selector dinamico e gestione stati vuoti
* **Ottimizzazioni JavaScript**: Migliorata gestione errori API, debouncing per chiamate multiple, e messaggi di errore localizzati in italiano
* **UI/UX Admin**: Migliorata esperienza utente con messaggi informativi quando non ci sono esperienze disponibili e link diretti per creare la prima esperienza
* **Console Check-in**: Migliorata interfaccia check-in con gestione stati prenotazioni e feedback utente più chiaro
* **Gestione Email**: Potenziata sezione gestione email con layout migliorato e navigazione breadcrumb
* **Logs e Diagnostica**: Migliorata pagina logs con filtri avanzati e diagnostica di sistema più dettagliata
* **Strumenti Operativi**: Ottimizzata pagina strumenti con layout migliorato e descrizioni più chiare
* **Accessibilità**: Migliorata accessibilità con etichette screen reader e gestione focus appropriata
* **Localizzazione**: Aggiunti messaggi di errore in italiano per migliorare l'esperienza utente italiana

= 0.2.0 =
* Polish UI/UX stile GetYourGuide (layout 2-col, sticky, chips).
* Bugfix: fallback ID shortcode, flush transients, no-store headers.
* Admin menu unificato + “Crea Pagina Esperienza”.
* Listing con filtri e “price from”.
* Hardened hooks/REST/nonce (no WSOD).

= 0.1.0 =
* Initial public release with isolated booking cart, shortcodes, Elementor widgets, Brevo integration, Google Calendar sync, marketing tracking toggles, admin calendar/manual booking tools, and hardened REST utilities.

See `docs/CHANGELOG.md` for the full development log.
