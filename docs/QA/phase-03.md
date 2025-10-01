# Phase 3 – Recurring slots + time sets

## QA checklist

- [x] Enabled the recurrence toggle on an experience, defined two time sets, and saved; reloaded editor shows the sets intact.
- [x] Clicked “Anteprima ricorrenza” and confirmed the preview lists future slots matching the selected days/times without touching past events.
- [x] Triggered “Rigenera slot da RRULE” on a published experience and verified new slots were created without duplicating existing entries.
- [x] Attempted to preview with no times and received the inline validation warning without hitting the REST endpoint.
- [x] Confirmed weekly recurrences honour the selected weekday chips and daily recurrences hide the day selector.
