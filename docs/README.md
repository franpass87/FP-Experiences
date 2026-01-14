# 📚 Documentazione FP Experiences

**Versione Plugin:** 1.2.0  
**Ultimo aggiornamento:** Dicembre 2025

Benvenuto nella documentazione completa del plugin **FP Experiences** per WordPress. Questa guida è organizzata per ruolo e tipo di contenuto per facilitare la navigazione.

---

## 🚀 Quick Start

### 👨‍💼 Per Amministratori
- **[Quick Start](admin/QUICK-START.md)** ⚡ - Setup iniziale in 15 minuti
- **[Guida Admin Completa](admin/ADMIN-GUIDE.md)** - Reference completo funzionalità
- **[Menu Admin](admin/ADMIN-MENU.md)** - Struttura e navigazione interfaccia
- **[Importer CSV](admin/IMPORTER-COMPLETO.md)** - Import esperienze in massa

### 💻 Per Sviluppatori
- **[Quick Start Dev](developer/QUICK-START-DEV.md)** ⚡ - Setup ambiente in 5 minuti
- **[Playbook](developer/PLAYBOOK.md)** - Metodologia e workflow sviluppo
- **[Frontend Modulare](developer/FRONTEND-MODULAR-GUIDE.md)** - API moduli JavaScript
- **[Sistema Calendario](developer/CALENDAR-SIMPLIFIED.md)** - Architettura calendario

### 🔧 Per Tecnici/QA
- **[Sistema Calendario](technical/CALENDAR-SYSTEM.md)** - Verifica completa sistema
- **[Audit Completo](technical/AUDIT-COMPLETO.md)** - Sicurezza, performance, accessibilità
- **[Production Readiness](technical/PRODUCTION-READINESS-REPORT.md)** - Checklist produzione
- **[Security Fixes](technical/SECURITY_FIXES_APPLIED.md)** - Fix sicurezza applicati

---

## 📖 Indice Completo per Categoria

### 👨‍💼 Documentazione Admin

| Documento | Descrizione |
|-----------|-------------|
| [ADMIN-GUIDE.md](admin/ADMIN-GUIDE.md) | Guida completa per amministratori del plugin |
| [ADMIN-MENU.md](admin/ADMIN-MENU.md) | Struttura menu e navigazione interfaccia |
| [IMPORTER-COMPLETO.md](admin/IMPORTER-COMPLETO.md) | Import CSV esperienze e meeting points |
| [QUICK-START.md](admin/QUICK-START.md) | Setup rapido 15 minuti |

**Argomenti trattati:**
- ✅ Gestione esperienze (creazione, modifica, pubblicazione)
- ✅ Calendario e slot ricorrenti
- ✅ Prezzi e biglietti
- ✅ Meeting points e locations
- ✅ Impostazioni generali e branding
- ✅ Gestione prenotazioni e ordini
- ✅ Gift vouchers e buoni regalo
- ✅ Email transazionali
- ✅ Integrazioni (Google Calendar, Brevo, Analytics)

---

### 💻 Documentazione Developer

| Documento | Descrizione |
|-----------|-------------|
| [ARCHITECTURE.md](developer/ARCHITECTURE.md) | Architettura Kernel-based (v1.2.0+) |
| [MIGRATION-GUIDE.md](developer/MIGRATION-GUIDE.md) | Guida migrazione alla nuova architettura |
| [FRONTEND-MODULAR-GUIDE.md](developer/FRONTEND-MODULAR-GUIDE.md) | API moduli JavaScript frontend |
| [CALENDAR-SIMPLIFIED.md](developer/CALENDAR-SIMPLIFIED.md) | Architettura sistema calendario |
| [PLAYBOOK.md](developer/PLAYBOOK.md) | Metodologia sviluppo a fasi |
| [QUICK-START-DEV.md](developer/QUICK-START-DEV.md) | Setup ambiente sviluppo |

**Argomenti trattati:**
- ✅ Architettura moduli FPFront (availability, slots, calendar, quantity)
- ✅ Sistema calendario backend → frontend
- ✅ Hook e filter WordPress
- ✅ REST API endpoints
- ✅ Custom Post Types e tassonomie
- ✅ Build e deployment
- ✅ Testing e quality assurance

**API Reference:**
```javascript
// Moduli disponibili
FPFront.availability  // Gestione disponibilità slot
FPFront.slots         // Selezione e prenotazione slot
FPFront.calendar      // Calendario mensile
FPFront.quantity      // Gestione quantità biglietti
FPFront.summaryRtb    // Riepilogo Request to Book
FPFront.summaryWoo    // Riepilogo WooCommerce
```

---

### 🔧 Documentazione Technical

| Documento | Descrizione |
|-----------|-------------|
| [MODULAR-ARCHITECTURE.md](technical/MODULAR-ARCHITECTURE.md) | Architettura modulare plugin |
| [CALENDAR-SYSTEM.md](technical/CALENDAR-SYSTEM.md) | Sistema calendario: verifica completa |
| [CALENDAR-VERIFICATION-REPORT.md](technical/CALENDAR-VERIFICATION-REPORT.md) | Report tecnico verifica calendario |
| [AUDIT-COMPLETO.md](technical/AUDIT-COMPLETO.md) | Audit sicurezza, performance, accessibilità |
| [PRODUCTION-READINESS-REPORT.md](technical/PRODUCTION-READINESS-REPORT.md) | Checklist e report produzione |
| [SECURITY_FIXES_APPLIED.md](technical/SECURITY_FIXES_APPLIED.md) | Fix sicurezza applicati |
| [TRACKING-AUDIT.md](technical/TRACKING-AUDIT.md) | Audit tracking e analytics |
| [DEEP-AUDIT.md](technical/DEEP-AUDIT.md) | Analisi approfondita codice |

**Sistema Calendario:**
- ✅ Backend: `Recurrence.php`, `AvailabilityService.php`, `Slots.php`
- ✅ Frontend: `availability.js`, `calendar.js`
- ✅ API: Endpoint `/fp-exp/v1/availability`
- ✅ Formato dati: `time_slots` (nuovo) + `time_sets` (legacy)
- ✅ 34 controlli automatici, 0 errori critici
- ✅ Retrocompatibilità garantita

**Performance:**
- ✅ Cache frontend per slot mensili
- ✅ Prefetch riduce latenza
- ✅ Query DB ottimizzate
- ✅ JSON response minimale

**Sicurezza:**
- ✅ Nonce verification
- ✅ Capability checks
- ✅ Input sanitization
- ✅ SQL injection protection
- ✅ XSS prevention

---

### 🎨 UX & Design

| Documento | Descrizione |
|-----------|-------------|
| [UX-IMPROVEMENTS-COMPLETE.md](ux/UX-IMPROVEMENTS-COMPLETE.md) | Tutti i miglioramenti UX implementati |
| [SETTINGS-UI-IMPROVEMENTS.md](ux/SETTINGS-UI-IMPROVEMENTS.md) | Redesign pagina impostazioni |
| [FINAL-SUMMARY.md](ux/FINAL-SUMMARY.md) | Riepilogo implementazioni UX |

**Features UX:**
- ✅ Setup checklist banner
- ✅ Integration status badges
- ✅ Toast notifications system
- ✅ Empty states migliorati
- ✅ Help tooltips
- ✅ Preview links
- ✅ Quick actions

---

### ✨ Features Implementate

| Documento | Descrizione |
|-----------|-------------|
| [ADDON_FIX_SUMMARY.md](features/ADDON_FIX_SUMMARY.md) | Sistema addon completo |
| [ADDON_SELECTION_TYPES.md](features/ADDON_SELECTION_TYPES.md) | Tipi selezione addon |
| [ADDON_UI_IMPROVEMENTS.md](features/ADDON_UI_IMPROVEMENTS.md) | UI miglioramenti addon |
| [BRANDING_BACKUP_SYSTEM.md](features/BRANDING_BACKUP_SYSTEM.md) | Sistema backup branding |
| [IMPORTER_AGGIORNATO.md](features/IMPORTER_AGGIORNATO.md) | Sistema import CSV |
| [PREZZO_DA_CHECKBOX_FEATURE.md](features/PREZZO_DA_CHECKBOX_FEATURE.md) | Pricing dinamico |
| [SETTINGS_PRESERVATION_GUIDE.md](features/SETTINGS_PRESERVATION_GUIDE.md) | Preservazione settings |

---

### 🐛 Bug Fixes

**Totale:** 20+ bug fix documentati

| Categoria | File | Descrizione |
|-----------|------|-------------|
| **Checkout** | [CHECKOUT_ERROR_FIX.md](bug-fixes/CHECKOUT_ERROR_FIX.md) | Fix errori checkout |
| | [CHECKOUT_NONCE_FIX.md](bug-fixes/CHECKOUT_NONCE_FIX.md) | Fix nonce checkout |
| | [CHECKOUT_PAYMENT_FIX.md](bug-fixes/CHECKOUT_PAYMENT_FIX.md) | Fix pagamenti |
| **Calendario** | [BUG_ULTIMO_GIORNO_RISOLTO.md](bug-fixes/BUG_ULTIMO_GIORNO_RISOLTO.md) | Fix ultimo giorno calendario |
| | [CALENDAR_MARGIN_FIX.md](bug-fixes/CALENDAR_MARGIN_FIX.md) | Fix margini calendario |
| | [CALENDAR_SPACE_VERIFICATION.md](bug-fixes/CALENDAR_SPACE_VERIFICATION.md) | Verifica spazi calendario |
| **Gift Voucher** | [GIFT_BUTTON_FIX.md](bug-fixes/GIFT_BUTTON_FIX.md) | Fix pulsante gift |
| | [GIFT_ENDPOINT_FIX_2025-10-31.md](bug-fixes/GIFT_ENDPOINT_FIX_2025-10-31.md) | Fix endpoint gift |
| **Altri** | [FEATURED_IMAGE_FIX.md](bug-fixes/FEATURED_IMAGE_FIX.md) | Fix immagini featured |
| | [SESSION_EXPIRED_FIX.md](bug-fixes/SESSION_EXPIRED_FIX.md) | Fix sessione scaduta |
| | [PROBLEMA_VISIBILITA_AGGIORNAMENTI.md](bug-fixes/PROBLEMA_VISIBILITA_AGGIORNAMENTI.md) | Fix aggiornamenti non visibili |

**Indice completo:** [bug-fixes/INDEX.md](bug-fixes/INDEX.md)

---

### 🚀 Deployment & Releases

| Documento | Descrizione |
|-----------|-------------|
| [DEPLOYMENT-SETUP.md](deployment/DEPLOYMENT-SETUP.md) | Configurazione deployment |
| [DEPLOYMENT-CHANGES.md](deployment/DEPLOYMENT-CHANGES.md) | Modifiche deploy |
| [GITHUB-DEPLOYMENT-SUMMARY.md](deployment/GITHUB-DEPLOYMENT-SUMMARY.md) | Deploy GitHub Actions |

**Release Notes:**
- [RELEASE_NOTES_v0.3.7.md](releases/RELEASE_NOTES_v0.3.7.md) - Note rilascio v0.3.7
- [UPGRADE_GUIDE_v0.3.7.md](releases/UPGRADE_GUIDE_v0.3.7.md) - Guida aggiornamento v0.3.7

**Checklist:**
- [RELEASE-CHECKLIST.md](RELEASE-CHECKLIST.md) - Checklist pre-rilascio

---

### ✅ QA & Testing

| Documento | Descrizione |
|-----------|-------------|
| [full-regression.md](QA/full-regression.md) | Test regressione completo |
| [phase-01.md](QA/phase-01.md) → [phase-08.md](QA/phase-08.md) | Test per fase |

**Verifiche:**
- [COMPLETE_FILE_VERIFICATION.md](verification/COMPLETE_FILE_VERIFICATION.md) - Verifica file completa
- [VERIFICA_CHECKOUT_2025-10-09.md](verification/VERIFICA_CHECKOUT_2025-10-09.md) - Verifica checkout
- [VERIFICA_PULSANTE_PAGAMENTO.md](verification/VERIFICA_PULSANTE_PAGAMENTO.md) - Verifica pulsante pagamento

---

## 🎯 Percorsi di Lettura Consigliati

### Per Amministratori

1. **[Quick Start](admin/QUICK-START.md)** - Setup iniziale
2. **[Admin Guide](admin/ADMIN-GUIDE.md)** - Reference completo
3. **[Importer](admin/IMPORTER-COMPLETO.md)** - Import CSV
4. **[CHANGELOG.md](CHANGELOG.md)** - Cosa è cambiato

### Per Sviluppatori

1. **[Quick Start Dev](developer/QUICK-START-DEV.md)** - Setup ambiente
2. **[Architecture](developer/ARCHITECTURE.md)** - Architettura Kernel-based (v1.2.0+)
3. **[Migration Guide](developer/MIGRATION-GUIDE.md)** - Migrazione alla nuova architettura
4. **[Playbook](developer/PLAYBOOK.md)** - Metodologia sviluppo
5. **[Frontend Guide](developer/FRONTEND-MODULAR-GUIDE.md)** - API JavaScript

### Per QA Team

1. **[Production Readiness](technical/PRODUCTION-READINESS-REPORT.md)** - Checklist produzione
2. **[Full Regression](QA/full-regression.md)** - Test regressione
3. **[Security Fixes](technical/SECURITY_FIXES_APPLIED.md)** - Fix sicurezza
4. **[Audit Completo](technical/AUDIT-COMPLETO.md)** - Audit sistema

---

## 🔍 Cerca per Argomento

| Cosa cerchi | Dove guardare |
|-------------|---------------|
| Come configurare il plugin | [admin/QUICK-START.md](admin/QUICK-START.md) |
| Come sviluppare | [developer/QUICK-START-DEV.md](developer/QUICK-START-DEV.md) |
| Bug fix specifico | [bug-fixes/](bug-fixes/) |
| Release notes | [releases/](releases/) |
| Come fare deploy | [deployment/](deployment/) |
| Test eseguiti | [QA/](QA/) |
| Dettagli tecnici | [technical/](technical/) |
| Feature specifica | [features/](features/) |
| Miglioramenti UX | [ux/](ux/) |
| Changelog completo | [CHANGELOG.md](CHANGELOG.md) |

---

## 📊 Statistiche Documentazione

| Categoria | File | Dimensione |
|-----------|------|------------|
| **Admin** | 4 | ~40 KB |
| **Developer** | 4 | ~35 KB |
| **UX** | 3 | ~25 KB |
| **Features** | 10 | ~55 KB |
| **Bug Fixes** | 20+ | ~100 KB |
| **QA** | 9 | ~45 KB |
| **Technical** | 8+ | ~50 KB |
| **Deployment** | 3 | ~15 KB |
| **Releases** | 2 | ~20 KB |
| **TOTALE** | ~65+ | ~385 KB |

---

## 🆕 Novità Recenti (v1.2.0)

### 🏗️ Kernel-based Architecture (v1.2.0)
- ✅ Nuova architettura basata su Kernel e Container (Dependency Injection)
- ✅ Service Providers organizzati per dominio funzionale
- ✅ Plugin legacy convertito in facade minimale
- ✅ Retrocompatibilità completa mantenuta
- ✅ Risolte dipendenze circolari
- ✅ Metodi helper `Bootstrap::get()` e `Bootstrap::has()`

**Documentazione:**
- [Architettura completa](developer/ARCHITECTURE.md) - Documentazione tecnica architettura
- [Guida migrazione](developer/MIGRATION-GUIDE.md) - Come migrare codice esistente
- [CHANGELOG](CHANGELOG.md#120---2025-12-25) - Dettagli completi v1.2.0

### Gift Voucher System
- ✅ Sistema completo gift vouchers
- ✅ Checkout WooCommerce integrato
- ✅ Template override personalizzato
- ✅ Prezzo dinamico funzionante
- ✅ Email pre-fill automatico

**Documentazione:**
- Bug fix: [BUG-REPORT-GIFT-VOUCHER-CHECKOUT-2025-11-06.md](../BUG-REPORT-GIFT-VOUCHER-CHECKOUT-2025-11-06.md)
- Status: [FINAL-STATUS-REPORT.md](../FINAL-STATUS-REPORT.md)

### Security Hardening
- ✅ XSS prevention client-side
- ✅ Input sanitization completa
- ✅ SQL injection protection
- ✅ Nonce verification robusta

**Documentazione:**
- [SECURITY_FIXES_APPLIED.md](technical/SECURITY_FIXES_APPLIED.md)

### Performance Improvements
- ✅ Cache frontend ottimizzata
- ✅ Query DB ottimizzate
- ✅ Lazy loading implementato
- ✅ Asset optimization

---

## 🛠️ Strumenti e Utility

### Script di Verifica

**verify-calendar-system.sh**
```bash
bash tools/verification/verify-calendar-system.sh
```
Esegue 34 controlli automatici sul sistema calendario.

**test-calendar-data-flow.php**
```bash
php tools/verification/test-calendar-data-flow.php
```
Simula il flusso completo dati dal form admin al frontend.

**run-php-syntax-check.sh**
```bash
bash tools/run-php-syntax-check.sh
```
Verifica sintassi PHP di tutti i file sorgente e compilati.

---

## 📋 Build e Release

### Build del Plugin

```bash
# Incrementa versione patch (1.2.0 → 1.2.1)
bash build.sh --bump=patch

# Imposta versione specifica
bash build.sh --set-version=2.0.0

# Build con dipendenze di produzione
bash build.sh
```

Il processo:
1. ✅ Aggiorna numeri versione in tutti i file
2. ✅ Installa dipendenze production-only
3. ✅ Crea package .zip in `/build/`
4. ✅ Esclude file dev e test

### Release GitHub

Pusha un tag per triggerare il workflow automatico:
```bash
git tag v1.2.0
git push origin v1.2.0
```

---

## 📝 Changelog

Vedi **[CHANGELOG.md](CHANGELOG.md)** per:
- Cronologia versioni completa
- Nuove funzionalità
- Bug fix dettagliati
- Breaking changes
- Note di migrazione

**Versione corrente:** `1.2.0` (Dicembre 2025)

---

## 🗂️ Struttura Documentazione

```
docs/
├── README.md                    # Questo file (indice principale)
├── CHANGELOG.md                 # Cronologia versioni
├── RELEASE-CHECKLIST.md         # Checklist pre-release
├── DOCUMENTATION-GUIDE.md       # Come scrivere documentazione
├── INDEX.md                     # Indice alternativo (legacy)
├── ORGANIZATION.md              # Organizzazione struttura
│
├── admin/                       # Guide amministratori (4 file)
├── developer/                   # Guide sviluppatori (4 file)
├── technical/                   # Documentazione tecnica (8+ file)
├── ux/                          # Miglioramenti UX (3 file)
├── features/                    # Features implementate (10 file)
├── bug-fixes/                   # Bug fix applicati (20+ file)
├── deployment/                  # Setup deployment (3 file)
├── releases/                    # Release notes (2 file)
├── verification/                # Test e verifiche (3 file)
├── QA/                          # Quality Assurance (9 file)
├── bug-reports/                 # Report bug storici (9 file)
└── archived/                    # Documentazione deprecata (15 file)
```

---

## 🆘 Supporto e Troubleshooting

### Problemi Comuni

**Il calendario non mostra slot:**
1. Verifica che l'esperienza sia pubblicata
2. Controlla che ci siano giorni settimana selezionati
3. Verifica che ci siano time_slots configurati
4. Controlla che la capacità generale sia > 0

**Le modifiche non si salvano:**
1. Abilita WP_DEBUG in `wp-config.php`
2. Controlla i log in `wp-content/debug.log`
3. Verifica permessi utente
4. Ispeziona Network tab del browser

**Errori API REST:**
1. Verifica permalink settings (Settings → Permalinks)
2. Controlla .htaccess
3. Testa endpoint con Postman/Insomnia
4. Verifica nonce e authentication

### Debug

**Abilita debug mode:**
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**Log personalizzati:**
```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('FP_EXP: ' . print_r($data, true));
}
```

---

## 🔗 Link Utili

- **Repository:** [GitHub](https://github.com/franpass87/FP-Experiences)
- **Issue Tracker:** [GitHub Issues](https://github.com/franpass87/FP-Experiences/issues)
- **Documentazione Principale:** [README.md](../README.md)

---

## 📝 Contribuire

Per contribuire al progetto:

1. Fork il repository
2. Crea branch feature (`git checkout -b feature/AmazingFeature`)
3. Commit modifiche (`git commit -m 'Add AmazingFeature'`)
4. Push al branch (`git push origin feature/AmazingFeature`)
5. Apri Pull Request

**Linee guida:**
- ✅ Segui PSR-12 per PHP
- ✅ Usa ESLint per JavaScript
- ✅ Scrivi test per nuove funzionalità
- ✅ Aggiorna la documentazione
- ✅ Mantieni la retrocompatibilità

---

## 📄 Licenza

Questo plugin è rilasciato sotto licenza GPL v2 o successiva.

---

## 👥 Team

Sviluppato e mantenuto da **Francesco Passeri**.

- Website: [francescopasseri.com](https://francescopasseri.com)
- GitHub: [@franpass87](https://github.com/franpass87)

---

**Ultimo aggiornamento:** Dicembre 2025  
**Versione documentazione:** 2.2  
**Versione plugin:** 1.2.0

---

**Tieni questa pagina nei preferiti come punto di partenza! 🔖**
