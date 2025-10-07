# FP Experiences Build Playbook

This playbook tracks the phased implementation path for the FP Experiences plugin and supports resumable development via `.rebuild-state.json`.

## Phases Overview
0. Scaffold
1. CPT & Meta
2. Availability & Pricing
3. Shortcodes & Templates
4. Cart & Checkout Isolation
4B. Request-to-Book (RTB) Mode
5. Emails, ICS, Staff Notifications
6. Brevo Integration
7. Google Calendar
8. Marketing Tracking
9. Branding & Theming
10. Admin Calendar, Manual Booking, Roles
11. Logs, Tools, Diagnostics
12. Hardening & QA
13. I18N & Docs
14. Final Acceptance

## Phase Notes
- **Phase 0** – Establish project layout, loader bootstrap, asset placeholders, git hygiene, and initial docs.
- **Phase 4B** – Introduce the RTB request flow with admin approvals, payment-link handling, soft holds, and dedicated Requests admin page.
- **Phase 10** – Admin calendar upgraded with month/week/day views, drag-and-drop slot moves, capacity editing, and manual booking payment links governed by dedicated capabilities.
- **Phase 11** – Logs page gained filters and CSV export while the Tools tab triggers Brevo resync, event replay, ping, and cache clearing actions over authenticated REST endpoints.
- **Phase 12** – REST API hardened with nonce checks, sanitisation, no-cache headers, and per-user rate limiting; acceptance test matrix recorded for staging execution.
- **Phase 13** – Documentation, readme, changelog, and translation template refreshed to surface shortcodes, hooks, FAQs, and the new admin utilities.
- **Phase 14** – Final acceptance scenarios A1–A16 executed (booking and RTB flows) with results logged in `docs/ACCEPTANCE-TESTS.md` and release notes updated for version 0.1.0.

Each phase must conclude with `.rebuild-state.json` updates noting `current`, optional `audit_current`, and any pertinent notes.
