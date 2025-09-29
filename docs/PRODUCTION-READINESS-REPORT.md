# Production Readiness Report

## A0 Inventory
- **Layout check:** Repository includes all mandatory plugin files (loader, uninstall, src modules, templates, assets, languages, docs). Additional helpers such as `src/Admin/RequestsPage.php`, `src/Booking/RequestToBook.php`, and `src/Shortcodes/Registrar.php` extend RTB/shortcode orchestration but respect namespace `FP_Exp\`.
- **Placeholders:** Legacy `.gitkeep` markers remain in some folders (Admin, Api, Booking, Elementor, Integrations, PostTypes, Shortcodes, Utils) yet coexist with implemented files. No empty stubs detected that would block runtime.
- **File policy:** No binary assets found; assets are CSS/JS only. `.gitignore` already excludes vendor/node_modules, source maps, minified bundles, archives, and common binary types (images, fonts, PDFs).
- **Discrepancies:** Both `README.md` and `readme.txt` exist; WordPress readme follows required format while Markdown readme duplicates high-level overview — acceptable. Requests admin page exceeds baseline layout but aligns with RTB phase requirements.

Next phase: A1 — Coding standards & static review.

## A1 Coding standards & static review
- Added `phpcs.xml.dist` with PSR-12 baseline and repository exclusions to enforce consistent formatting during CI.
- Ran `php -l` across templates and removed redundant `use function` imports in front-end templates that triggered lint warnings under PHP 8.1.
- No blocking namespace or prefix violations identified; remaining style deviations (long arrays/HTML in templates) accepted as intentional for templating readability.

Next phase: A2 — Security audit.

## A2 Security audit
- Re-ran the hardening checklist on 2025-05-09, verifying nonce coverage on checkout, RTB, and REST endpoints plus capability checks for every admin page and API route.
- Confirmed sanitisation/escaping for request payloads and ensured SQL interactions continue to flow through `$wpdb->prepare`; no regressions detected in the rate-limiting guards or cache-control headers on sensitive endpoints.
- Revalidated masked logging for contact details and confirmed API secrets remain confined to protected options without leaking to logs or front-end output.

Next phase: A3 — Privacy & GDPR.

## A3 Privacy & GDPR
- Confirmed checkout captures marketing consent and persists it to WooCommerce order meta (`_fp_exp_consent_marketing`) and Brevo payloads only when the user opts in.
- Verified UTM propagation uses the `fp_exp_utm` cookie with sanitised keys and is stored privately on reservations/orders without front-end exposure.
- Documented privacy practices in `readme.txt`, covering data storage, consent handling, API credentials, and export/erasure flows through WooCommerce tools.

Next phase: A4 — Performance review.

## A4 Performance
- Verified shortcode assets enqueue only when components render, keeping unrelated pages free of plugin CSS/JS.
- Confirmed REST availability responses send `no-store` headers and admin rate limiting protects heavy endpoints.
- Indexed custom tables align with query filters, minimising full scans during slot/reservation lookups.

Next phase: A5 — Accessibility & UX.

## A5 Accessibility & UX
- Audited widget, calendar, and checkout templates for focus states and live region usage; confirmed quantity controls and calendars expose descriptive `aria-label`s.
- Enhanced the RTB feedback container with `role="status"` so assistive tech announces approval/decline updates and rechecked the focus trap/error summary behaviour introduced during the accessibility patch — both remain functional in the 2025-05-09 sweep.
- Recommended adding future enhancements (modal refinements, extended keyboard shortcuts) to backlog for future releases.

Next phase: A6 — Tracking & consent.

## A6 Tracking & consent
- Confirmed GA4, Google Ads, Meta Pixel, and Clarity scripts respect per-channel consent toggles before enqueueing.
- Documented the RTB-specific dataLayer events (`fpExp.request_*`) and the ecommerce events emitted through front.js and thank-you page integrations.
- Verified UTM propagation flows from the visitor cookie to reservations, orders, and Brevo contact attributes/tags.

Next phase: A7 — Integrations.

## A7 Integrations
- Confirmed Brevo REST callbacks, transactional fallbacks, and consent-aware contact sync remain operational; webhook payloads are scrubbed before logging.
- Verified Google Calendar OAuth flow, token persistence, and event lifecycle hooks (create/update/delete) tied to reservation approvals and payments.
- Noted scaffolded `src/Api/Webhooks.php` remains unused; flagged as future enhancement rather than modifying production behaviour.

Next phase: A8 — Functional smoke tests.

## A8 Functional smoke tests
- Reviewed the existing acceptance log (A1–A16) confirming coverage across isolated checkout, RTB lifecycle, integrations, and admin tooling. No new regressions identified during audit; manual re-run deferred to dedicated staging per process.

Next phase: A9 — Docs & release checklist.

## A9 Docs & release readiness
- Refreshed the build playbook to include Phase 0 and the dedicated RTB Phase 4B while clarifying the final acceptance step.
- Updated the changelog status to reflect completion of the production readiness audit for version 0.1.0.
- Added a comprehensive release checklist capturing binary policy, isolation guarantees, integrations, QA evidence, and pre-PR checks.
- Expanded `readme.txt` with a Privacy section detailing data handling, consent, and credential storage.

Audit complete.

## Pre-PR Check — 2025-05-09
- **Files touched this round:** `docs/ACCEPTANCE-TESTS.md`, `docs/PRODUCTION-READINESS-REPORT.md`.
- **Binary policy:** confirmed only text assets present; `git status --short` shows no binary additions and `.gitignore` still blocks vendor, node_modules, maps, minified bundles, archives, and common binaries.
- **Lint status:** `php -l src/Plugin.php` / `php -l src/Admin/SettingsPage.php` and `node --check assets/js/front.js` / `node --check assets/js/checkout.js` all pass for syntax verification.
- **Outstanding TODOs:** backlog remains limited to future enhancements (modal refinements, extended REST tooling) — none are blocking this release.
