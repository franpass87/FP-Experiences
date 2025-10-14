# Indice Documentazione - FP Experiences v0.3.7

**Ultimo aggiornamento:** 13 Ottobre 2025  
**Versione plugin:** 0.3.7

---

## 📚 Documentazione Principale

### Getting Started

1. **[README.md](README.md)** ⭐ START HERE
   - Panoramica plugin
   - Caratteristiche principali
   - Installazione e configurazione
   - Quality Assurance section

2. **[UPGRADE_GUIDE_v0.3.7.md](UPGRADE_GUIDE_v0.3.7.md)**
   - Procedura di aggiornamento
   - Checklist post-upgrade
   - Risoluzione problemi
   - Test raccomandati

3. **[RELEASE_NOTES_v0.3.7.md](RELEASE_NOTES_v0.3.7.md)**
   - Novità versione 0.3.7
   - Bug fix dettagliati
   - Security audit
   - Breaking changes (nessuno)

---

## 📖 Documentazione Tecnica

### Core Documentation

- **[docs/README.md](docs/README.md)** - Indice documentazione completa
- **[docs/CHANGELOG.md](docs/CHANGELOG.md)** - Cronologia versioni dettagliata
- **[docs/RELEASE-CHECKLIST.md](docs/RELEASE-CHECKLIST.md)** - Checklist pre-release

### Documentazione Admin

- **[docs/admin/QUICK-START.md](docs/admin/QUICK-START.md)** - Guida rapida amministratori
- **[docs/admin/IMPORTER-COMPLETO.md](docs/admin/IMPORTER-COMPLETO.md)** - Guida importatore CSV
- **[docs/admin/AUDIT-COMPLETO.md](docs/admin/AUDIT-COMPLETO.md)** - Audit funzionalità
- **[docs/admin/VERIFICA-COMPLETA.md](docs/admin/VERIFICA-COMPLETA.md)** - Verifica sistema

### Documentazione Developer

- **[docs/developer/QUICK-START-DEV.md](docs/developer/QUICK-START-DEV.md)** - Setup ambiente dev
- **[docs/developer/HOOKS.md](docs/developer/HOOKS.md)** - Hook e filtri disponibili
- **[docs/developer/API.md](docs/developer/API.md)** - REST API reference
- **[docs/developer/SHORTCODES.md](docs/developer/SHORTCODES.md)** - Guida shortcode

### Documentazione Tecnica

- **[docs/technical/CALENDAR-SYSTEM.md](docs/technical/CALENDAR-SYSTEM.md)** - Sistema calendario
- **[docs/technical/BOOKING-FLOW.md](docs/technical/BOOKING-FLOW.md)** - Flusso prenotazioni
- **[docs/technical/PRICING.md](docs/technical/PRICING.md)** - Sistema pricing
- **[docs/technical/EMAIL-SYSTEM.md](docs/technical/EMAIL-SYSTEM.md)** - Sistema email
- **[docs/technical/DATABASE.md](docs/technical/DATABASE.md)** - Schema database
- **[docs/technical/INTEGRATIONS.md](docs/technical/INTEGRATIONS.md)** - Integrazioni terze parti
- **[docs/technical/ARCHITECTURE.md](docs/technical/ARCHITECTURE.md)** - Architettura plugin

---

## 🐛 Bug Reports & Analysis (v0.3.7)

### Indice Completo

**[docs/bug-reports/README.md](docs/bug-reports/README.md)** - Indice tutti i report

### Report Chiave

1. **[SUMMARY_ALL_BUG_FIXES_2025-10-13.md](docs/bug-reports/SUMMARY_ALL_BUG_FIXES_2025-10-13.md)** ⭐
   - Riepilogo completo 6 iterazioni
   - Tutti i bug trovati e risolti
   - Statistiche complete

2. **[BUG_FIX_RACE_CONDITION_IMPLEMENTED.md](docs/bug-reports/BUG_FIX_RACE_CONDITION_IMPLEMENTED.md)**
   - Fix race condition critica
   - Double-check pattern spiegato
   - Before/after comparison

3. **[REGRESSION_ANALYSIS.md](docs/bug-reports/REGRESSION_ANALYSIS.md)**
   - Analisi regressioni
   - Verifica backward compatibility
   - Nessuna regressione trovata

4. **[FINAL_BUG_ANALYSIS_COMPLETE.md](docs/bug-reports/FINAL_BUG_ANALYSIS_COMPLETE.md)**
   - Certificazione finale
   - Metriche qualità
   - Production ready approval

### Report per Iterazione

- **Iterazione 1:** [BUG_FIX_REPORT_2025-10-13.md](docs/bug-reports/BUG_FIX_REPORT_2025-10-13.md)
- **Iterazione 2:** [BUG_ANALYSIS_COMPLETE_2025-10-13.md](docs/bug-reports/BUG_ANALYSIS_COMPLETE_2025-10-13.md)
- **Iterazione 3:** [BUG_RACE_CONDITION_ANALYSIS.md](docs/bug-reports/BUG_RACE_CONDITION_ANALYSIS.md)
- **Iterazione 5:** [BUG_SEARCH_ITERATION_5_FINAL.md](docs/bug-reports/BUG_SEARCH_ITERATION_5_FINAL.md)

---

## 🛠️ Tools & Utilities

- **[tools/README.md](tools/README.md)** - Guida strumenti sviluppo
- **[tools/bump-version.php](tools/bump-version.php)** - Script bump versione
- **[tools/verification/](tools/verification/)** - Script di verifica

---

## 📋 File Configurazione

### Build System

- **[package.json](package.json)** - Configurazione npm
- **[build-optimize.js](build-optimize.js)** - Build system JavaScript
- **[composer.json](composer.json)** - Dipendenze PHP

### Quality Assurance

- **[phpunit.xml.dist](phpunit.xml.dist)** - Configurazione PHPUnit
- **[phpcs.xml.dist](phpcs.xml.dist)** - Configurazione PHP_CodeSniffer

### WordPress

- **[fp-experiences.php](fp-experiences.php)** - File principale plugin
- **[uninstall.php](uninstall.php)** - Script disinstallazione

---

## 🗂️ Struttura Completa

```
fp-experiences/
├── README.md                          ← Documentazione principale
├── RELEASE_NOTES_v0.3.7.md           ← Note di rilascio
├── UPGRADE_GUIDE_v0.3.7.md           ← Guida aggiornamento
├── DOCUMENTATION_INDEX.md             ← Questo file
│
├── docs/                              ← Documentazione organizzata
│   ├── README.md                      ← Indice docs/
│   ├── CHANGELOG.md                   ← Cronologia versioni
│   ├── RELEASE-CHECKLIST.md          ← Checklist release
│   │
│   ├── admin/                         ← Guide amministratori
│   │   ├── QUICK-START.md
│   │   ├── IMPORTER-COMPLETO.md
│   │   ├── AUDIT-COMPLETO.md
│   │   └── VERIFICA-COMPLETA.md
│   │
│   ├── developer/                     ← Guide sviluppatori
│   │   ├── QUICK-START-DEV.md
│   │   ├── HOOKS.md
│   │   ├── API.md
│   │   └── SHORTCODES.md
│   │
│   ├── technical/                     ← Documentazione tecnica
│   │   ├── CALENDAR-SYSTEM.md
│   │   ├── BOOKING-FLOW.md
│   │   ├── PRICING.md
│   │   ├── EMAIL-SYSTEM.md
│   │   ├── DATABASE.md
│   │   ├── INTEGRATIONS.md
│   │   └── ARCHITECTURE.md
│   │
│   ├── bug-reports/                   ← Report bug v0.3.7
│   │   ├── README.md                  ← Indice report
│   │   ├── SUMMARY_ALL_BUG_FIXES_2025-10-13.md
│   │   ├── BUG_FIX_RACE_CONDITION_IMPLEMENTED.md
│   │   ├── REGRESSION_ANALYSIS.md
│   │   └── FINAL_BUG_ANALYSIS_COMPLETE.md
│   │
│   └── archived/                      ← Documentazione storica
│
├── tools/                             ← Strumenti sviluppo
│   ├── README.md
│   ├── bump-version.php
│   └── verification/
│
├── src/                               ← Codice sorgente PHP
├── assets/                            ← JavaScript e CSS
├── templates/                         ← Template PHP
└── tests/                             ← Test suite
```

---

## 🎯 Percorsi di Lettura Consigliati

### Per Amministratori

1. [README.md](README.md) - Panoramica
2. [UPGRADE_GUIDE_v0.3.7.md](UPGRADE_GUIDE_v0.3.7.md) - Come aggiornare
3. [docs/admin/QUICK-START.md](docs/admin/QUICK-START.md) - Setup iniziale
4. [docs/CHANGELOG.md](docs/CHANGELOG.md) - Cosa è cambiato

### Per Sviluppatori

1. [README.md](README.md) - Panoramica
2. [docs/developer/QUICK-START-DEV.md](docs/developer/QUICK-START-DEV.md) - Setup dev
3. [docs/bug-reports/SUMMARY_ALL_BUG_FIXES_2025-10-13.md](docs/bug-reports/SUMMARY_ALL_BUG_FIXES_2025-10-13.md) - Bug fix tecnici
4. [docs/developer/HOOKS.md](docs/developer/HOOKS.md) - API hooks

### Per QA Team

1. [RELEASE_NOTES_v0.3.7.md](RELEASE_NOTES_v0.3.7.md) - Cosa testare
2. [docs/bug-reports/REGRESSION_ANALYSIS.md](docs/bug-reports/REGRESSION_ANALYSIS.md) - Verifica regressioni
3. [UPGRADE_GUIDE_v0.3.7.md](UPGRADE_GUIDE_v0.3.7.md) - Checklist test
4. [docs/admin/VERIFICA-COMPLETA.md](docs/admin/VERIFICA-COMPLETA.md) - Procedure verifica

---

## 🔍 Come Trovare Informazioni

### Per Argomento

- **Installazione:** README.md → Installazione
- **Configurazione:** docs/admin/QUICK-START.md
- **Bug Fix:** docs/bug-reports/SUMMARY_ALL_BUG_FIXES_2025-10-13.md
- **API:** docs/developer/API.md
- **Database:** docs/technical/DATABASE.md
- **Changelog:** docs/CHANGELOG.md

### Per Ruolo

- **Utenti:** README.md
- **Admin:** docs/admin/*
- **Developer:** docs/developer/*
- **QA:** docs/bug-reports/*, UPGRADE_GUIDE_v0.3.7.md
- **DevOps:** docs/technical/*, tools/README.md

---

## 📞 Supporto

### Hai Domande?

1. **Cerca nella documentazione** usando questo indice
2. **Consulta FAQ** in README.md o readme.txt
3. **Leggi bug reports** se riguarda un fix recente
4. **Apri issue** su GitHub se non trovi risposta

---

**Maintained by:** Development Team  
**Contact:** [support@example.com](mailto:support@example.com)  
**GitHub:** [Repository](https://github.com/your-repo/fp-experiences)

---

**Versione:** 1.0  
**Data:** 13 Ottobre 2025  
**Status:** ✅ Aggiornato per v0.3.7
