# Phase 05 — Auto page creation QA

## Admin
- [x] Publishing a new experience without a linked page auto-generates a WordPress page with the `[fp_exp_page id="{ID}"]` shortcode.
- [x] The experience metabox shows the “Vedi pagina”/“Modifica pagina” controls once the page exists, with the current status label.
- [x] Editing an existing experience without republishing leaves the linked page intact (no duplicate pages created).

## Tools
- [x] Triggered **Tools → Risincronizza pagine esperienza** and confirmed missing pages are created plus a success message summarises checked/created counts.
- [x] Running the tool again within the rate limit returns the cooldown notice.
- [x] Logbook records the page resync execution with checked/created metrics.

## Front-end
- [x] The auto-generated page renders the full experience layout (hero, highlights, widget) and inherits the theme’s full-width template when available.
- [x] `[fp_exp_list]` cards pick up the linked page URL immediately after publish.

## Regression
- [x] Manual **Crea pagina esperienza** tool continues to work and respects the new linked page meta.
