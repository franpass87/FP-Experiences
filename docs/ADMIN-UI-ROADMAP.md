# Roadmap admin UI — FP Experiences

Allineamento progressivo a `fp-admin-ui-design-system.mdc` (token FP DMS, banner FP Mail SMTP, componenti, notice WordPress).

## Fase 1 — Fondamenta + pagina pilota (Dashboard)

- Token `:root` condivisi (`--fpdms-*`, `--shadow-md`, `--radius-xl`, …) oltre alle variabili legacy `--fp-exp-*` dove ancora necessarie.
- Banner `.fpexp-page-header` allineato al canone: niente `box-shadow` / `text-shadow` sul gradiente; tipografia e badge versione come da design system.
- Regola `body[class*="fp-exp"] #wpbody-content > .wrap.fp-exp-admin-page` per spaziatura rispetto alle notice.
- **Pagina pilota**: Dashboard (`fp_exp_dashboard`) — classe `fp-exp-admin-page` sul `.wrap` + icona `dashicons-dashboard` nel titolo banner.

## Fase 2 — Wrapper unificato *(completata v1.5.30)*

- `fp-exp-admin-page` sul `.wrap` di: Impostazioni, Email, Richieste, Calendario, Tools, Logs, Guida, Onboarding, Importer esperienze, Crea pagina esperienza, Check-in, Import meeting points (CSV).
- **Enqueue**: slug `fp-exp-meeting-points-import` incluso nel fallback `$_GET['page']` così il CSS admin carica anche su quella schermata.

## Fase 3 — Form e azioni *(in corso)*

- **Fatto (v1.5.31)**: form *Crea prenotazione manuale* in Calendario — `fp-exp-fields-grid`, `fp-exp-field`, `fp-exp-btn-primary`; CSS condiviso in `admin.css`.
- **Da fare**: altri `form-table` / `submit_button()` (Impostazioni tab, Richieste filtri, ecc.).

## Fase 4 — Tabelle e card

- Liste operative su tabella stile `fp-exp-table` (thead gradiente); card sezioni con header grigio chiaro e ombra `--shadow-md`.

## Riferimenti

- `.cursor/rules/fp-admin-ui-design-system.mdc`
- Esempi: FP Mail SMTP (`assets/css/admin.css`), FP Marketing Tracking Layer
