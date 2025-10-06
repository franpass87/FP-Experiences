# Changelog

## [Unreleased]

## [0.3.4] - 2025-01-27
- **Ottimizzazione Documentazione**: Consolidati tutti i file di audit in un unico documento completo (AUDIT-COMPLETO.md)
- **Guida Importer Consolidata**: Unificati tutti i file dell'importer in una guida completa (IMPORTER-COMPLETO.md)
- **Verifica Completa**: Consolidati tutti i file di verifica in una guida unificata (VERIFICA-COMPLETA.md)
- **Riduzione File**: Eliminati 15+ file ridondanti per ottimizzare la struttura della documentazione
- **Aggiornamento README**: Tradotto e aggiornato il README principale in italiano con riferimenti alla nuova documentazione consolidata
- **Miglioramento Organizzazione**: La documentazione è ora più facile da navigare e mantenere

## [0.3.3] - 2025-01-27

## [0.3.3] - 2025-01-27
- **Miglioramenti Admin Calendar**: Aggiunto supporto per filtraggio per esperienza nel calendario admin con selector dinamico e gestione stati vuoti
- **Ottimizzazioni JavaScript**: Migliorata gestione errori API, debouncing per chiamate multiple, e messaggi di errore localizzati in italiano
- **UI/UX Admin**: Migliorata esperienza utente con messaggi informativi quando non ci sono esperienze disponibili e link diretti per creare la prima esperienza
- **Console Check-in**: Migliorata interfaccia check-in con gestione stati prenotazioni e feedback utente più chiaro
- **Gestione Email**: Potenziata sezione gestione email con layout migliorato e navigazione breadcrumb
- **Logs e Diagnostica**: Migliorata pagina logs con filtri avanzati e diagnostica di sistema più dettagliata
- **Strumenti Operativi**: Ottimizzata pagina strumenti con layout migliorato e descrizioni più chiare
- **Accessibilità**: Migliorata accessibilità con etichette screen reader e gestione focus appropriata
- **Localizzazione**: Aggiunti messaggi di errore in italiano per migliorare l'esperienza utente italiana

## [0.3.2] - 2025-01-26
- Added a hero gallery manager to the experience details tab with drag ordering, multi-select uploads, and quick clearing.
- Moved language selection into the details tab, allowing manual term creation and badge previews prior to saving.
- Introduced a configurable badge library under **Settings → Showcase** so presets can be renamed or extended for editors.
- Expanded branding controls with section icon background/foreground pickers and switched public templates to Font Awesome icons.
- Streamlined essentials/notes lists to rely on native bullets and reduced section title sizing for better hierarchy.
- Fixed ticket quantity buttons, restored desktop ticket table alignment, and kept the sticky CTA button legible after clicks.
- Updated contributor documentation with the PHP syntax check helper covering both source and build trees.

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
