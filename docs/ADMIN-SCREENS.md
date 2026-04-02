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
