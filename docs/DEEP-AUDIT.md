# Deep Audit Log

## D6 — Experience Page Layout Refresh
- Updated the experience template wrapper to use the new `fp-hero-section` class and kept the semantic main/aside structure.
- Refined front-end CSS tokens and layout rules to deliver the two-column grid, sticky widget, and GetYourGuide-inspired styling.
- Extended `[fp_exp_page]` so editors can control container mode, max-width, gutter, and sidebar placement via shortcode attributes or the new defaults stored in `fp_exp_experience_layout`.
- Added a General settings panel (“Experience Page Layout”) with sanitisation to capture those defaults for non-technical users.
- Expanded the Elementor “FP Experience Page” widget with live-preview controls for the layout attributes and ensured it reuses the shortcode builder.
- Normalised the layout wrapper to keep the `data-layout="auto"` contract while still toggling single-column mode through the existing helper classes and sidebar data attribute.
- Updated the verification checklist/readme to cover breakout guidance and logged the resumable tracker at macro-step DOCS for this feature pass.
- Files: `templates/front/experience.php`, `assets/css/front.css`, `src/Shortcodes/ExperienceShortcode.php`, `src/Admin/SettingsPage.php`, `src/Elementor/WidgetExperiencePage.php`, `docs/VERIFY-EXPERIENCE-LAYOUT.md`, `readme.txt`, `.rebuild-state.json`, `docs/DEEP-AUDIT.md`.

## D7 — Style Polish & Tokens
- Injected the design token baseline via inline CSS so defaults load only with plugin assets and still respect branding overrides.
- Expanded the theme variable map to mirror scoped values on the shared token names (`--fp-color-*`, radius, gap, font) for auto/ dark palettes.
- Refreshed focus-visible interactions across CTAs, filters, pagination, and widget controls for consistent accessibility.
- Files: `src/Shortcodes/Assets.php`, `src/Utils/Theme.php`, `assets/css/front.css`, `docs/VERIFY-EXPERIENCE-LAYOUT.md`.

## D8 — Widget Booking Polish & Pricing Sync
- Rebuilt the booking widget summary UI to surface slot context, ticket/add-on breakdown, adjustments, and totals with explicit loading/error states.
- Added a resumable RTB quote endpoint and client-side cache so pricing always matches `Pricing::calculate_breakdown` before checkout or requests.
- Hardened checkout and RTB order flows by recalculating totals server-side from pricing rules and slot data to prevent drift.
- Files: `templates/front/widget.php`, `assets/js/front.js`, `assets/css/front.css`, `src/Booking/RequestToBook.php`, `src/Booking/Orders.php`.

## D9 — Integrations Hardening & Notices
- Gated GA4, Google Ads, Meta Pixel, and Clarity snippets behind both consent and the explicit channel toggles so saved IDs do not run unless enabled.
- Captured Brevo and Google Calendar API failures into transient notices surfaced on the Settings screen, alongside connection state summaries and template guidance.
- Logged calendar token refresh issues and Brevo delivery problems with translated, admin-visible messages without blocking production flows.
- Files: `src/Integrations/GA4.php`, `src/Integrations/GoogleAds.php`, `src/Integrations/MetaPixel.php`, `src/Integrations/Clarity.php`, `src/Integrations/Brevo.php`, `src/Integrations/GoogleCalendar.php`, `src/Admin/SettingsPage.php`.

## D10 — Listing Chips & Price Fast-Path
- Added removable chips for active theme/language/family filters with a single reset control that preserves sort/view state while resetting pagination.
- Reused cached “price from” transients to derive slider bounds and ensured the filter UI reflects adjusted min/max selections on reload.
- Updated the showcase template and styling to introduce the GetYourGuide-style chips and tidy the filter toolbar spacing.
- Files: `src/Shortcodes/ListShortcode.php`, `templates/front/list.php`, `assets/css/front.css`, `docs/VERIFY-LISTING.md`.

## D11 — Performance Tightening
- Cached taxonomy choice lookups within the listing shortcode to avoid duplicate `get_terms()` queries during a request.
- Leaned on existing transient invalidation and ensured the new chip controls reuse the cached “price from” dataset without extra database roundtrips.
- Files: `src/Shortcodes/ListShortcode.php`.

## D12 — I18N & Docs Refresh
- Extended the POT catalogue with the new admin notices, chip labels, and Brevo/Calendar error messages so translators can cover the latest UI.
- Documented the chip workflow in the verification guide and readme while keeping existing translations intact.
- Files: `languages/fp-experiences.pot`, `docs/VERIFY-LISTING.md`, `readme.txt`.

## D13 — Acceptance Log
- Reviewed the acceptance checklist to confirm the new integrations notices and listing UX do not impact the previously validated booking, tracking, and RTB flows.
- Logged the resumable state as complete for this pass (see `docs/ACCEPTANCE-TESTS.md` for historical results).

## D0 — Bootstrap & Critical Error Scan (Cycle 2)
- Ran a full PHP lint sweep and found a fatal apostrophe regression in the admin help copy introduced during the previous cycle; escaped the string to restore compatibility with PHP 8.1+.
- Re-ran the lint suite to confirm every plugin PHP file now passes without syntax errors and updated the resumable tracker to restart the cycle at D0.
- Files: `src/Admin/HelpPage.php`, `.rebuild-state.json`, `docs/DEEP-AUDIT.md`.

## D1 — Security & Data Integrity (Cycle 2)
- Locked down the checkout, RTB quote/request, and meeting-point REST endpoints with nonce-aware permission callbacks that reuse a shared helper for consistent verification.
- Localised the REST API nonce for front-end scripts and ensured RTB fetches automatically attach the `X-WP-Nonce` header alongside existing payload nonces.
- Added helper utilities to extract and validate REST nonces (with same-origin fallbacks) so future endpoints inherit the hardened behaviour without duplicating logic.
- Files: `src/Booking/Checkout.php`, `src/Booking/RequestToBook.php`, `src/MeetingPoints/RestController.php`, `src/Shortcodes/Assets.php`, `src/Utils/Helpers.php`, `assets/js/front.js`.

## D2 — Meta & Save/Render Binding (Cycle 2)
- Restored the meta normaliser helper with default fallbacks so empty fields gracefully return sanitised arrays instead of raw strings or serialised blobs.
- Swapped the booking widget back to the helper-driven highlights and languages lists, trimming the display payload while keeping the JSON config clean for analytics.
- Re-validated the shortcode registrar cache flush to ensure price and calendar transients clear whenever experiences are updated.
- Files: `src/Utils/Helpers.php`, `src/Shortcodes/WidgetShortcode.php`.

## D3 — Admin UX & Onboarding (Cycle 2)
- Reviewed the unified admin menu, meta-box tabs, and capability guards; no structural regressions surfaced after the latest shortcode/layout updates.
- Implemented the onboarding wizard page with actionable shortcuts, dismissal state, and a scoped notice to guide operators on first run.
- Normalised the booking `Tickets` helper so attendee summaries and future admin widgets can rely on sanitised, clamp-aware counts instead of TODO stubs.
- Files: `src/Admin/Onboarding.php`, `src/Plugin.php`, `src/Booking/Tickets.php`.

## D4 — Meeting Points Review (Cycle 2)
- Verified the meeting-point CPT registration, meta persistence, and REST permission callbacks; no changes were required for this pass.

## D5 — Listing Filters & Showcase (Cycle 2)
- Re-tested the shortcode filters, chip reset flow, and price caching logic; behaviour matches the documented expectations, so no edits were needed.

## D6 — Experience Layout (Cycle 2)
- Confirmed the two-column desktop grid, sticky widget behaviour, and layout controls introduced in the prior pass continue to render correctly after the helper updates.

## D7 — Style Tokens (Cycle 2)
- Ensured the inline design tokens still map to branding overrides and that focus-visible/focus-ring styles remain intact; no code adjustments were necessary.

## D8 — Booking Widget (Cycle 2)
- Exercised the pricing breakdown, RTB submission, and sticky behaviour across desktop/mobile; with the ticket helper normalised earlier, no further changes were required.

## D9 — Integrations Hardening (Cycle 2)
- Smoke-tested Brevo, Google Calendar, and marketing pixel toggles to confirm notices and consent guards still apply; no additional fixes required.

## D10 — Listing Performance (Cycle 2)
- Rechecked cache invalidation and chip filter queries to ensure no regressions; no updates were needed this round.

## D11 — Performance (Cycle 2)
- Spot-checked asset enqueue conditions and transient usage; behaviour remains scoped to plugin pages without new hot paths to adjust.

## D12 — I18N & Docs (Cycle 2)
- Added onboarding strings to the translation template, extended the deep audit, and prepared acceptance notes for the refreshed cycle.
- Files: `languages/fp-experiences.pot`, `docs/DEEP-AUDIT.md`, `docs/ACCEPTANCE-TESTS.md`.

## D13 — Acceptance (Cycle 2)
- Logged the additional desktop layout, listing, meeting-point, admin, branding, and performance verification rows in the acceptance matrix and marked the resumable tracker as complete for this pass.

## D0 — Bootstrap & Critical Error Scan (Cycle 3)
- Wrapped every module bootstrap call in a guarded executor that traps throwables, logs the failure context, and surfaces a consolidated admin notice instead of triggering a fatal during plugin load.
- Files: `src/Plugin.php`.

## D1 — Security & Data Integrity (Cycle 3)
- Re-ran the REST and nonce audit to confirm the guarded bootstrap still registers hardened callbacks for checkout, RTB, and meeting-point routes without weakening the permission gates.

## D2 — Meta & Binding Review (Cycle 3)
- Spot-checked the helper-backed meta binding for highlights, inclusions, extras, and meeting points to ensure the guard does not interfere with shortcode resolution; no code changes required.

## D3 — Admin UX & Onboarding (Cycle 3)
- Verified the onboarding wizard, admin notices, and unified menu continue to render correctly with the new bootstrap safety net.

## D4 — Meeting Points Module (Cycle 3)
- Exercised the meeting-point CPT and front-end partial with guard-induced logging enabled; behaviour remains stable so no adjustments were needed.

## D5 — Listing Showcase (Cycle 3)
- Revalidated chip filters, pagination, and cached price ranges to confirm the new guard leaves showcase functionality intact.

## D6 — Experience Layout (Cycle 3)
- Confirmed the desktop grid, sticky widget, and layout controls stay aligned with the GetYourGuide styling following the bootstrap hardening.

## D7 — Style Tokens (Cycle 3)
- Checked token injection and focus states to ensure they remain idempotent now that bootstrap retries bail out gracefully on failure.

## D8 — Booking Widget (Cycle 3)
- Smoke-tested the stepper, pricing breakdown, and sticky footer against the guarded bootstrap; no anomalies observed.

## D9 — Integrations Hardening (Cycle 3)
- Validated Brevo, Calendar, and marketing pixel toggles still emit notices and consent guards when modules opt out via the new guard.

## D10 — Listing Performance (Cycle 3)
- Rechecked transient invalidation and query scopes to make sure the guard adds no extra DB load or cache churn.

## D11 — Performance (Cycle 3)
- Measured the front-end enqueue footprint after the guard changes; asset scoping and lazy boot remain unaffected.

## D12 — I18N & Docs (Cycle 3)
- Confirmed translation extraction captures the new admin notice copy and detail formatter while keeping documentation in sync; no further strings were needed.

## D13 — Acceptance (Cycle 3)
- Updated the acceptance tracking to note the guarded bootstrap coverage and reconfirmed prior acceptance rows remain valid.

## D0 — Bootstrap & Critical Error Scan (Cycle 4)
- Extended the guarded bootstrap admin notice to fire in the network dashboard so multisite operators are alerted when a module fails hard while still keeping the plugin active.
- Files: `src/Plugin.php`.

## D1 — Security & Data Integrity (Cycle 4)
- Sanitised meeting-point REST payloads before returning them to the front end to prevent unsanitised address or contact strings from leaking into markup or scripts.
- Files: `src/MeetingPoints/RestController.php`.

## D2 — Meta & Binding Review (Cycle 4)
- Normalised the experience shortcode to honour the `sticky_widget` attribute when the sidebar is visible so widget behaviour stays in sync across the layout and widget shortcodes.
- Files: `src/Shortcodes/ExperienceShortcode.php`.

## D3 — Admin UX & Onboarding (Cycle 4)
- Re-tested the onboarding wizard and unified menu after the sticky/widget adjustments; no code changes required as dismissal flow and capability gates remain valid.

## D4 — Meeting Points Module (Cycle 4)
- Verified the sanitised REST payload feeds the meeting-point partial without breaking maps links or alternative listings.

## D5 — Listing Showcase (Cycle 4)
- Spot-checked the listing filters and cached price ranges to ensure the revised widget bindings did not introduce regressions; no edits necessary.

## D6 — Experience Layout (Cycle 4)
- Confirmed the sticky-widget toggle now respects shortcode settings across boxed, full-width, left, right, and single-column layouts.

## D7 — Style Tokens (Cycle 4)
- Checked that the singleton token injection continues to run once per request after the layout updates; focus rings and palette overrides remain intact.

## D8 — Booking Widget (Cycle 4)
- Exercised the widget in sticky and non-sticky modes via the page shortcode to confirm the config handshake and mobile CTA visibility align with expectations.

## D9 — Integrations Hardening (Cycle 4)
- Confirmed Brevo, Calendar, and marketing snippets still initialise correctly with the updated shortcode output; no changes required.

## D10 — Listing Performance (Cycle 4)
- Re-ran the listing pagination with cached price ranges to ensure no redundant widget loads occur; no code modifications needed.

## D11 — Performance (Cycle 4)
- Ensured that the additional shortcode logic does not enqueue extra assets beyond the existing guards; verified via manual inspection.

## D12 — I18N & Docs (Cycle 4)
- Documented the new cycle in the deep audit log; no translation updates were necessary because no new strings were introduced.

## D13 — Acceptance (Cycle 4)
- Reconfirmed sticky-widget scenarios in the acceptance matrix, noting the shortcode attribute fix for regression coverage.

## D0 — Bootstrap & Critical Error Scan (Cycle 5)
- Ran a full PHP lint pass across the plugin to confirm no syntax regressions surfaced since the last cycle and verified autoloader guards remain in place for bootstrap safety.
- Spot-checked action/filter registrations on the primary bootstrapped services to ensure callable visibility aligns with their registrations and no fatal edge cases are exposed during init.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`.

## D1 — Security & Data Integrity (Cycle 5)
- Hardened the calendar REST payload by sanitising date/title strings and normalising capacity arrays before sending them to clients.
- Trimmed and stripped ping responses and error messages inside the tools endpoint so administrators never receive unsanitised HTML or excessively large payloads.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `src/Api/RestRoutes.php`.

## D2 — Meta & Binding Review (Cycle 5)
- Normalised note meta to filter empty entries and pass all persisted content through `wp_kses_post` before rendering on the experience page.
- Updated the cycle tracker after confirming highlight/inclusion helpers still bind the sanitised arrays correctly.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `src/Shortcodes/ExperienceShortcode.php`.

## D3 — Admin UX & Onboarding (Cycle 5)
- Highlighted active FP Experiences screens in the admin bar using `aria-current="page"` so operators can better orient themselves while navigating the unified menu.
- Scoped the indicators to dashboard, calendar, requests, and settings views while keeping the wizard links hidden from unauthorised roles.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `src/Admin/AdminMenu.php`.

## D4 — Meeting Points Module (Cycle 5)
- Sanitised meeting point titles before caching and introduced cache invalidation hooks so edits and imports reflect immediately during the save request.
- Cleared repository caches from the meeting-point meta boxes and importer to keep linked experiences in sync with updated coordinates.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `src/MeetingPoints/Repository.php`, `src/MeetingPoints/MeetingPointMetaBoxes.php`, `src/MeetingPoints/MeetingPointImporter.php`.

## D5 — Listing Showcase (Cycle 5)
- Sanitised the experience names embedded in listing tracking payloads so data attributes remain HTML-safe even with custom titles.
- Normalised price values when emitting tracking metadata to prevent mixed numeric types in the ecommerce events.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `src/Shortcodes/ListShortcode.php`.

## D6 — Experience Layout (Cycle 5)
- Surfaced the active container mode through the wrapper `data-layout` attribute so scripts can differentiate boxed vs full-width renders.
- Introduced an additional 1280px breakpoint to ease desktop spacing and enlarge hero media without affecting tablet layouts.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `templates/front/experience.php`, `assets/css/front.css`.

## D7 — Style Tokens (Cycle 5)
- Added dedicated focus-ring variables to the theme mapper so branding overrides carry through to all interactive focus states.
- Refreshed the focus-visible rules to fall back on the new tokens, keeping accessibility contrast consistent across palettes.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `src/Utils/Theme.php`, `assets/css/front.css`.

## D8 — Booking Widget (Cycle 5)
- Sanitised the widget configuration payload before embedding it in the DOM and tightened JSON encoding with HEX flags to avoid attribute parsing issues.
- Normalised experience identifiers and URLs in the dataset so the stepper logic receives clean primitives regardless of shortcode context.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `templates/front/widget.php`.

## D9 — Integrations Hardening (Cycle 5)
- Enforced the optional Brevo webhook secret so callbacks without the configured token are rejected before logging.
- Logged invalid attempts separately to help operators diagnose external misconfigurations without exposing booking data.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `src/Integrations/Brevo.php`.

## D10 — Listing Performance (Cycle 5)
- Normalised taxonomy query parameters to sorted, sanitised arrays so cache keys remain stable regardless of selection order.
- Keeps pagination/reset URLs deterministic, aiding both server caching and analytics attribution for filtered listings.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `src/Shortcodes/ListShortcode.php`.

## D11 — Performance (Cycle 5)
- Added a small asset-version cache so repeated shortcode renders avoid redundant filesystem stats calls while registering scripts.
- Ensures front-end enqueues remain lazy while keeping token injection logic untouched for scoped themes.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `src/Shortcodes/Assets.php`.

## D12 — I18N & Docs (Cycle 5)
- Documented the Brevo webhook-secret requirement in the settings overview so operators know callbacks must include the token.
- Advanced the resumable state after verifying no new translatable strings were introduced outside existing text domains.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `readme.txt`.

## D13 — Acceptance (Cycle 5)
- Re-confirmed the Brevo integration with the webhook secret enforced alongside existing booking, layout, and listing sweeps.
- Logged the acceptance pass in the tracker to close out the cycle.
- Files: `.rebuild-state.json`, `docs/DEEP-AUDIT.md`, `docs/ACCEPTANCE-TESTS.md`.
