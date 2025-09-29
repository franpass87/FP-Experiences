# FP Experiences — Tracking Audit

## Channels & toggles
- **GA4 / GTM**: Enabled when an ID is saved and consent granted (`Consent::granted(ga4)`). Events pushed via front.js (`view_item`, `select_item`, `add_to_cart`, `begin_checkout`) and purchase payload emitted on thank-you page by `Integrations\GA4`.
- **Google Ads**: Uses conversion ID/label stored in settings; payload piggybacks on the dataLayer purchase event. Enhanced conversions honour consent before hashing customer data.
- **Meta Pixel**: Injected only when pixel ID set and consent granted; purchase payload derived from WooCommerce order meta with optional server-side ID passthrough.
- **Microsoft Clarity**: Script tag rendered only when toggle enabled and consent channel `clarity` granted.

## Request-to-Book events
Front-end script emits dedicated events for RTB lifecycle:
- `fpExp.request_submit` when the request form posts.
- `fpExp.request_success` after approval/confirmation payload returns.
- `fpExp.request_error` on validation or transport errors.

## UTM propagation
- `Helpers::read_utm_cookie()` sanitises the `fp_exp_utm` cookie and saves the payload on reservations/orders.
- Brevo contact upsert forwards `utm_source`, `utm_medium`, `utm_campaign`, and tag `experience:{slug}` for segmentation.

## Consent bridge
- `Utils\Consent::granted()` exposes `fp_exp_tracking_consent` filter so CMPs (Complianz, CookieYes, etc.) can override per-channel decisions before scripts enqueue.

Next phase: A7 — Integrations (Brevo & Google Calendar).
