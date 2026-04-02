# Roadmap admin UI — FP Experiences

Allineamento progressivo a `fp-admin-ui-design-system.mdc` (token FP DMS, banner FP Mail SMTP, componenti, notice WordPress).

## Fase 1 — Fondamenta + pagina pilota (Dashboard)

- Token `:root` condivisi (`--fpdms-*`, `--shadow-md`, `--radius-xl`, …) oltre alle variabili legacy `--fp-exp-*` dove ancora necessarie.
- Banner `.fpexp-page-header` allineato al canone: niente `box-shadow` / `text-shadow` sul gradiente; tipografia e badge versione come da design system.
- Regola `body[class*="fp-exp"] #wpbody-content > .wrap.fp-exp-admin-page` per spaziatura rispetto alle notice.
- **Pagina pilota**: Dashboard (`fp_exp_dashboard`) — classe `fp-exp-admin-page` sul `.wrap` + icona `dashicons-dashboard` nel titolo banner.

## Fase 2 — Wrapper unificato

- Aggiungere `fp-exp-admin-page` a tutte le altre pagine admin del plugin (Impostazioni, Calendario, Richieste, Email, Tools, Logs, Guida, ecc.).

## Fase 3 — Form e azioni

- Sostituire progressivamente `form-table` e `submit_button()` con `fp-exp-fields-grid` e `fp-exp-btn` (iniziare da Calendario / sezioni più piccole).

## Fase 4 — Tabelle e card

- Liste operative su tabella stile `fp-exp-table` (thead gradiente); card sezioni con header grigio chiaro e ombra `--shadow-md`.

## Riferimenti

- `.cursor/rules/fp-admin-ui-design-system.mdc`
- Esempi: FP Mail SMTP (`assets/css/admin.css`), FP Marketing Tracking Layer
