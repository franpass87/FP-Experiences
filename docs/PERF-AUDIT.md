# FP Experiences — Performance Audit

## Observations
- Shortcode renderer defers asset registration until invocation and scopes CSS/JS through `Assets::enqueue_front()` / `enqueue_checkout()`, preventing global enqueue overhead on unrelated pages.
- REST responses for availability and tools emit `Cache-Control: no-store` headers via `RestRoutes::enforce_no_cache()`, ensuring dynamic data is not cached by proxies while catalogue pages remain cache-friendly.
- Custom tables (`fp_exp_slots`, `fp_exp_reservations`, `fp_exp_resources`) include composite indexes on experience/date and status columns, matching the query filters in slot/reservation lookups.
- Rate limiting in admin REST handlers (`Helpers::hit_rate_limit()`) reduces repeated operations on capacity, resync, and replay endpoints.

## Micro-optimisations applied
- None required; current implementation already lazily loads settings and uses scope-based CSS injection. Keep monitoring slot generation for large datasets and consider batching if daily occurrences exceed thousands.

Next phase: A5 — Accessibility & UX.
