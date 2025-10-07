## FP Experiences – Frontend Modular Guide

Questa guida descrive l’architettura modulare del frontend e come estendere/sostituire i moduli `FPFront.*`.

### Obiettivi
- **Chiarezza**: responsabilità singola per modulo
- **Estendibilità**: API stabili su `window.FPFront`
- **Compatibilità**: nessun bundler richiesto, concatenazione tramite build interno

### Mappa dei moduli
- `FPFront.availability`
  - API: `init({ config, calendarEl, widget })`, `formatTimeRange`, `fetchAvailability`, `prefetchMonth`, `monthKeyOf`, `getCalendarMap`
- `FPFront.slots`
  - API: `init({ slotsEl })`, `renderSlots(items)`, `clearSelection()`, `getSelectedSlot()`
- `FPFront.calendar`
  - API: `init({ calendarEl, widget })`
- `FPFront.quantity`
  - API: `init({ widget })`
- `FPFront.summaryRtb`
  - API: `init({ widget, slotsEl, config })`
- `FPFront.summaryWoo`
  - API: `init({ widget, slotsEl, config })`

### Ordine di inizializzazione consigliato
1. `FPFront.availability.init({ config, calendarEl, widget })`
2. `FPFront.calendar.init({ calendarEl, widget })`
3. `FPFront.slots.init({ slotsEl })`
4. `FPFront.quantity.init({ widget })`
5. Riepilogo: 
   - RTB: `FPFront.summaryRtb.init({ widget, slotsEl, config })`
   - Woo: `FPFront.summaryWoo.init({ widget, slotsEl, config })`

Il bootstrap in `assets/js/front.js` applica già questo flusso in modo sicuro.

### Estendere o sovrascrivere un modulo
Puoi sostituire metodi in runtime prima della loro inizializzazione oppure dopo, se l’API lo consente.

Esempio: override di `formatTimeRange` (locale a 12h e fallback custom)
```javascript
window.FPFront = window.FPFront || {};
window.FPFront.availability = window.FPFront.availability || {};
window.FPFront.availability.formatTimeRange = function(startIso, endIso) {
  try {
    const opts = { hour: 'numeric', minute: '2-digit', hour12: true };
    const s = new Intl.DateTimeFormat(undefined, opts).format(new Date(startIso));
    const e = new Intl.DateTimeFormat(undefined, opts).format(new Date(endIso));
    return `${s} – ${e}`;
  } catch (e) {
    return 'Slot';
  }
};
```

### Aggiungere un nuovo modulo
1. Crea `assets/js/front/<nome>.js` ed esporta in `window.FPFront.<nome>` con `init(ctx)`.
2. Aggiungi il file in `build-config.js` sotto `js.frontend` nell’ordine desiderato.
3. Inizializza dal bootstrap (`front.js`) o da un modulo esistente.

### Debug
- Tutti i moduli sono plain JS, facili da ispezionare nel browser.
- Usa `console.debug('[FP-EXP] ...')` durante lo sviluppo; il build prod può sopprimere i log.

### Note di compatibilità
- Evitare dipendenze hard su librerie globali: jQuery è usato solo per il `document.ready` nel bootstrap.
- Gli endpoint REST rimangono invariati; le funzioni che li chiamano sono in `availability` e nel flusso Woo.


