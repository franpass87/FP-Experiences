# Changelog

Tutte le modifiche rilevanti a questo progetto verranno documentate in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce al [Semantic Versioning](https://semver.org/lang/it/).

---

## [Unreleased]

### Planned
- [ ] Multi-currency support
- [ ] Advanced reporting dashboard
- [ ] Mobile app integration
- [ ] Custom booking rules engine

---

## [0.3.4] - 2025-10-07

### 🎨 Documentazione
- **Riorganizzata completamente** struttura documentazione
- Creata nuova organizzazione `/docs` con sottocartelle:
  - `admin/` - Guide amministratori
  - `developer/` - Guide sviluppatori
  - `technical/` - Documentazione tecnica
  - `archived/` - File storici
- Creato **[docs/README.md](README.md)** come indice principale
- Aggiornato **README.md** root con design moderno
- Creata **Quick Start Guide** per [admin](admin/QUICK-START.md) e [developer](developer/QUICK-START-DEV.md)
- Archiviati 15+ file di verifica obsoleti
- Ottimizzato CHANGELOG con formato standardizzato

### ✨ Sistema Calendario
- Completata verifica sistema calendario backend → frontend
- 34 controlli automatici: 0 errori critici ✅
- Creati script di verifica:
  - `verify-calendar-system.sh` - Verifica automatica
  - `test-calendar-data-flow.php` - Test funzionale
- Documentazione tecnica completa:
  - [CALENDAR-SYSTEM.md](technical/CALENDAR-SYSTEM.md)
  - [CALENDAR-VERIFICATION-REPORT.md](technical/CALENDAR-VERIFICATION-REPORT.md)
- Retrocompatibilità `time_sets` → `time_slots` garantita

### 🔧 Miglioramenti
- Nessun errore di linting PHP ✅
- Struttura file ottimizzata e più navigabile
- Link documentazione aggiornati ovunque
- Rimosse dipendenze circolari nella documentazione

---

## [0.3.3] - 2025-01-27

### ✨ Aggiunte
- **Filtro esperienza** nel calendario admin con selector dinamico
- **Gestione stati vuoti** migliorata con messaggi informativi
- **Link diretti** per creare prima esperienza quando nessuna è disponibile

### 🎨 UI/UX Admin
- Migliorata interfaccia **console check-in** con feedback più chiaro
- Potenziata sezione **gestione email** con layout moderno
- Ottimizzata pagina **logs** con filtri avanzati
- Migliorata pagina **strumenti** con descrizioni dettagliate
- Aggiunta navigazione **breadcrumb** nelle sezioni principali

### 🔧 Ottimizzazioni
- **Debouncing** per chiamate API multiple
- Gestione errori API migliorata
- Messaggi di errore localizzati in italiano

### ♿ Accessibilità
- Aggiunte etichette **screen reader**
- Migliorata gestione **focus** per navigazione tastiera
- Contrasto colori verificato WCAG AA

### 🌍 Localizzazione
- Messaggi di errore tradotti in italiano
- Stringhe UI completamente localizzate
- Text domain verificato: `fp-experiences`

---

## [0.3.2] - 2025-01-26

### ✨ Aggiunte
- **Hero gallery manager** con drag & drop
  - Upload multipli simultanei
  - Riordinamento visuale
  - Rimozione singola o bulk
- **Selezione lingue** nella tab Dettagli
  - Creazione termini al volo
  - Preview badge live
- **Biblioteca badge** configurabile (Settings → Showcase)
  - Preset riutilizzabili
  - Descrizioni personalizzabili
- **Branding esteso** con controlli colore
  - Background icone sezioni
  - Colore glifi
  - Integrazione Font Awesome

### 🔧 Fix
- Pulsanti quantità ticket ripristinati
- Allineamento tabella ticket desktop
- Sticky CTA button leggibile dopo click
- Liste essentials/notes con bullet nativi

### 📚 Documentazione
- Aggiunta guida PHP syntax check
- Documentazione contributor aggiornata

---

## [0.3.1] - 2025-01-15

### 🐛 Fix
- Corretta generazione slot per ricorrenze complesse
- Fix encoding caratteri speciali nelle email
- Risolto problema timezone in availability API
- Corretto calcolo capacità rimanente

### 🔧 Ottimizzazioni
- Query database slot ottimizzate (-30% tempo)
- Cache transient per meeting points
- Ridotto payload JSON API responses

---

## [0.3.0] - 2025-09-30

### ✨ Feature Principali

#### Gift Your Experience
- Workflow completo acquisto buoni regalo
- Custom Post Type `fp_exp_gift_voucher`
- Email automatiche destinatario con codice
- Redemption form con slot selection
- Ordini WooCommerce zero-cost per redenzione
- Reminder automatici pre-scadenza (30/7/1 giorni)
- Admin interface gestione voucher
- Quick actions: cancel, extend +30 giorni
- Log completo modifiche
- Shortcode `[fp_exp_gift_redeem]`
- Cron job `fp_exp_gift_send_reminders`

#### Meeting Point Importer
- Import CSV bulk locations
- Toggle sicurezza impostazioni avanzate
- Validazione colonne e duplicati
- Coordinate GPS opzionali
- Formato: `title,address,lat,lng,notes,phone,email,opening_hours`

#### Pagine Experience Auto-generate
- Creazione automatica pagina WordPress al publish
- Shortcode `[fp_exp_page]` auto-inserito
- Comando Tools per resync completo
- Link bidirezionale experience ↔ page

#### Simple Archive Layout
- Shortcode `[fp_exp_simple_archive]`
- Toggle Elementor per layout semplice/avanzato
- Grid/List cards responsive
- CTA buttons configurabili
- Spacing desktop migliorato

#### Language Flags ISO
- Badge lingue con bandiere ISO
- Labels accessibili
- Taxonomy screens admin
- Experience editor preview
- Frontend cards e widget
- Font Awesome flags fallback

### 🔧 Sistema e Infrastruttura
- **Migration runner** automatico
  - Add-on image metadata
  - Gift voucher summary table
  - Backfill automatico su upgrade
- **Recurring slots** riparato
  - RRULE linkage a time sets
  - Preview generazione
  - Controlli rigenerazione in calendar tools

### 📊 Tracking
- Eventi dataLayer enriched:
  - `add_on_view`
  - `gift_purchase`
  - `gift_redeem`
- GA4 enhanced ecommerce events

### 📚 Documentazione
- Release notes aggiornate
- QA checklist v0.3.0 completata
- Admin guide estesa per gift workflow

---

## [0.2.0] - 2025-09-29

### 🎨 UI/UX Refresh
- Redesign stile **GetYourGuide**
- Layout 2-colonne con sidebar sticky
- Chips UI per tags e filtri
- Cards listing ottimizzate

### 🔧 Fix Critici
- Fallback ID shortcode se mancante
- Flush transient automatico
- No-store headers per API
- Hardening hooks/REST/nonce (no WSOD)

### ✨ Admin
- Menu unificato **FP Experiences**
- "Crea Pagina Esperienza" shortcut
- Listing con filtri avanzati
- Display "price from" automatico

---

## [0.1.0] - 2024-05-01

> 🎉 **Prima release production-ready**

### 🔌 Integrazioni
- **Brevo** transactional email
  - Contact sync automatica
  - Webhook capture
  - Template system
- **Google Calendar** sync
  - OAuth token refresh
  - Order meta linkage
  - Bidirectional sync
- **Marketing tracking**
  - Google Analytics 4
  - Google Ads conversion
  - Meta Pixel
  - Microsoft Clarity
  - Consent-aware scripts
  - Frontend events tracking

### 🛠️ Admin Tooling
- Dashboard calendario completo
- Manual booking creator
- Tools tab utility
- Diagnostics viewers
- Log system con filtri
- Ruoli custom (`fp_operator`, `fp_manager`)
- Rate-limited REST endpoints

### ✅ Acceptance Testing
- Test A1-A10 completati:
  - Isolamento funzionalità
  - Checkout flow
  - Integrazioni esterne
  - Theming compatibility
  - Admin workflows
  - Check-in process

### 🎟️ Request to Book (FASE 4B)
- Customer request forms
- Approval workflow admin
- Status tracking
- Email notifications
- Admin approval interface

---

## [0.0.5] - 2024-04-15

### 🔐 Sicurezza
- Sanitizzazione input completa
- SQL injection prevention
- XSS protection layers
- Nonce verification ovunque
- Capability checks strict

### ⚡ Performance
- Query DB ottimizzate
- Transient cache strategico
- Assets minification
- Lazy loading immagini
- Script defer/async

### ♿ Accessibilità
- ARIA labels completi
- Keyboard navigation
- Screen reader friendly
- Focus management
- Color contrast WCAG AA

---

## [0.0.4] - 2024-04-01

### ✨ Booking System
- Slot system con capacità
- Multi-ticket types
- Add-ons opzionali
- Validation rules
- Conflict detection

### 📅 Calendar Core
- Ricorrenze base (daily, weekly)
- Time sets configuration
- Buffer temporali
- Blackout dates
- Lead time settings

---

## [0.0.3] - 2024-03-15

### 🏗️ Architettura
- Custom Post Type `fp_experience`
- Tassonomie custom
- Meta boxes framework
- Database tables schema
- REST API foundation

### 🎨 Templates
- Single experience template
- Archive template
- Shortcodes base
- Widget system
- Template hooks

---

## [0.0.2] - 2024-03-01

### 🔧 Foundation
- Plugin structure PSR-4
- Autoloader Composer
- Build system setup
- Git workflow
- Coding standards

---

## [0.0.1] - 2024-02-15

### 🎉 Initial Release
- Plugin skeleton
- Basic activation/deactivation
- Admin menu placeholder
- Development environment

---

## Legend

- ✨ **Aggiunte** - Nuove feature
- 🔧 **Fix** - Bug fix
- 🎨 **UI/UX** - Miglioramenti interfaccia
- ⚡ **Performance** - Ottimizzazioni
- 🔐 **Sicurezza** - Security improvements
- 📚 **Documentazione** - Docs update
- 🗑️ **Deprecato** - Features deprecate
- ❌ **Rimosso** - Features rimosse
- 🔌 **Integrazioni** - Nuove integrazioni
- ♿ **Accessibilità** - A11y improvements
- 🌍 **i18n** - Internazionalizzazione

---

## Versioning

Questo progetto segue [Semantic Versioning](https://semver.org/):

- **MAJOR** version per breaking changes
- **MINOR** version per nuove feature retrocompatibili
- **PATCH** version per bug fix retrocompatibili

Formato: `MAJOR.MINOR.PATCH`

Esempio: `1.2.3`
- `1` = Major version
- `2` = Minor version (8 feature releases)
- `3` = Patch version (3 bug fixes)

---

## Migration Notes

### Upgrading to 0.3.x

**Da 0.2.x → 0.3.x:**
- ✅ No breaking changes
- ✅ Migration automatica database
- ✅ Retrocompatibilità garantita
- ⚠️ Nuove tabelle create: `wp_fp_exp_gift_vouchers`
- ⚠️ Nuovi meta fields: `_fp_exp_addon_image`

**Steps:**
1. Backup database
2. Aggiorna plugin
3. Verifica migrations eseguite: **Tools → System Status**
4. Test funzionalità critiche

### Upgrading to 0.2.x

**Da 0.1.x → 0.2.x:**
- ✅ UI refresh automatico
- ⚠️ Template override richiesti se custom theme
- ⚠️ Flush rewrite rules automatico

**Steps:**
1. Backup theme overrides
2. Aggiorna plugin
3. Test template rendering
4. Aggiorna overrides se necessario

---

## Support

- 📖 **Documentazione:** [docs/README.md](README.md)
- 🐛 **Bug Reports:** [GitHub Issues](https://github.com/your-repo/issues)
- 💬 **Discussions:** [GitHub Discussions](https://github.com/your-repo/discussions)
- 📧 **Email:** support@formazionepro.it

---

**Ultimo aggiornamento:** 7 Ottobre 2025  
**Formato:** Keep a Changelog 1.0.0