# FP Experiences admin menu

_Updated: unify plugin links under a single top-level menu._

## Struttura

- **FP Experiences → Dashboard** (`fp_exp_manage`)
  - KPI prenotazioni, riepilogo ordini, azioni rapide.
- **Esperienze / Nuova esperienza** (`edit_fp_experiences`)
  - CPT `fp_experience` gestito interamente sotto il nuovo menu.
- **Meeting point** (`fp_exp_manage`)
  - Visibile solo quando l'opzione “Enable Meeting Points” è attiva.
- **Calendario** (`fp_exp_operate`)
  - Vista calendario/manual booking.
- **Richieste** (`fp_exp_operate`)
  - Appare solo con RTB attivo.
- **Check-in** (`fp_exp_operate`)
  - Console per confermare arrivi nelle prossime 48h.
- **Ordini** (`fp_exp_manage` + `manage_woocommerce`)
  - Reindirizza alla lista ordini Woo filtrata sugli item `fp_experience_item`.
- **Impostazioni / Tools / Logs** (`fp_exp_manage`)
  - Tabs aggiornati: Generale, Branding, Booking Rules, Brevo, Calendar, Tracking, RTB, Vetrina, Tools, Logs.
- **Guida & Shortcode** (`fp_exp_guide`)
  - Documentazione interna e scorciatoie.
- **Crea pagina esperienza** (`fp_exp_manage`)
  - Genera una pagina WordPress con `[fp_exp_page id="{ID}"]`.

La toolbar di WordPress aggiunge il nodo “FP Experiences” con link rapidi a Nuova esperienza, Calendario, Richieste (se abilitate) e Impostazioni.

## Flussi rapidi

1. **Operatore** accede, apre **FP Experiences → Calendario** e gestisce slot o richieste senza vedere le impostazioni avanzate.
2. **Manager** consulta la **Dashboard** per KPI giornalieri, poi passa a **Impostazioni** per modificare branding/integrazioni.
3. **Marketing/Guide** trovano in **Guida & Shortcode** gli snippet pronti da copiare nelle pagine.

## Asset

- Gli asset `assets/css/admin.css` e `assets/js/admin.js` vengono caricati solo quando `get_current_screen()->id` contiene `fp-exp_page_` o `toplevel_page_fp_exp_dashboard`.
- Le pagine Tools usano le stesse chiamate REST della scheda Tools.

## Screenshot

```
[Screenshot placeholder – Admin → FP Experiences]
```
