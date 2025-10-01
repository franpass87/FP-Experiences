# Phase 06 – Simple archive & wider desktop layout

## Manual QA
- [ ] Insert `[fp_exp_simple_archive]` on a blank page and confirm it renders published experiences with thumbnails, duration, and "Dettagli"/"Prenota" CTA buttons.
- [ ] Toggle the shortcode `view` attribute between `grid` and `list` to verify responsive layouts, lazy-loaded imagery, and preserved CTA focus styles.
- [ ] Switch the Elementor “FP Experiences List” widget to **Simple** mode, adjust the column count, and confirm the preview updates along with the CTA buttons and price badges.
- [ ] Return the Elementor widget to **Advanced** mode and confirm the filters/map controls reappear with the wider desktop container applied on the front-end.
- [ ] Smoke test the advanced `[fp_exp_list]` shortcode to ensure filters, pagination, and ordering still function with the new layout spacing.

## Regression
- [ ] Verify meeting point import toggle remains hidden by default.
- [ ] Confirm add-on image pickers still save and display thumbnails in the booking widget.
- [ ] Ensure recurring slot regeneration preview continues to function with linked time sets.
