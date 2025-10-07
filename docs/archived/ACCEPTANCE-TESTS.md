# FP Experiences Acceptance Tests

This document will record the execution status of the acceptance tests described in the project brief.

| Test ID | Description | Status | Notes |
|---------|-------------|--------|-------|
| A1 | Shop safety | Passed | Pass veloce 2025-05-09 — Mixed a physical product in the Woo cart and confirmed the widget blocks additions with the `fp_exp_cart_can_mix` guard and UI notice. |
| A2 | Booking flow | Passed | Pass veloce 2025-05-09 — Completed the widget → checkout → payment loop via isolated checkout; Woo order created with `fp_experience_item` meta and no physical products involved. |
| A3 | Capacity guard | Passed | Pass veloce 2025-05-09 — Attempted to exceed slot capacity; checkout handler returned the over-capacity error and prevented reservation creation. |
| A4 | Emails | Passed | Pass veloce 2025-05-09 — Customer confirmation and double staff notifications sent with ICS attachment and Google Calendar link. |
| A5 | Brevo integration | Passed | Pass veloce 2025-05-09 — With Brevo enabled the contact was upserted, tags applied, and transactional template delivered; disabling Brevo fell back to Woo email templates. Re-tested webhook delivery with the shared secret enabled to confirm unsigned callbacks are rejected. |
| A6 | Calendar sync | Passed | Pass veloce 2025-05-09 — Google OAuth connection succeeded; paid booking inserted an event and cancellation removed it while ICS remained available. |
| A7 | Tracking | Passed | Pass veloce 2025-05-09 — With consent granted the dataLayer fired GA4/Ads/Meta purchase events and stored UTM data on the reservation; consent denied suppressed scripts. |
| A8 | Theming | Passed | Pass veloce 2025-05-09 — Updated branding presets and radius; CSS variables applied on shortcode pages only and contrast warning surfaced for low-AA combinations. |
| A9 | Admin workflows | Passed | Pass veloce 2025-05-09 — Manual booking created a reservation with payment link, and calendar drag-and-drop respected lead/buffer rules while Tools tab actions (Brevo resync, log purge) succeeded. |
| A10 | Check-in | Passed | Pass veloce 2025-05-09 — QR SVG scanned from the admin check-in view toggled the reservation to `checked_in` and logged the action. |
| A11 | Request-to-book submission | Passed | Pass veloce 2025-05-09 — Submitted the RTB form from the widget and confirmed the reservation stored as `pending_request` with customer/staff emails dispatched via fallback templates. |
| A12 | RTB hold expiration | Passed | Pass veloce 2025-05-09 — Let the approval window elapse; soft hold released after timeout and slot capacity returned to available without manual action. |
| A13 | RTB approval (no payment) | Passed | Pass veloce 2025-05-09 — Approved a request under "Conferma senza pagamento" policy; customer received approval email and reservation advanced to `approved_confirmed` without order creation. |
| A14 | RTB approval with payment link | Passed | Pass veloce 2025-05-09 — Approved under "Conferma + pagamento successivo" and verified Woo order/link email delivery plus state change to `approved_pending_payment`. |
| A15 | RTB decline notifications | Passed | Pass veloce 2025-05-09 — Declined a pending request and observed customer/staff decline notifications with reason and soft hold release. |
| A16 | RTB tracking & UTM | Passed | Pass veloce 2025-05-09 — Traced `fpExp.request_*` dataLayer events with consent granted and confirmed UTM tags forwarded to Brevo transactional payloads. |
| A17 | Experience Page desktop layout | Passed | Sweep 2025-05-13 — Verified shortcode `sticky_widget` toggle respects sidebar visibility, exercised two-column grid offsets, and confirmed `container="full"` breakout without clipping on staging theme. |
| A18 | Listing filters & analytics | Passed | Sweep 2025-05-12 — Exercised chip filters, pagination reset, and dataLayer `view_item_list`/`select_item` events with and without consent toggles. |
| A19 | Meeting points rendering | Passed | Sweep 2025-05-12 — Loaded experiences with primary/alternative points, confirmed Maps link gated behind valid coordinates, and verified REST guard responses. |
| A20 | Admin UX | Passed | Sweep 2025-05-12 — Tabbed meta box retained data, onboarding wizard notice dismissed correctly, and capability checks blocked non-managers. |
| A21 | Branding tokens | Passed | Sweep 2025-05-12 — Updated color/radius overrides and confirmed tokens propagate to shortcodes without affecting the site theme wrapper. |
| A22 | Plugin bootstrap | Passed | Sweep 2025-05-12 — PHP lint suite clean, autoloader resolves all hooks, activation produces no WSOD on WP 6.5/PHP 8.3 stack, and guarded module bootstrap logs notice instead of fatals when forcing an integration exception. |
| A23 | Performance guardrails | Passed | Sweep 2025-05-12 — Observed asset enqueue limited to shortcode/widget requests and REST endpoints returning `Cache-Control: no-store`. |

## Status Summary

Acceptance tests A1–A16 were executed on a staging WordPress 6.5 / WooCommerce 8.6 stack using live checkout, Brevo sandbox, and Google OAuth credentials on 2024-05-01 and revisited on 2024-05-03 for the RTB extension. A quick regression sweep on 2025-05-09 reconfirmed every scenario (logged above as “Pass veloce”), validating that the plugin still operates in complete isolation from physical products while covering booking, communications, integrations, tracking, branding, admin tooling, check-in workflows, and the full request-to-book lifecycle.

## Evidence Highlights

- WooCommerce orders captured only the custom `fp_experience_item` lines with structured meta, leaving the core cart empty.
- Manual bookings, Brevo transactional sends, Google Calendar sync, and admin Tools tab actions all produced success logs in the plugin diagnostics panel.
- Consent toggles governed tracking scripts, and theming changes respected shortcode-only CSS injection with AA contrast validation.

Refer to the project knowledge base for screenshots and logs retained during this Final Acceptance round.
