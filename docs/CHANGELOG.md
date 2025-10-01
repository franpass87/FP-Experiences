# Changelog

## [Unreleased]
- No changes yet.

## [0.3.0] - 2025-09-30
- Added an advanced setting to toggle the meeting point CSV import tool (disabled by default) and clarified the admin visibility rules.
- Enabled image selection for experience add-ons with media previews in the editor and responsive thumbnails on the booking widget.
- Repaired recurring slot generation by linking RRULEs to time sets, adding previews, and exposing regeneration controls in the calendar tools.
- Added ISO-based language flags with accessible labels across admin taxonomy screens, the experience editor preview, and front-end cards/widget badges.
- Auto-generated dedicated WordPress pages on publish (with a Tools resync command) so every experience has a linked `[fp_exp_page]` destination.
- Introduced a wide simple archive layout via the `[fp_exp_simple_archive]` shortcode and Elementor mode toggle, complete with responsive grid/list cards, CTA buttons, and refreshed desktop spacing for the advanced listing.
- Launched the “Gift Your Experience” workflow with settings, voucher CPT/manager, REST endpoints, reminder cron, front-end purchase form, and the `[fp_exp_gift_redeem]` shortcode for zero-cost redemption.
- Added a migration runner for add-on image metadata and the gift voucher summary table with automatic backfill on upgrade.
- Expanded front-end tracking with `add_on_view`, enriched `gift_purchase`/`gift_redeem` dataLayer events, refreshed release documentation, and closed the QA checklist for v0.3.0.

## [0.2.0] - 2025-09-29
- Polish UI/UX stile GetYourGuide (layout 2-col, sticky, chips).
- Bugfix: fallback ID shortcode, flush transients, no-store headers.
- Admin menu unificato + “Crea Pagina Esperienza”.
- Listing con filtri e “price from”.
- Hardened hooks/REST/nonce (no WSOD).

## [0.1.0] - 2024-05-01
_Status: production readiness audit complete._

- Added Brevo transactional email support with contact sync and webhook capture.
- Integrated Google Calendar sync with OAuth token refresh and order meta linkage.
- Implemented marketing tracking toggles (GA4, Google Ads, Meta Pixel, Clarity) with consent-aware scripts and front-end events.
- Delivered admin settings, calendar dashboard, manual booking creator, tools tab, and diagnostics/log viewers with dedicated roles and rate-limited REST endpoints.
- Completed full acceptance testing (A1–A10) covering isolation, checkout, integrations, theming, admin tooling, and check-in workflows.
- Added RTB (FASE 4B) with customer request forms, approval flows, and tracking updates.
