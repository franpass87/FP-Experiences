# Inventario UI admin FP Experiences vs design system FP

Riferimento: `.cursor/rules/fp-admin-ui-design-system.mdc`. Stato: **OK** = conforme; **GAP** = migliorato in questa passata o da monitorare.

| Schermata | Box / area | Stato | Note |
|-----------|------------|-------|------|
| Metabox `fp_experience` | Tab + shell `.fp-exp-experience-metabox` | OK | Tabs, sticky head |
| Metabox | Sezioni `.fp-exp-dms-card` (MetaBoxHelpers) | OK | Header grigio chiaro, body |
| Metabox | Sotto-blocchi `.fp-exp-pricing-block` | OK | Allineati a token DMS (bordo, raggio, sfondo) |
| Metabox | Repeater `.fp-exp-repeater__item` | OK | Bordi/raggio DMS |
| Metabox | Checkbox grid, trust, lingue | OK | Già modulare |
| Dashboard | Banner `.fpexp-page-header` | OK | CSS unificato senza ombra banner |
| Dashboard | Metric cards, sezioni | OK | Token bordo/raggio DMS in layout; tabelle agenda/ordini in `.fp-exp-table-shell` |
| Impostazioni | Banner + `.fp-exp-settings` | OK | Tab attivo con primario DMS |
| Impostazioni | Card sezioni / `form-table` wrappati | OK | Anteprima log: `widefat` in `.fp-exp-settings__embedded-table` |
| Calendario admin | Banner + operazioni | OK | Stessi token banner |
| Request-to-Book | Banner + tabella | OK | Filtri in card DMS; tabella in `.fp-exp-table-shell` |
| Check-in | Banner + console | OK | Scanner/operatori mobile senza inline style; tabelle in shell |
| Log | Banner + filtri/tabella | OK | Toolbar card; diagnostica in card; `.fp-exp-logs__context-pre` |
| Strumenti / Email / Importer / Guida | Banner + contenuto | OK | |
| Onboarding / Crea pagina | Banner + contenuto | OK | |
| Lista gift voucher | Banner JS + `.fp-exp-gift-voucher-list` | OK | Classi banner condivise |
| Ordini esperienze | Redirect WC | N/A | Nessuna UI dedicata |
| Taxonomia lingue | Lista termini core + badge | OK | `LanguageAdmin` decora nome |
| CPT lista esperienze | `cpt-list-shell.css` | OK | Shell esistente |

## Azioni applicate (changelog plugin)

- `page-header-dms.css`: unico blocco banner, senza `box-shadow` sul gradiente, metriche DS.
- `layout.css`: card dashboard e sezioni con token `--fpdms-*`; `margin-top` su `.wrap.fp-exp-admin-page` in shell.
- `metabox-details.css`: pricing block e repeater con token DMS.
- `settings.css`: tab attivi allineati al primario DMS.
- `variables.css`: nota su convivenza token `--fp-exp-*` (metabox) e `--fpdms-*` (design system).
- `listing-pages.css`: `.fp-exp-table-shell`, toolbar log, filtri richieste, pannelli check-in (QR / operatori mobile).
