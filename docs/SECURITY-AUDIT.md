# FP Experiences — Security Audit

## Summary
The audit revisited REST endpoints, admin flows, Request-to-Book lifecycle, logging, and integration hooks. The plugin enforces capability checks and nonce validation, and now includes per-client throttling on public submission endpoints alongside the existing sanitisation safeguards.

## Findings
| ID | Area | Severity | Status | Notes |
| -- | ---- | -------- | ------ | ----- |
| SEC-01 | Google Calendar connect/disconnect actions | Low | Fixed | Sanitised `_wpnonce` handling in `SettingsPage::maybe_handle_calendar_actions()` to unslash the nonce before verification, preventing failures with slashed query vars. |
| SEC-02 | REST capability checks | Info | Pass | All management routes use dedicated capabilities (`fp_exp_manage_calendar`, `fp_exp_manage_tools`, `fp_exp_manage_requests`). |
| SEC-03 | Front-end submission nonces | Info | Pass | Checkout (`fp-exp-checkout`) and RTB (`fp-exp-rtb`) endpoints require signed nonces and sanitise payloads (`sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`). |
| SEC-04 | Logging of sensitive data | Info | Pass | `Logger::scrub_context()` masks email, phone, and credential fields before persisting to options. |
| SEC-05 | SQL access | Info | Pass | Custom table queries rely on `$wpdb->prepare()` and sanitised inputs across `Reservations`, `Slots`, and `Resources` models; no unprepared SQL detected in audit spot checks. |
| SEC-06 | Public submission throttling | Medium | Fixed | Introduced `Helpers::client_fingerprint()` with per-client rate limiting for checkout and RTB submissions, returning HTTP 429 on bursts and sending `nocache_headers()` to discourage intermediary caching. |

## Recommendations
- Extend the capability documentation to help site owners assign the new roles correctly.
- Consider adding integration tests for nonce failures to guard regressions.

Next phase: A3 — Privacy & GDPR.
