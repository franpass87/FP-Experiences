# Phase 08 – Tracking, Migrations, Docs, Final Tests

- [ ] Run the migration runner (visit any admin page) and confirm `_fp_addons` entries include an `image_id` key; inspect the `wp_fp_exp_gift_vouchers` table for backfilled rows.
- [ ] Purchase a gift voucher, verify the new summary table row is created, and confirm the `gift_purchase` dataLayer event includes experience, quantity, and addons metadata.
- [ ] Redeem an active voucher, ensure the zero-cost WooCommerce order/reservation is created, and confirm the `gift_redeem` event fires with slot/add-on details.
- [ ] Trigger the booking widget on desktop, scroll add-ons into view, and check the `add_on_view` tracking event payload.
- [ ] Smoke-test existing flows (recurrence regeneration, meeting point import toggle, auto page creation, archive simple view, language flags) for regressions.
- [ ] Review docs (`readme.txt`, `docs/CHANGELOG.md`, `docs/ADMIN-GUIDE.md`) and confirm version bump to 0.3.0.
- [ ] Generate the release ZIP and tag `v0.3.0` (GitHub action or `npm run build` depending on environment) once all checks are green.
