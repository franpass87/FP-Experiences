# VERIFY-LISTING

Manual smoke-test checklist for the experiences showcase.

## Setup

1. Create at least six published `fp_experience` posts with distinct themes, languages, durations, and ticket prices. Assign primary meeting points (with latitude/longitude) to a couple of experiences.
2. Seed availability so that at least two experiences have open slots on the same calendar date and a couple have none.
3. Visit **FP Experiences → Settings → Showcase** and confirm defaults (filters enabled, price badge toggle, etc.).
4. Add the shortcode `[fp_exp_list filters="theme,language,duration,price,family,date" per_page="6" orderby="price" order="ASC" show_price_from="1" show_map="1"]` to a public “Tutte le esperienze” page.

## Behaviour

- ✅ The filter form renders with search, taxonomy selects, price range inputs, family-friendly checkbox, date picker, sort selectors, and view toggles.
- ✅ Cards display featured imagery (or gradient placeholder), highlights/description, badges (duration, language, family when relevant), “From €X” price tag, and CTA linking to the dedicated experience page (or the CPT fallback). Map link opens a Google Maps search for the meeting point.
- ✅ Active theme/language/family filters surface as removable chips above the form alongside a “Reset filters” link; removing a chip resets pagination to page 1.
- ✅ The “X experiences found” counter updates after submitting filters. Submissions use `GET` so the page can be bookmarked/shared.
- ✅ Price badge reflects the lowest ticket price (transient cached) and updates when ticket meta changes.
- ✅ Price range slider defaults to the cached min/max “price from” values; adjusting the range filters cards and keeps min/max sticky on reload.
- ✅ Pagination retains applied filters/sorting via the query string. Page reset occurs when filters are reapplied.
- ✅ Date filtering only returns experiences with open slots on the selected day (querying `fp_exp_slots`).
- ✅ Switching between grid/list views via the toggle updates the layout classes without breaking pagination or filters.
- ✅ Tracking: with consent granted, `view_item_list` fires once per page load (IDs and titles of visible cards). Clicking a card CTA or hero image fires `select_item` with the corresponding `item_id`/`item_name`.
- ✅ Asset loading: `assets/css/front.css` and `assets/js/front.js` enqueue only on pages rendering the shortcode.

## Elementor Widget

1. Insert the “FP Experiences List” widget on a test page.
2. Use the content controls to mirror the shortcode attributes (filters, ordering, map toggle, CTA target).
3. In the Style tab set different column counts for desktop/tablet/mobile, change the gap, and toggle price/badge visibility.
4. Confirm the rendered widget honours the configured layout and mirrors shortcode behaviour (filters, pagination, map links, tracking events).

## Settings

- ✅ Changing defaults under **Settings → Showcase** updates the shortcode/widget when attributes are omitted (e.g. turning off the price badge hides it on fresh embeds).
- ✅ Saving settings clears the price-from transient (`fp_exp_price_from_{id}`) so badge changes appear after editing tickets.

Document test results (pass/fail, notes) in the internal QA tracker.
