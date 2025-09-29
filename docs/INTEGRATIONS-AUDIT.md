# FP Experiences — Integrations Audit

## Brevo
- REST route `fp-exp/v1/brevo` accepts POST callbacks when signed with the shared webhook secret; payload context is masked before logging to avoid PII exposure.
- Transactional emails fall back to WooCommerce templates if the Brevo API call fails; failures are logged via `Logger::log('brevo', ...)`.
- Contact upsert sanitises attributes (name, phone, UTM tags) before hitting the API and respects marketing consent stored on the order.

## Google Calendar
- OAuth connect/disconnect guarded by capabilities and nonce checks; tokens stored in `fp_exp_google_calendar` option with expiry refresh.
- Events created only when reservations reach paid/approved states; cancellations trigger delete calls with logging for diagnostics.
- ICS attachments continue to ship regardless of calendar connectivity.

## Diagnostics
- `fp-exp/v1/ping` GET endpoint is available to capability holders (`fp_exp_manage_tools`) for quick REST health checks.

Next phase: A8 — Functional flows (smoke tests).
