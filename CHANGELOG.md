# Changelog

All notable changes to FP Experiences will be documented in this file.

## [1.5.50] - 2026-04-03

### Added

- **Editor esperienza → Dettagli** (solo con «Evento a data fissa»): controlli sullo step **Richieste speciali** nel widget — modalità standard, solo note libere o step nascosto; titolo step, etichetta note e testo di aiuto opzionali (`_fp_single_event_special_requests_*`).

### Changed

- **Widget**: per eventi a data singola, testo di aiuto predefinito sotto le richieste speciali riformulato (data/ora già fissi; usare lo spazio per allergie, accessibilità, note per il giorno). Esperienze ricorrenti restano con il copy originale; il campo «Testo di aiuto» in Dettagli resta prioritaria se compilato.

## [1.5.49] - 2026-04-03

### Added

- **Impostazioni → Gift**: opzione **Regalo su eventi a data singola** (`allow_gift_single_date`, default attivo). Se disattivata, il pulsante «Regala questa esperienza» non compare sulle esperienze con data evento fissa e l’API voucher rifiuta l’acquisto (`VoucherValidationService`).

### Changed

- `Helpers::gift_enabled_for_experience( int $id )` e `Helpers::gift_allow_single_date_events()` per la policy; contesto shortcode pagina esperienza usa `gift.enabled` coerente con l’esperienza.

## [1.5.48] - 2026-04-03

### Changed

- **Widget — evento a data singola**: rimossi calendario espandibile e blocco fasce in pagina; resta il box con data/ora in grande. Calendario e lista slot restano nel DOM in area “assist” nascosta per il JS (caricamento slot + checkout). Selezione automatica del **primo** slot disponibile quando ce n’è almeno uno (prima solo se unico slot).

## [1.5.47] - 2026-04-03

### Changed

- **Frontend — hero evento a data singola**: data/ora sotto al titolo resa più leggibile (`clamp` ~1.2–1.7rem), badge con più contrasto, bordo e ombra leggera (`front.css` `.fp-exp-hero__event-date`).

## [1.5.46] - 2026-04-02

### Fixed

- **Admin — FOUC / stili che “rimbalzano”**: `fp-exp-admin` CSS ora dipende da `colors` (dopo `wp-admin` / `buttons` / schema colori) così tab e bottoni DMS non vengono sovrascritti da fogli core caricati dopo; reset esplicito su `.nav-tab` nel guscio `[data-fp-exp-admin]` (`float`, `margin-bottom`, `border-bottom`); rinforzo `!important` su `.button.button-primary` dentro `body.fp-exp-admin-shell.wp-core-ui` (e tema scuro) per coesistenza con WooCommerce e altri enqueue tardivi.

### Added

- Filtro documentato: `fp_exp_admin_style_dependencies` (default `['colors']`) per estendere le dipendenze dello stile admin del plugin.

## [1.5.45] - 2026-04-02

### Added

- **Documentazione**: in `docs/ADMIN-SCREENS.md` sezione **Checklist verifica** con esiti PASS (smoke browser su fp-development.local, 2026-04-02) per pagine custom, tab Impostazioni/Email, viste Calendario, Richieste, Check-in, CPT/tassonomia e editor esperienza; note su limiti (salvataggi, Tools REST, dashboard solo guida, ispezione DOM completa).

## [1.5.44] - 2026-04-02

### Changed

- **Admin — navigazione operatore** (`.fp-exp-operator-nav.nav-tab-wrapper` su Calendario, Richieste RTB, Check-in): stessi contenitori/chip/barra gradiente attiva e tema scuro delle tab Impostazioni/Email (`.fp-exp-tabs`), con margine orizzontale neutro rispetto al full-bleed delle tab interne alla pagina Calendario.
- **Body class**: `fp-exp-admin-shell` estesa a **`edit-fp_exp_gift_voucher`** e **`fp_exp_gift_voucher`** (coerenza con enqueue CSS già presente).

### Added

- **Documentazione**: `docs/ADMIN-SCREENS.md` — inventario pagine custom, CPT/tassonomie, pattern HTML e tipi di tab.

## [1.5.43] - 2026-04-02

### Changed

- **Admin — tab Impostazioni** (`.fp-exp-tabs.nav-tab-wrapper` / `.fp-exp-settings .nav-tab-wrapper`): aspetto allineato al design system DMS — contenitore con `--fpdms-bg-light`, bordo `--fpdms-border-light`, chip con hover lavanda, scheda attiva con testo `--fpdms-primary-dark` e **indicatore inferiore** con `var(--fpdms-gradient-primary)` al posto del blu WordPress e della “linguetta” classica; badge `.fp-exp-tab-badge` con token viola; regole **tema scuro** (`html.fp-exp-dark`) per wrapper e tab.

## [1.5.42] - 2026-04-02

### Fixed

- **Admin — bottoni primari**: la regola `body.fp-exp-admin-shell .wrap .button` coincideva con gli elementi `class="button button-primary"` (entrambe le classi presenti). Con uguale specificità, la regola su `.button` — dopo nel foglio — impostava sfondo bianco e annullava il gradiente viola, con effetto “flash” al caricamento. I secondari usano ora `.button:not(.button-primary)` (e analogo in tema scuro).

## [1.5.41] - 2026-04-02

### Changed

- **Admin — bottoni**: nel body `fp-exp-admin-shell`, `.button-primary` / `.page-title-action` usano il **gradiente DMS** e ombra come `.fp-exp-btn-primary`; secondari con bordo `--fpdms-border` e `font-weight: 600`; stile **`.button.delete`** (es. cancella log) con token danger DMS; varianti **small/large** e stati **:focus-visible**; tema scuro allineato al gradiente viola condiviso.
- **Calendario admin**: tab vista attiva e cella “oggi” con colori `--fpdms-*`.
- **Repeater**: pulsante “aggiungi” con bordo/token DMS.
- **Classe body**: `fp-exp-admin-shell` estesa a **`edit-fp_exp_language`** (lista termini lingua esperienza).

### Added

- **CSS**: utilità **`.fp-exp-btn-secondary`** (outline coerente con `.fp-exp-btn`) per uso opzionale accanto alle classi WP.

## [1.5.40] - 2026-04-02

### Fixed

- **Admin — Impostazioni (`--layout-fp`)**: i selettori legacy legati a `.fp-exp-settings__form .form-table`, descrizioni e controlli (anche con `[data-fp-exp-admin]`) applicavano ancora box, hover sulle righe, barra `tr::before` e riquadri descrizione perché la tabella campi ha ancora la classe `form-table`. Ora quei selettori sono ristretti a **`.fp-exp-settings__form:not(.fp-exp-settings__form--layout-fp)`**, così il layout FP resta pulito; aggiunti stili espliciti per input/select/textarea nel body sezione e disattivato `::before` sulle descrizioni. Rigenerato **`fp-experiences-admin.min.css`**.

## [1.5.39] - 2026-04-02

### Changed

- **Impostazioni (dentro le tab)**: rendering Settings API con **`render_fp_settings_sections()`** — ogni sezione è una **`fp-exp-dms-card`** (header + body), campi in tabella `fp-exp-settings__fields-table` senza doppio “box” bianco; form con classe **`fp-exp-settings__form--layout-fp`**; salvataggio con **`fp-exp-btn-primary`** coerente col design system. La pagina **Email** resta sul layout legacy (senza `--layout-fp`).
- **CSS**: token `--fpdms-*`, griglia righe campo/valore, descrizioni campi senza riquadro forzato da regole `[data-fp-exp-admin]`; responsive colonna singola sotto 782px.

## [1.5.38] - 2026-04-02

### Changed

- **Impostazioni — tutte le schede**: un solo contenitore **`fp-exp-settings__tab-card`** per tab (header con dashicon + titolo testuale dalla label del nav-tab, così rispetta anche `fp_exp_settings_tabs`); corpo comune `fp-exp-settings__tab-card-body` per Tools, Booking, Logs e per il form `options.php`.
- **Tools (scheda Impostazioni)**: `render_tools_panel( false )` — niente card intro ridondante; descrizione in paragrafo sotto l’header comune. Pagina **Strumenti** dedicata invariata (`render_tools_panel()` default).

## [1.5.37] - 2026-04-02

### Changed

- **Impostazioni**: form `options.php` (tutte le schede con salvataggio) avvolto in `fp-exp-dms-card` + body; schede **Booking Rules** e **Logs** con header/card DMS e dashicon; separatore visivo tra blocco stato Google Calendar e form sulla scheda Calendar.

## [1.5.36] - 2026-04-02

### Changed

- **Admin Operazioni / Calendario**: Panoramica operatore in **due card** FP Mail-style (dashboard + KPI, poi filtri/tabella); KPI e form filtri senza stili inline, classi `fp-exp-calendar-kpi-*` e `fp-exp-operator-overview__*`.
- **Tab Calendario**: area app `#fp-exp-calendar-app` avvolta in `fp-exp-dms-card` con titolo «Calendario slot e disponibilità».
- **Tab Prenotazione manuale**: form in card con header; eventuale **Riepilogo prezzi** in sotto-card annidata.

## [1.5.35] - 2026-04-02

### Added

- **Admin UI (interno pagine)**: componenti `fp-exp-dms-card` / header / body, badge e `fp-exp-dms-fields-grid` in `admin.css` (stesso modello di FP Mail SMTP) per riuso sotto il banner.
- **Strumenti**: pannello con card introduttiva, griglia card con header (dashicon + titolo), corpo con descrizione e pulsante `fp-exp-btn-primary`; metodo `tool_action_icon_class()` per icone per slug.

### Changed

- **Strumenti**: rimossi blocchi CSS duplicati / glassmorphism su `.fp-exp-tools__card`; griglia allineata a `minmax(300px, 1fr)` e gap 1.25rem come griglia campi Mail.

## [1.5.34] - 2026-04-02

### Changed

- **Admin banner**: CSS titolo / paragrafo / badge **allineato alle stesse dichiarazioni** di `FP-Mail-SMTP/assets/css/admin.css` (niente `gap`, `line-height` o `font-weight` extra sul titolo); unica differenza strutturale documentata: breadcrumb nel banner con `margin-bottom: 0.75rem`.

## [1.5.33] - 2026-04-02

### Changed

- **Admin banner**: respiro tra breadcrumb e blocco titolo (`row-gap` / `column-gap`); `.fpexp-page-header-content` in colonna con `gap` tra titolo e descrizione (come la coppia h2+p di FP Mail); `line-height` su titolo, sottotitolo e breadcrumb; `font-weight: 600` sul titolo (design system); badge con `align-self: center` e `line-height` esplicito.

## [1.5.32] - 2026-04-02

### Changed

- **Admin banner**: stili `.fpexp-page-header` allineati a **FP Mail SMTP** (`admin.css` — stessi padding, `font-size` titolo `1.5rem`, paragrafo `0.95rem` / `opacity: 0.95`, badge `0.8rem` / `600`, `color: #fff` sul titolo come Mail); contenitore con prefisso `.fp-exp-admin-page .fpexp-page-header`; regole notice dentro `.wrap.fp-exp-admin-page` come Mail.

## [1.5.31] - 2026-04-02

### Changed

- **Admin UI — Fase 3 (primo blocco)**: form *Crea prenotazione manuale* nel Calendario senza `form-table` / `submit_button()` — griglia `fp-exp-fields-grid`, campi `fp-exp-field`, invio con `button.fp-exp-btn-primary`; hint con `fp-exp-hint`. Aggiunti stili riutilizzabili in `assets/css/admin.css`.

## [1.5.30] - 2026-04-02

### Changed

- **Admin UI — Fase 2**: classe `fp-exp-admin-page` sul `.wrap` di Impostazioni, Email, Richieste, Calendario, Tools, Logs, Guida, Onboarding, Importer, Crea pagina esperienza, Check-in e import meeting points (CSV).
- **AdminMenu**: prefisso schermate `fp-exp-dashboard_page_` (invece di `fp_exp_` solo) per enqueue CSS/JS, body `fp-exp-admin-shell` e admin bar — copre tutte le sottopagine incluso **Import Meeting Points** (`fp-exp-meeting-points-import` nel fallback `$_GET['page']`).

## [1.5.29] - 2026-04-02

### Added

- **Roadmap admin UI**: documento `docs/ADMIN-UI-ROADMAP.md` con fasi di allineamento al design system FP.

### Changed

- **Admin — Fase 1 (pilota Dashboard)**: classe `fp-exp-admin-page` sul `.wrap` della bacheca; icona `dashicons-dashboard` nel titolo del banner.
- **CSS admin**: token `:root` allineati a FP DMS (`--fpdms-*`, `--shadow-md`, `--radius-xl`, …); banner `.fpexp-page-header` conforme a `fp-admin-ui-design-system.mdc` (niente ombra/testo ombra sul gradiente, tipografia e badge versione canonici); regola `body[class*="fp-exp"]` + `.wrap.fp-exp-admin-page` per spaziatura rispetto alle notice.
- **Build**: rigenerato `assets/css/dist/fp-experiences-admin.min.css` da `admin.css`.

## [1.5.28] - 2026-03-31

### Fixed

- **Brevo contact sync (HTTP 400)**: il payload usava `array_filter($attributes)` senza callback, che in PHP rimuove anche la stringa `'0'` (consenso marketing disattivo). Con nome/cognome/telefono vuoti `attributes` diventava un array JSON `[]` invece di un oggetto `{}`, e l’API Brevo rispondeva `invalid_parameter` / *attributes should be an object*. Ora si filtrano solo `null` e stringa vuota e, se non resta nessun attributo, si invia `{}`.

## [1.5.27] - 2026-03-31

### Fixed

- **Calendario admin (Operazioni)**: nel select esperienze e nei messaggi JS comparivano entità letterali (`&#038;`, `&#039;`) perché le stringhe i18n passate a `wp_localize_script` usavano `esc_html__()` (destinato all’HTML, non al testo mostrato via `textContent`). Ora si usa `__()` per `fpExpCalendar.i18n` e i titoli esperienza sono normalizzati con decodifica entità per le liste.

## [1.5.26] - 2026-03-31

### Fixed

- **Oggetto email**: il titolo esperienza nell’header `Subject` usava `esc_html()`, che trasforma `&` in `&amp;` (testo visibile nei client come `Wine Tour &amp; Tasting`). L’oggetto non è HTML: ora il titolo viene normalizzato con testo plain (`plainTextForEmailSubject` su tutti i template che lo inseriscono nel subject).

## [1.5.25] - 2026-03-30

### Fixed

- **RTB Rifiuta**: `decline()` accettava solo `pending_request` o hold scaduto — le richieste in **Waiting payment** (`approved_pending_payment`, es. Michele Vinci con slot passato) restituivano errore e il pulsante **Rifiuta** sembrava non avere effetto. Ora il rifiuto è consentito per tutte le RTB con meta `rtb` in attesa pagamento; la prenotazione viene scollegata dall’ordine (`order_id` → 0) e l’ordine WooCommerce RTB viene messo in **cancelled** se ancora `pending` / `on-hold` / `failed` (evita che il hook ordine riporti la prenotazione a `cancelled` sovrascrivendo `declined`).
- **Auto-decline slot passato**: stessa pulizia ordine WooCommerce quando lo stato era `approved_pending_payment`.

## [1.5.24] - 2026-03-30

### Fixed

- **Cron RTB** (`fp_exp_expire_rtb_holds`): il rifiuto automatico per **slot nel passato** non veniva eseguito quando in quel tick **non** c’erano hold scaduti da cancellare (return anticipato). Ora la chiusura slot-passato gira **sempre** a fine tick, indipendentemente dagli hold.

## [1.5.23] - 2026-03-30

### Added

- **RTB — slot nel passato**: chiusura automatica in stato `declined` (motivo salvato in `rtb_decision.auto_slot_past`, `declined_by` = 0). Esecuzione a ogni tick del cron `fp_exp_expire_rtb_holds` e in batch all’apertura della pagina **Richieste**. Viene inviata la stessa email di rifiuto al cliente. Action: `fp_exp_rtb_auto_declined_past_slot`.

### Changed

- **Richieste RTB**: righe con slot passato e stato `declined` — niente campo motivo né pulsante Rifiuta; messaggio compatto (distinzione chiusura automatica vs rifiuto manuale). Checkbox disabilitata per le righe `declined`. Testo informativo aggiornato per le righe ancora chiudibili manualmente.

## [1.5.22] - 2026-03-30

### Fixed

- **Admin CSS**: `assets/css/dist/fp-experiences-admin.min.css` era **incompleto** (solo una parte delle regole rispetto a `admin.css`), quindi WordPress caricava il `.min` per primo e **mancavano** filtri richieste, banner FP e molti stili condivisi — layout FP apparentemente «rotto» su tutte le pagine admin. Il bundle minificato è stato **rigenerato** integralmente da `admin.css` (clean-css-cli).

## [1.5.21] - 2026-03-30

### Fixed

- **Richieste RTB**: colonna **Azioni** di nuovo coerente con il layout FP (rimossi override larghezza/flex sui pulsanti che creavano allineamenti errati).

### Changed

- Righe con **data/orario slot già passato**: in tabella compaiono solo nota + modulo **Rifiuta** (niente Approva, niente link pagamento). Messaggi distinti per hold scaduto vs altri stati. **`approve()`** rifiuta lato server qualsiasi approvazione se l’inizio slot è nel passato (`Reservations::is_reservation_slot_start_in_past`).

## [1.5.20] - 2026-03-30

### Changed

- Pagina **Richieste RTB**: per le righe il cui **orario di inizio slot è già passato** (rispetto all’ora del sito), tabella in **visualizzazione compatta** — cliente su una riga con separatori, stato su una riga, badge **Passata** sullo slot, azioni in riga con pulsanti più piccoli; testo lungo sull’hold scaduto sostituito da icona **info** (tooltip) se applicabile.

## [1.5.19] - 2026-03-30

### Added

- RTB con **hold scaduto** (`cancelled` da cron): da **Richieste** è di nuovo possibile **Approva** / **Rifiuta** (e azioni di gruppo). In approvazione il plugin **ricontrolla slot, party max e capacità** prima di confermare o creare l’ordine di pagamento; in `rtb_decision` viene salvato `recovered_from_expired_hold` / `after_expired_hold`.

### Changed

- Testo guida in tabella per righe hold scaduto: spiega che l’operatore può comunque gestire la richiesta.

## [1.5.18] - 2026-03-30

### Fixed

- Pagina **Richieste RTB**: le richieste scadute per hold (cron che imposta `cancelled` con `hold_expires_at` valorizzato) non comparivano più in elenco pur essendo stata inviata l’email allo staff — ora compaiono in vista «Tutti gli stati» e nel filtro dedicato **Hold scaduto (automatico)**, senza azioni Approva/Rifiuta.

### Changed

- Default **Hold timeout (minutes)** per nuove configurazioni senza valore salvato: da 30 a **1440** (24 h), più adatto alla conferma manuale operatore; testo guida in impostazioni RTB sul rischio di timeout troppo breve.

## [1.5.17] - 2026-03-25

### Fixed

- `RTBHelper::getSettings`: rimosso `error_log` con `print_r` dell’intero array opzioni (chiamata molto frequente in frontend/admin).

## [1.5.16] - 2026-03-25

### Fixed

- Admin impostazioni: rimossi `error_log` diagnostici da `enqueue_tools_assets`, `render_rtb_field` e `sanitize_rtb` (evita dump `print_r` / `$_POST` in `debug.log` con `WP_DEBUG` attivo).

## [1.5.15] - 2026-03-24

### Changed

- Brevo transactional: prima di `POST /v3/smtp/email` il payload passa da `fp_tracking_brevo_merge_transactional_tags()` se FP Marketing Tracking Layer espone la funzione (tag sito per log/sync centralizzati).

## [1.5.14] - 2026-03-24
### Changed
- Brevo contatti: con FP Tracking e Brevo abilitato, `sync_contact` usa `fp_tracking_brevo_upsert_contact()`; `is_enabled()` è true anche senza API key in `fp_exp_brevo` se il layer ha Brevo attivo. Template transazionali e `POST /v3/events` restano sulla chiave locale.

## [1.5.13] - 2026-03-24
### Changed
- Branding email: se è attivo **FP Mail SMTP** (≥ 1.2.0), `apply_branding` delega a `fp_fpmail_brand_html()`; altrimenti resta il wrapper locale da impostazioni FP Experiences.
- Email buono regalo / reminder / scaduto / riscattato, percorso `VoucherManager` e test dalla pagina Email: stesso filtro `fp_exp_email_branding` così il layout è unificato.

## [1.5.12] - 2026-03-24
### Added
- Tab Brevo → Messaggi al cliente: **tre canali** (conferma e aggiornamenti prenotazione / promemoria / follow-up post-esperienza), ciascino WordPress o Brevo — allineato a FP Restaurant Reservations (mix consentito).

### Changed
- `Brevo::is_customer_pipeline_active()` è true se **almeno un** canale è Brevo; invii transazionali e `CustomerEmailSender` usano il canale del tipo di messaggio.
- Email «data spostata» (riprogrammazione): niente più `force_send` locale forzato, così con canale Brevo non si duplica con il template Brevo.

## [1.5.11] - 2026-03-24
### Changed
- Admin tab Brevo: testi in italiano più chiari, riepilogo «a cosa serve», collegamento esplicito a **FP Marketing Tracking Layer** (chiave API e liste), note sui campi centralizzati e badge stato «Chiave da FP Tracking» quando applicabile.

## [1.5.10] - 2026-03-24
### Changed
- Brevo: canale predefinito **WordPress (wp_mail)** per le email al cliente se non salvato diversamente; la sola chiave/liste da FP Tracking non implica più delega Brevo per conferme.
- Eventi `trackEvent` e `queue_automation_events` restano attivi con integrazione Brevo abilitata anche quando il messaggio al cliente è WordPress; sync contatti invariato.

## [1.5.9] - 2026-03-24
### Added
- Tab Brevo: sezione **Messaggi al cliente** — scelta tra invio al cliente via Brevo (eventi Automation e opzionalmente template transazionali API) o **WordPress (wp_mail)**; elenco eventi `trackEvent` selezionabili; opzione solo-eventi senza SMTP API.

### Changed
- `CustomerEmailSender` rispetta il canale Brevo: con **WordPress** non delega al provider email “Brevo” così le conferme passano da `wp_mail` anche se il provider in Email è impostato su Brevo.

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
