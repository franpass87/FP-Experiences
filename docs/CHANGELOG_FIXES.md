# Fix Changelog

| ID | File | Line | Severity | Fix summary | Commit |
| --- | --- | --- | --- | --- | --- |
| ISSUE-004 | src/Shortcodes/BaseShortcode.php | 47 | High | Skip global no-store headers unless a shortcode opts in, keeping cached pages intact. | 0c4736f |
| ISSUE-001 | src/Utils/Helpers.php | 465 | High | Allow REST permissions to fall back to payload nonces when the X-WP-Nonce header targets another action. | 249a0bc |
| ISSUE-002 | src/Utils/Helpers.php | 492 | High | Require a valid REST nonce or same-origin referer before gift voucher REST endpoints run. | 1b332e6 |
| ISSUE-003 | src/Api/RestRoutes.php | 281 | Medium | Limit Cache-Control overrides to fp-exp routes so core REST responses stay cacheable. | 9d30a16 |
| ISSUE-005 | src/Booking/Cart.php | 344 | Medium | Mark the fp_exp_sid session cookie as HttpOnly to block script access. | 8c208a4 |
| ISSUE-006 | templates/front/widget.php | 55 | Medium | Format widget ticket prices with the store currency symbol and positioning. | 4d0bf25 |
| ISSUE-006 | templates/front/list.php | 70 | Medium | Render listing prices with WooCommerce symbol and respect currency position. | 4d0bf25 |
| ISSUE-006 | templates/front/simple-archive.php | 29 | Medium | Replace hardcoded Euro symbols with WooCommerce-driven currency output. | 4d0bf25 |
| ISSUE-007 | src/Gift/VoucherManager.php | 386 | Medium | Process gift reminders in paginated batches to avoid loading every voucher at once. | 3b75ce5 |
| ISSUE-008 | src/MeetingPoints/MeetingPointMetaBoxes.php | 102 | Low | Unslash meeting point meta payloads before sanitising to remove stray backslashes. | 9876b84 |
| ISSUE-009 | src/Booking/Slots.php | 507 | Medium | Add bulk capacity snapshot helper used by front-end shortcodes. | e0e6099 |
| ISSUE-009 | src/Shortcodes/CalendarShortcode.php | 156 | Medium | Use the bulk snapshot to remove calendar shortcode N+1 queries. | e0e6099 |
| ISSUE-009 | src/Shortcodes/WidgetShortcode.php | 352 | Medium | Reuse aggregated slot capacity data when building widget slot lists. | e0e6099 |

## Summary

Resolved 9 of 9 audited issues across two fix batches; no pending items remain after marking the fix phase complete.
