=== FP Experiences ===

Contributors: francescopasseri
Tags: experiences, booking, wooocommerce, shortcodes, calendar
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.6.14
Last updated: 2026-03-22
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

= 1.6.14 - 2026-04-02 =
* **Removed**: `ExperienceMetaBoxes.php` — rimosso ~3300 righe di codice deprecato non invocato (render/save/get duplicati); editor solo handler + `maybe_generate_recurrence_slots` / `has_pricing`.
* **Changed**: `sync-source-to-dist-build.ps1` copia anche `src/Admin/ExperienceMetaBoxes.php` in `dist/` e `build/`.

= 1.5.59 - 2026-04-02 =
* **Fixed**: Meta box — id univoci per i trigger `.fp-exp-tooltip` (`{id}-tooltip-trigger`) così non collidono più con `<p class="fp-exp-field__description" id="…">` e `aria-describedby`.
* **Fixed**: Dettagli — blocco «Pagina pubblica»: un solo elemento `fp-exp-linked-page-desc` e `aria-describedby` sempre valido; testo di supporto quando la pagina esiste ma non c’è etichetta di stato.

= 1.5.58 - 2026-04-02 =
* **Changed**: Admin — accenti burgundy residui (placeholder media addon, chip ricorrenza, tab attiva in dark) allineati ai token viola DMS.
* **Changed**: Tooltip meta box (`render_tooltip`) — `tabindex`, `role`, `aria-label` e icona nascosta agli screen reader per accessibilità.
* **Removed**: Codice morto in `ExperienceMetaBoxes` (vecchi render/save tab); rimosso log debug temporaneo sul salvataggio cognitive biases.
* **Build**: rigenerato `fp-experiences-admin.min.css`.

= 1.5.57 - 2026-04-03 =
* **Changed**: Tooltip admin (icona info + bolla) — colori e ombre allineati al design system DMS (viola FP); tema scuro dedicato.

= 1.5.56 - 2026-04-03 =
* **Changed**: Badge di fiducia e griglia checkbox correlate — colori e bordi allineati ai token DMS (`--fpdms-*`), card selezionabili con stato checked/hover viola; campo ricerca coerente; tema scuro.
* **Fixed**: Classe body `fp-exp-admin-shell` anche su `post.php` per CPT `fp_experience`, `fp_meeting_point`, `fp_exp_gift_voucher` (bottoni meta box in stile FP).

= 1.5.55 - 2026-04-03 =
* **Changed**: Editor esperienza — barra tab meta box **non più sticky** (scorre con il contenuto della meta box).

= 1.5.54 - 2026-04-03 =
* **Fixed**: Tab Policy/FAQ — attributo `hidden` sul pannello (coerenza altre tab, niente doppio contenuto prima dell’init JS); label cancellazione allineata all’`id` della textarea.
* **Fixed**: CSS — sottotitoli campo (`.fp-exp-field__subtitle`) nelle card meta box senza padding orizzontale legacy da fieldset.

= 1.5.53 - 2026-04-03 =
* **Fixed**: Editor esperienza — titoli sezione e icone dashicons nelle card delle tab non più tagliati in alto (`overflow` e padding header allineati).

= 1.5.52 - 2026-04-03 =
* **Changed**: Editor esperienza — sezioni meta box come **card DMS** (`.fp-exp-dms-card`: header con dashicon + titolo, body); tab Dettagli suddivisa in 4 card; focus/input allineati ai token FP; tema scuro per le card.

= 1.5.51 - 2026-04-03 =
* **Changed**: Editor esperienza — meta box «Impostazioni esperienza»: etichetta guida, barra tab e pannelli distinti (DMS); fieldset come sottosezioni con accento teal; fix conflitto CSS che applicava le tab Impostazioni anche all’editor.

= 1.5.50 - 2026-04-03 =
* **Changed**: Admin calendario — gerarchia tra navigazione operatore e sottoviste (Panoramica / Calendario / Manuale): etichetta esplicativa, contenitore con accento teal e tab compatte senza barra gradiente viola.

= 1.5.49 - 2026-04-03 =
* **Fixed**: Menu admin — voce **Dashboard** visibile nel sottomenu FP Experiences; registrazione menu a priorità 5 prima di Onboarding/import (evita clone WordPress con lo stesso titolo del top-level).

= 1.5.48 - 2026-04-03 =
* **Changed**: Documentazione — `docs/ADMIN-SCREENS.md` checklist meta box editor (smoke 2026-04-03, ambiente locale ripristinato).

= 1.5.47 - 2026-04-02 =
* **Changed**: Documentazione — `docs/ADMIN-SCREENS.md`: tab meta box esperienza (slug/handler), checklist per tab editor, nota smoke click bloccata da HTTP 500 su ambiente locale.

= 1.5.46 - 2026-04-02 =
* **Fixed**: Admin — enqueue CSS dopo `colors`; reset tab core e primari rinforzati (meno flash viola/bianco e tab blu WP). Filtro `fp_exp_admin_style_dependencies`.

= 1.5.45 - 2026-04-02 =
* **Added**: Documentazione — in `docs/ADMIN-SCREENS.md` checklist verifica admin (esiti smoke browser su ambiente locale, limiti su salvataggi e ispezione DOM).

= 1.5.44 - 2026-04-02 =
* **Changed**: Admin — tab navigazione operatore (`fp-exp-operator-nav`) allineate alle tab DMS; `fp-exp-admin-shell` anche su liste/modifica **gift voucher**; inventario schermate in `docs/ADMIN-SCREENS.md`.

= 1.5.43 - 2026-04-02 =
* **Changed**: Admin — tab Impostazioni (nav-tab) in stile design system FP: contenitore `--fpdms-bg-light`, chip attivo viola, barra inferiore con `--fpdms-gradient-primary`, badge contatori allineati; tema scuro dedicato.

= 1.5.42 - 2026-04-02 =
* **Fixed**: Admin — i primari (`button button-primary`) non devono più essere selezionati dalla regola `.wrap .button` (stessa specificità): evitato flash viola → bianco e sfondo bianco persistente sui primari.

= 1.5.41 - 2026-04-02 =
* **Changed**: Admin — bottoni WordPress nel guscio FP allineati al design system (gradiente primario `--fpdms-gradient-primary`, secondari/outline, delete, small/large, focus); calendario e repeater; `fp-exp-admin-shell` anche su tassonomia lingue (`edit-fp_exp_language`).

= 1.5.40 - 2026-04-02 =
* **Fixed**: Admin — impostazioni con `--layout-fp`: esclusi i selettori legacy su `.form-table` / descrizioni / input così non restano bordi, hover a “cella” e box informativi blu; controlli nativi stilati con token `--fpdms-*`; rigenerato `fp-experiences-admin.min.css`.

= 1.5.39 - 2026-04-02 =
* **Changed**: Impostazioni — struttura interna tab allineata al design system FP (card per sezione Settings API, griglia campi, pulsante salva fp-exp-btn-primary); Email admin non modificata.

= 1.5.38 - 2026-04-02 =
* **Changed**: Impostazioni — card unica per ogni tab con titolo allineato al nav-tab (filtro `fp_exp_settings_tabs`); Tools in Impostazioni senza doppia card intro.

= 1.5.37 - 2026-04-02 =
* **Changed**: Impostazioni — card DMS sul form principale; schede Booking Rules e Logs con intestazione card; layout scheda Calendar (stato Google + form).

= 1.5.36 - 2026-04-02 =
* **Changed**: Pagina Operazioni/Calendario — card su Panoramica (KPI + filtri), tab Calendario e Prenotazione manuale visivamente allineate al design system; KPI/filtri senza inline style.

= 1.5.35 - 2026-04-02 =
* **Added**: Componenti interni admin `fp-exp-dms-card` / griglia (modello FP Mail) e pannello Strumenti con card intro + card azioni (header dashicon, `fp-exp-btn-primary`).
* **Changed**: Strumenti — CSS duplicato/glassmorphism sulle vecchie card rimosso; griglia allineata a Mail.

= 1.5.34 - 2026-04-02 =
* **Changed**: Banner — tipografia identica al CSS FP Mail; solo breadcrumb con margine extra.

= 1.5.33 - 2026-04-02 =
* **Changed**: Banner admin — spaziatura e line-height allineati al respiro del banner FP Mail (colonna titolo/descrizione, gap, breadcrumb).

= 1.5.32 - 2026-04-02 =
* **Changed**: Banner admin allineato a FP Mail SMTP (tipografia e badge).

= 1.5.31 - 2026-04-02 =
* **Changed**: Admin Calendario — form prenotazione manuale migrato a griglia FP (`fp-exp-fields-grid`, `fp-exp-btn-primary`); CSS condiviso per prossimi form.

= 1.5.30 - 2026-04-02 =
* **Changed**: Admin — `fp-exp-admin-page` su tutte le pagine plugin elencate in roadmap Fase 2; enqueue/body/admin bar per tutte le sottopagine `fp-exp-dashboard_page_*` + slug import meeting points.

= 1.5.29 - 2026-04-02 =
* **Changed**: Admin UI — Fase 1: roadmap in `docs/ADMIN-UI-ROADMAP.md`, banner allineato al design system FP, Dashboard pilota (`fp-exp-admin-page` + icona titolo), token CSS condivisi e min CSS rigenerato.

= 1.5.28 - 2026-03-31 =
* **Fixed**: Brevo — sync contatti HTTP 400 (*attributes should be an object*) quando consenso marketing era disattivo e campi anagrafica vuoti (filtro attributi + oggetto vuoto `{}`).

= 1.5.27 - 2026-03-31 =
* **Fixed**: Calendario admin — `&` e apostrofi nei titoli/messaggi (select + avviso) senza entità `&#038;` / `&#039;` (i18n per JS in plain text).

= 1.5.26 - 2026-03-31 =
* **Fixed**: Oggetto email — `&` nel titolo esperienza non diventa più `&amp;` (subject in plain text, non HTML).

= 1.5.25 - 2026-03-30 =
* **Fixed**: RTB — Rifiuta funziona anche per «Waiting payment»; ordine WC RTB annullato se non pagato.

= 1.5.24 - 2026-03-30 =
* **Fixed**: Cron RTB — rifiuto automatico slot passato eseguito anche senza hold scaduti nello stesso tick.

= 1.5.23 - 2026-03-30 =
* **Added**: RTB — rifiuto automatico se lo slot è nel passato (cron + apertura elenco Richieste); email cliente come rifiuto manuale.
* **Changed**: Richieste — righe `declined` con slot passato: niente motivo/Rifiuta; checkbox disabilitata.

= 1.5.22 - 2026-03-30 =
* **Fixed**: Admin — rigenerato `fp-experiences-admin.min.css` completo da `admin.css` (il file precedente era troncato e rompeva il layout FP su tutte le schermate).

= 1.5.21 - 2026-03-30 =
* **Fixed**: Richieste RTB — CSS colonna Azioni ripristinato.
* **Changed**: Slot passato — solo Rifiuta in elenco; approve bloccato lato server.

= 1.5.20 - 2026-03-30 =
* **Changed**: Richieste RTB — righe con data/orario slot passato: layout compatto (badge Passata, azioni compatte, tooltip hold).

= 1.5.19 - 2026-03-30 =
* **Added**: RTB hold scaduto — Approva/Rifiuta (e bulk) ripristinati; in approvazione ricontrollo capacità slot.
* **Changed**: Messaggio admin su righe hold scaduto.

= 1.5.18 - 2026-03-30 =
* **Fixed**: Richieste RTB — richieste con hold scaduto (stato `cancelled` da cron) visibili in elenco e filtro «Hold scaduto (automatico)».
* **Changed**: Default timeout hold RTB (solo se mancante in opzioni) 24 h; nota in impostazioni su timeout troppo breve.

= 1.5.17 - 2026-03-25 =
* **Fixed**: `RTBHelper::getSettings` — nessun dump opzioni RTB su ogni lettura (log enorme in `debug.log`).

= 1.5.16 - 2026-03-25 =
* **Fixed**: meno rumore in `debug.log` — rimossi log RTB/tools admin con `print_r` e dati sensibili da contesto salvataggio.

= 1.5.15 - 2026-03-24 =
* **Changed**: Brevo transactional — merge tag sito via `fp_tracking_brevo_merge_transactional_tags` prima di SMTP API (FP Tracking).

= 1.5.14 - 2026-03-24 =
* **Changed**: Brevo — upsert contatti centralizzato via FP Tracking quando abilitato; integrazione Brevo attiva senza chiave locale solo per quel percorso (eventi SMTP/transazionali richiedono ancora chiave in tab Brevo).

= 1.5.13 - 2026-03-24 =
* **Changed**: Branding email — con FP Mail SMTP attivo si usa `fp_fpmail_brand_html`; altrimenti impostazioni locali. Voucher e test email allineati al filtro `fp_exp_email_branding`.

= 1.5.12 - 2026-03-24 =
* **Added**: Brevo — tre canali messaggi al cliente (conferma/aggiornamenti, promemoria, follow-up), mix WordPress/Brevo come FP Reservations.
* **Changed**: Riprogrammazione — niente doppio invio locale+Brevo quando il canale conferma è Brevo.

= 1.5.11 - 2026-03-24 =
* **Changed**: Tab Brevo — testi più chiari, link a FP Marketing Tracking Layer, note su chiave/liste centralizzate.

= 1.5.10 - 2026-03-24 =
* **Changed**: Brevo — default email cliente WordPress; eventi/sync contatti restano con Brevo abilitato anche senza canale Brevo per le conferme.

= 1.5.9 - 2026-03-24 =
* **Added**: Brevo — sezione Messaggi al cliente (wp_mail vs Brevo, template transazionali on/off, eventi Automation selezionabili).

= 1.5.8 - 2026-03-24 =
* **FIXED**: Filtro opzione Brevo (`option_fp_exp_brevo`) — firma allineata agli argomenti WordPress; risolto errore che impediva il boot di BrevoIntegration.

= 1.5.7 - 2026-03-24 =
* GA4 purchase: affiliation, page_url thank you, item_category, coupon.

= 1.5.6 - 2026-03-23 =
* **Changed**: Notice FP Mail SMTP esplicita "Non compilare la sezione SMTP personalizzato" quando attivo.

= 1.5.5 - 2026-03-23 =
* **Added**: Notice in sezione Email se FP Mail SMTP installato (centralizza SMTP per tutti i plugin FP).

= 1.5.4 - 2026-03-22 =
* **FIXED**: Rimosso console.log in produzione (Sezione listing, Read More)

= 1.5.2 - 2026-03-19 =
* **Changed**: Gerarchia titoli admin (h1 screen reader + h2 titolo visibile) e spaziatura `.wrap` sotto le notice, in linea con il design system FP.

= 1.5.1 - 2026-03-17 =
* **Added**: Separazione visiva dei gruppi nel sottomenu admin (Operatività, Gestione, Sistema, Supporto) per orientamento più rapido.
* **Changed**: Riordinato il sottomenu FP Experiences per dare priorità ai flussi operativi quotidiani (Calendario, Richieste, Check-in, Ordini).
* **Changed**: Etichette submenu semplificate con nomi brevi e più leggibili.

= 1.5.0 - 2026-03-17 =
* **Added**: Modalità simulazione locale per Google Calendar (senza OAuth) con test create/update/delete e log diagnostici.
* **Added**: Modalità simulazione locale per Brevo (senza API key) per contact sync, transactional email e tracking eventi.
* **Added**: Nuove email dedicate per riprogrammazione cliente/staff e tool one-click "Verifica tracking simulato" in area Tools.
* **Changed**: Dashboard operatore potenziata con agenda oggi/domani, quick actions e KPI conversion/no-show.
* **Fixed**: Hardening backend reschedule con blocchi server-side su slot non validi (chiusi/passati/non consentiti) e deduplica destinatari staff.

= 1.4.12 - 2026-03-16 =
* Extended AutoTranslator for gift-redeem, widget, calendar/slots and voucher emails (IT/EN consistency).
* Voucher redemption labels now via fpExpConfig.i18n.giftRedeem; JS fallbacks use i18n for slots/calendar messages.
* Fixed IT/EN mix on voucher redeem page.

= 1.4.11 - 2026-03-15 =
* **Changed**: Migliorata la UI del modal Gift Experience su desktop/mobile (spaziature, griglia campi, addon, leggibilita e resa responsive).
* **Fixed**: Flusso redeem voucher ripristinato lato frontend/backend: inizializzazione JS dedicata sulla pagina redeem, payload voucher con slot disponibili e correzioni ai fatal REST/WooCommerce.
* **Fixed**: Cache busting piu robusto per `assets/js/front.js` in registrazione asset per ridurre casi di JS stale dopo hotfix.

= 1.4.10 - 2026-03-14 =
* **Fixed**: Endpoint REST `gift/purchase` ora inoltra correttamente `ticket_slug` e `ticket_quantities`, evitando errore critico in fase "Procedi al pagamento".
* **Fixed**: Harden payload gift su servizi backend (purchaser/recipient/delivery) e detection multilanguage senza autoload forzato per prevenire fatal side-effect con plugin esterni.

= 1.4.9 - 2026-03-14 =
* **Added**: Gift Voucher con quantità per tipo di biglietto (es. Adulto, Bambini) direttamente nel form regalo.
* **Changed**: Calcolo totale gift aggiornato per sommare le quantità selezionate per ciascun ticket type e salvare il dettaglio su ordine/voucher.

= 1.4.8 - 2026-03-14 =
* **Added**: Nel modulo Gift Voucher e ora obbligatoria la selezione del tipo biglietto (es. Adulto/Bambini) e il prezzo checkout viene calcolato sul ticket scelto.
* **Changed**: Asset frontend impostato su `assets/js/front.js` come fallback primario per garantire il nuovo payload gift senza dipendere dalla build minificata locale.

= 1.4.7 - 2026-03-14 =
* **Fixed**: Prezzo Gift Experience allineato al riepilogo ticket: quando sono presenti prezzi ticket il totale voucher non aggiunge piu il prezzo base, evitando extra fissi in checkout (es. +10 euro).

= 1.4.6 - 2026-03-14 =
* **Fixed**: Riepilogo RTB allineato ai ticket selezionati: quando sono presenti linee ticket il totale non aggiunge piu il prezzo base, evitando mismatch (es. Adulto x2 = 120 euro ma totale 180 euro).

= 1.4.5 - 2026-03-14 =
* **Added**: Quantità predefinita e massima per biglietti (esperienza di coppia) — campi in Dettagli, pre-selezione e limite sul primo ticket, validazione RTB/carrello

= 1.4.4 - 2026-03-14 =
* **Added**: Eventi a data singola — data dell'evento preselezionata nel calendario al caricamento

= 1.4.3 - 2026-03-14 =
* **Fixed**: Eventi a data singola — corretto caricamento is_event/event_datetime nel form Dettagli
* **Fixed**: Calendario ora mostra correttamente gli slot per esperienze evento a data fissa
* **Added**: Supporto completo eventi a data singola in AvailabilityService (slot da DB con lead time e buffer)

= 1.4.2 - 2026-03-13 =
* **Fixed**: Checkout gift voucher ora usa il prezzo aggiornato del prodotto regalo anche quando WooCommerce ricalcola il carrello in fasi successive.

= 1.4.1 - 2026-03-09 =
* **Fix**: GiftCheckoutHandler guard per funzioni WooCommerce — evita Fatal 500 quando WooCommerce non è caricato
* Refactor tracking (GA4, Meta Pixel, Clarity, Google Ads) al layer centralizzato FP Marketing Tracking Layer

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
