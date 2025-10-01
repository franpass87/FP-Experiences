# Phase 04 — Language flags QA

## Admin
- [x] Verified the “Lingue disponibili” field renders flag chips matching the saved values.
- [x] Confirmed taxonomy screen **Esperienze → Lingue** shows inline SVG flags beside each term.
- [x] Checked admin CSS loads on the taxonomy page without duplicate enqueues.

## Front-end
- [x] Experience hero badges show individual language flags with ISO codes and screen-reader text.
- [x] Listing cards render language badges per language with lazy SVG sprite usage.
- [x] Booking widget meta displays flags + codes for the configured languages.

## Accessibility/Performance
- [x] Ensured fallback globe icon renders when an unmapped language is entered.
- [x] Confirmed language badges keep visible text (no icon-only disclosure).
- [x] Observed only the shared SVG sprite is requested (no extra HTTP requests per badge).
