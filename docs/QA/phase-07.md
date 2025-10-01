# Phase 07 – Gift Your Experience

## Manual QA
- [ ] Enable **Settings → Gift**, adjust validity and reminder offsets, and confirm the values persist after saving.
- [ ] From an experience page, submit the gift form with purchaser/recipient details and ensure the flow redirects to the WooCommerce payment screen with the correct amount and add-ons.
- [ ] Complete payment for a gift order and verify the voucher post is created, status switches to **Active**, and recipient email is dispatched with the redemption link/code.
- [ ] Visit the `[fp_exp_gift_redeem]` page, look up the new voucher, and confirm that add-ons/quantity display before selecting a slot and completing the zero-cost redemption.
- [ ] Attempt to redeem an expired or cancelled voucher and confirm a clear error is surfaced without creating orders/reservations.
- [ ] Toggle the Gift feature off and verify the purchase CTA disappears from the experience page while existing vouchers remain redeemable.

## Regression
- [ ] Standard booking checkout still works with tickets/add-ons unaffected by the gift changes.
- [ ] Reminder cron scheduling does not run when the Gift toggle is disabled.
- [ ] Elementor widgets and shortcodes continue to load without additional assets when the gift feature is inactive.
