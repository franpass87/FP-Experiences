# Inventario schermate admin — FP Experiences

Riferimento per revisione UI (design system FP: `fpexp-page-header`, `fp-exp-admin` + `data-fp-exp-admin`, token `--fpdms-*`, body `fp-exp-admin-shell`).

## Pagine custom (menu FP Experiences)

| Voce menu | Slug / screen | Classe wrap | Banner | Note |
|-----------|---------------|-------------|--------|------|
| Dashboard (manager) | `fp_exp_dashboard` | `fp-exp-dashboard` | `fpexp-page-header` | KPI, agenda, export |
| Dashboard (solo guida) | idem | `fp-exp-help` | sì | Chi non gestisce vede Help |
| Impostazioni | `fp_exp_settings` | `fp-exp-settings` | sì | Tab `fp-exp-tabs` + form `--layout-fp` |
| Email | `fp_exp_emails` | `fp-exp-emails` | sì | Tab `fp-exp-tabs`; form legacy senza `--layout-fp` |
| Strumenti | `fp_exp_tools` | `fp-exp-tools` | sì | Card grid azioni REST |
| Log | `fp_exp_logs` | `fp-exp-logs` | sì | Filtri + tabella + delete |
| Guida & shortcode | `fp_exp_help` | `fp-exp-help` | sì | Documentazione |
| Calendario / Operazioni | `fp_exp_calendar` | `fp-exp-calendar` | sì | Tab viste + **`fp-exp-operator-nav`** |
| Richieste RTB | `fp_exp_requests` | `fp-exp-requests` | sì | Tabella + **`fp-exp-operator-nav`** |
| Check-in | `fp_exp_checkin` | `fp-exp-checkin` | sì | QR + operatori + **`fp-exp-operator-nav`** |
| Importer | `fp_exp_importer` | `fp-exp-importer-page` | sì | CSV |
| Crea pagina esperienza | `fp_exp_create_page` | `fp-exp-page-creator` | sì | Form semplice |
| Ordini | `fp_exp_orders` | — | — | **Redirect** a `edit.php?post_type=shop_order` filtrato |

## Schermate WordPress (CPT / tassonomie)

| Schermata | Screen ID tipico | `fp-exp-admin-shell` | CSS admin plugin |
|-----------|------------------|------------------------|------------------|
| Lista esperienze | `edit-fp_experience` | sì | sì |
| Modifica esperienza | `fp_experience` | sì | sì + meta box `data-fp-exp-admin` |
| Lista meeting point | `edit-fp_meeting_point` | sì | sì |
| Modifica meeting point | `fp_meeting_point` | sì | sì |
| Lista gift voucher | `edit-fp_exp_gift_voucher` | sì (da 1.5.44) | sì |
| Modifica gift voucher | `fp_exp_gift_voucher` | sì (da 1.5.44) | sì |
| Lingue esperienza (tassonomia) | `edit-fp_exp_language` | sì | sì |

## Pattern HTML obbligatori (pagine custom)

1. `.wrap.fp-exp-admin-page` + `h1.screen-reader-text` per accessibilità e notice WP.
2. `.fp-exp-admin[data-fp-exp-admin]` > `.fp-exp-admin__body` > `.fp-exp-admin__layout` (+ slug contestuale).
3. Banner: `.fpexp-page-header` con breadcrumb, `.fpexp-page-header-content`, badge versione.

## Navigazione a tab

- **Impostazioni / sotto-tab calendario**: `div.fp-exp-tabs.nav-tab-wrapper` + link `.nav-tab`.
- **Email**: stesso pattern.
- **Viste calendario** (overview / calendario / manuale): stesso pattern dentro pagina Calendario.
- **Operatore** (salti tra Calendario, RTB, Check-in, Ordini): `nav.fp-exp-operator-nav.nav-tab-wrapper` — stessi stili DMS delle altre tab (CSS condiviso con `.fp-exp-tabs`).

## Meta box «Impostazioni esperienza» (editor `fp_experience`)

Markup: `.fp-exp-admin[data-fp-exp-admin]` con `role="tablist"` e pulsanti `.fp-exp-tab` (`role="tab"`, `data-tab="{slug}"`). Ordine definito in `ExperienceMetaBoxes::get_tab_labels()`.

| Slug `data-tab` | Etichetta (IT) | Handler |
|-----------------|----------------|---------|
| `details` | Dettagli | `DetailsMetaBoxHandler` |
| `pricing` | Biglietti & Prezzi | `PricingMetaBoxHandler` |
| `calendar` | Calendario & Slot | `CalendarMetaBoxHandler` |
| `meeting-point` | Meeting Point | `MeetingPointMetaBoxHandler` |
| `extras` | Extra | `ExtrasMetaBoxHandler` |
| `policy` | Policy/FAQ | `PolicyMetaBoxHandler` |
| `seo` | SEO/Schema | `SEOMetaBoxHandler` |

**Nota:** se è attivo un plugin SEO riconosciuto da `ExperienceMetaBoxes::is_seo_plugin_active()` (es. FP SEO Manager / FP SEO Performance), il tab **`seo`** non viene mostrato e il pannello non viene renderizzato; in quel caso controllare solo le **sei** tab rimanenti.

## Checklist verifica (tracciamento release)

Sessione di controllo sistematico su **https://fp-development.local** (2026-04-02), utente con capacità di gestione FP Experiences (`fp_exp_manage`). Criteri: caricamento pagina senza fatal, titolo schermata coerente, **nessun errore console attribuibile a script FP Experiences** (restano warning WP core tipo JQMIGRATE e, nell’editor blocco, errori deprecazione blocchi di altri plugin: FP Privacy, WPML, ecc.).

**Struttura DOM** (`.wrap.fp-exp-admin-page`, `h1.screen-reader-text`, `body.fp-exp-admin-shell`, notice fuori dal banner): non ispezionata elemento-per-elemento in questa sessione; si assume allineamento all’inventario sopra e ai template correnti. Per regressioni UI usare DevTools su una pagina campione.

**Non eseguito in sessione (prima tornata)**: salvataggio form su ogni tab Impostazioni/Email; esecuzione azione REST dalla pagina Tools; scenario “dashboard solo guida” (utente senza `manage`); click sistematico su ogni tab del meta box esperienza.

**Follow-up meta box (2026-04-03)**: dopo ripristino sito (fix parse error plugin FP), smoke su **editor esperienza** `post.php?post=10&action=edit` — pagina **200**, titolo coerente. L’albero accessibilità del browser integrato non espone i pulsanti `.fp-exp-tab` come controlli cliccabili (`interactive refs: 0`), ma risultano presenti nel DOM le etichette/contenuti attesi: **Dettagli** (`tabpanel`), sezioni **Biglietti** / prezzo gruppo / **Addon**, **slot** (“Aggiungi slot orario”, durata slot), **Meeting Point** principale/alternativi, **FAQ** (“Aggiungi FAQ”). Tab **SEO/Schema** del plugin nascosto con FP SEO attivo (comportamento documentato sopra).

**Ripetizione smoke (2026-04-03, browser sequenziale — niente navigazioni parallele)**: confermati **PASS** (titolo documento WP coerente, URL atteso, assenza di schermata bianca o messaggio di fatal PHP visibile) per `fp_exp_dashboard`, `fp_exp_emails&tab=brevo`, Calendario con `view=overview`, `view=calendar`, `view=manual`, `fp_exp_requests`, `fp_exp_checkin`, lista esperienze, **Nuova esperienza** (`post-new.php?post_type=fp_experience`), modifica esperienza `post=10`, lista meeting point, lista gift voucher, tassonomia lingue (`edit-tags.php?taxonomy=fp_exp_language&post_type=fp_experience`).

| Pagina / contesto | Tab o vista | Data | Esito | Note |
|-------------------|-------------|------|-------|------|
| Dashboard manager | — | 2026-04-02 | PASS | Titolo “FP Experiences”; URL `fp_exp_dashboard`. |
| Guida & shortcode | — | 2026-04-02 | PASS | `fp_exp_help`. |
| Strumenti | — | 2026-04-02 | PASS | `fp_exp_tools`; azione card non invocata. |
| Log | — | 2026-04-02 | PASS | `fp_exp_logs`. |
| Importer | — | 2026-04-02 | PASS | `fp_exp_importer`. |
| Crea pagina esperienza | — | 2026-04-02 | PASS | `fp_exp_create_page`. |
| Ordini (redirect WC) | — | 2026-04-02 | PASS | Redirect a `admin.php?page=wc-orders&fp_exp_filter=experiences`. |
| Impostazioni | general | 2026-04-02 | PASS | |
| Impostazioni | gift | 2026-04-02 | PASS | |
| Impostazioni | branding | 2026-04-02 | PASS | |
| Impostazioni | booking | 2026-04-02 | PASS | |
| Impostazioni | calendar | 2026-04-02 | PASS | |
| Impostazioni | tracking | 2026-04-02 | PASS | |
| Impostazioni | rtb | 2026-04-02 | PASS | RTB visibile in menu (modulo non “off”). |
| Impostazioni | webhook | 2026-04-02 | PASS | |
| Impostazioni | listing | 2026-04-02 | PASS | |
| Impostazioni | tools | 2026-04-02 | PASS | |
| Impostazioni | logs | 2026-04-02 | PASS | |
| Email | senders | 2026-04-02 | PASS | |
| Email | branding | 2026-04-02 | PASS | |
| Email | config | 2026-04-02 | PASS | |
| Email | previews | 2026-04-02 | PASS | Contenuto esteso (anteprime). |
| Email | brevo | 2026-04-02 | PASS | Nessun fatal con tab aperta. |
| Calendario | view=overview | 2026-04-02 | PASS | Link operatore Calendario / Richieste / Check-in / Ordini e tab Panoramica / Calendario / Prenotazione manuale presenti nello snapshot. |
| Calendario | view=calendar | 2026-04-02 | PASS | |
| Calendario | view=manual | 2026-04-02 | PASS | |
| Richieste RTB | — | 2026-04-02 | PASS | `fp_exp_requests`; barra operatore attesa come su Calendario. |
| Check-in | — | 2026-04-02 | PASS | `fp_exp_checkin`. |
| Lista esperienze | — | 2026-04-02 | PASS | `edit.php?post_type=fp_experience`. |
| Lista meeting point | — | 2026-04-02 | PASS | `edit.php?post_type=fp_meeting_point`. |
| Lista gift voucher | — | 2026-04-02 | PASS | `edit.php?post_type=fp_exp_gift_voucher`. |
| Tassonomia lingue | — | 2026-04-02 | PASS | `edit-tags.php?taxonomy=fp_exp_language&post_type=fp_experience`. |
| Modifica esperienza | meta `details` | 2026-04-02 / 03 | PASS | `post.php?post=10&action=edit`; tabpanel Dettagli. |
| Modifica esperienza | meta `pricing` | 2026-04-03 | PASS | Contenuti Biglietti / prezzo gruppo visibili in snapshot a11y (stesso caricamento editor). |
| Modifica esperienza | meta `calendar` | 2026-04-03 | PASS | Testi slot (“Aggiungi slot orario”, durata slot) presenti. |
| Modifica esperienza | meta `meeting-point` | 2026-04-03 | PASS | MP principale e alternativi in snapshot. |
| Modifica esperienza | meta `extras` | 2026-04-03 | PASS | Sezione Addon / capacità per slot in snapshot. |
| Modifica esperienza | meta `policy` | 2026-04-03 | PASS | FAQ (“Aggiungi FAQ”) in snapshot. |
| Modifica esperienza | meta `seo` | — | N/A* | Tab FP Experiences nascosto con FP SEO attivo. |
| Dashboard solo guida | — | — | N/A | Richiede utente senza `fp_exp_manage`; da ripetere in QA dedicato. |
