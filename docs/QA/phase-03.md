# Phase 3 – Recurring slots + time sets

> Consulta anche la [full regression checklist](./full-regression.md) per verificare gli
> aggiornamenti correlati all'esperienza prima di passare ai controlli specifici della
> ricorrenza.

## QA checklist

- [x] Compilata la sezione “Ricorrenza slot” scegliendo la frequenza desiderata, impostando almeno un giorno attivo se settimanale e aggiungendo due set orari; dopo il salvataggio l’editor mostra i dati invariati.
- [x] Clicked “Anteprima ricorrenza” and confirmed the preview lists future slots matching the selected days/times without touching past events.
- [x] Triggered “Genera slot ricorrenti” on a published experience and verified new slots were created without duplicating existing entries.
- [x] Attempted to preview with no times and received the inline validation warning without hitting the REST endpoint.
- [x] Confirmed weekly recurrences honour the selected weekday chips and daily recurrences hide the day selector.
