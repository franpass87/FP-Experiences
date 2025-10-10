# FP Experiences - WordPress Plugin

> Plugin WordPress professionale per la gestione di esperienze turistiche, prenotazioni online, e calendario disponibilità.

[![Version](https://img.shields.io/badge/version-0.3.6-blue.svg)](CHANGELOG.md)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](LICENSE)

---

## 📋 Indice

- [Caratteristiche](#-caratteristiche)
- [Requisiti](#-requisiti)
- [Installazione](#-installazione)
- [Configurazione Rapida](#-configurazione-rapida)
- [Documentazione](#-documentazione)
- [Build e Sviluppo](#-build-e-sviluppo)
- [Deployment Automatico](#-deployment-automatico)
- [Changelog](#-changelog)
- [Supporto](#-supporto)

---

## ✨ Caratteristiche

### 🎯 Core Features

- **📅 Sistema Calendario Avanzato**
  - Ricorrenze settimanali con time slots
  - Generazione automatica slot per 12 mesi
  - Capacità e buffer configurabili
  - On-the-fly availability via REST API

- **🎟️ Gestione Prenotazioni**
  - Checkout WooCommerce integrato
  - Request-to-Book con approvazione manuale
  - Biglietti multipli per esperienza
  - Add-ons opzionali con immagini

- **📍 Meeting Points**
  - Gestione locations con coordinate GPS
  - Import CSV in massa
  - Integrazione Google Maps
  - Multiple locations per esperienza

- **🎁 Gift Vouchers**
  - Acquisto buoni regalo
  - Redemption workflow completo
  - Reminder automatici pre-scadenza
  - Gestione validità e estensioni

### 🔧 Admin Experience

- **🎨 Interfaccia Moderna**
  - UI stile GetYourGuide
  - Gallery manager con drag & drop
  - Badge configurabili per esperienze
  - Branding personalizzabile

- **📊 Dashboard e Analytics**
  - Calendario admin con filtri
  - Console check-in prenotazioni
  - Gestione ordini e richieste
  - Log system completo

- **📧 Email Transazionali**
  - Template personalizzabili
  - Integrazione Brevo
  - Placeholder dinamici
  - Preview live admin

### 🔌 Integrazioni

- **WooCommerce** - Checkout e pagamenti
- **Elementor** - Widget dedicati
- **Google Calendar** - Sync bidirezionale
- **Brevo** - Email marketing
- **Google Analytics 4** - Tracking avanzato
- **Meta Pixel** - Conversioni Facebook
- **Google Ads** - Tracking conversioni
- **Microsoft Clarity** - Heatmaps e sessioni

---

## 💻 Requisiti

### Ambiente

| Requisito | Versione Minima | Consigliata |
|-----------|-----------------|-------------|
| **PHP** | 8.1 | 8.2+ |
| **WordPress** | 6.0 | 6.4+ |
| **MySQL** | 5.7 | 8.0+ |
| **WooCommerce** | 7.0 | 8.0+ |

### Plugin Dipendenze

- **WooCommerce** (richiesto) - Per gestione checkout e ordini
- **Elementor** (opzionale) - Per usare i widget dedicati

### Estensioni PHP

- `json` - Gestione dati JSON
- `curl` - Chiamate HTTP/API
- `mysqli` - Database
- `mbstring` - Gestione stringhe multibyte

---

## 🚀 Installazione

### Metodo 1: Upload ZIP (Consigliato)

1. Scarica l'ultimo release da [GitHub Releases](https://github.com/your-repo/releases)
2. WordPress Admin → Plugin → Aggiungi nuovo → Carica plugin
3. Seleziona il file ZIP e clicca "Installa ora"
4. Attiva il plugin

### Metodo 2: FTP/SFTP

1. Estrai il file ZIP
2. Carica la cartella `fp-experiences` in `/wp-content/plugins/`
3. Vai su WordPress Admin → Plugin
4. Attiva "FP Experiences"

### Metodo 3: WP-CLI

```bash
wp plugin install fp-experiences.zip --activate
```

---

## ⚙️ Configurazione Rapida

### 1. Setup Iniziale

Dopo l'attivazione, il plugin crea automaticamente:
- ✅ Tabelle database necessarie
- ✅ Ruoli e capability (`fp_operator`, `fp_manager`)
- ✅ Pagine WordPress (se configurate)
- ✅ Tassonomie custom

### 2. Impostazioni Base

**FP Experiences → Impostazioni → Generali**
```
✓ Imposta timezone
✓ Configura ruoli e permessi
✓ Abilita modulo meeting points
✓ Imposta pagine sistema
```

**Impostazioni → Branding**
```
✓ Logo e colori brand
✓ Colori sezioni
✓ Icone Font Awesome
```

### 3. Prima Esperienza

1. **FP Experiences → Aggiungi nuova esperienza**
2. **Tab Dettagli:**
   - Titolo e descrizione
   - Gallery immagini (drag & drop)
   - Lingue disponibili
   - Badge showcase

3. **Tab Prezzi:**
   - Aggiungi tipi biglietto
   - Imposta prezzi base
   - Configura add-ons opzionali

4. **Tab Calendario:**
   - Seleziona giorni settimana
   - Aggiungi slot orari
   - Imposta capacità generale
   - Configura buffer temporali

5. **Tab Meeting Point:**
   - Seleziona location primaria
   - Aggiungi locations alternative

6. **Pubblica l'esperienza**

Gli slot vengono generati automaticamente al salvataggio!

### 4. Shortcode Disponibili

**Pagina esperienza singola:**
```
[fp_exp_page id="123"]
```

**Archivio esperienze:**
```
[fp_exp_simple_archive]
```

**Meeting points:**
```
[fp_exp_meeting_points id="123"]
```

**Riscatto gift voucher:**
```
[fp_exp_gift_redeem]
```

---

## 📚 Documentazione

La documentazione completa è organizzata in **[docs/](docs/README.md)**:

### Per Amministratori
- 📖 **[Admin Guide](docs/admin/ADMIN-GUIDE.md)** - Guida completa interfaccia admin
- 🗺️ **[Menu Admin](docs/admin/ADMIN-MENU.md)** - Struttura navigazione
- 📥 **[Guida Importer](docs/admin/IMPORTER-COMPLETO.md)** - Import CSV esperienze

### Per Sviluppatori
- 💻 **[Frontend Modular Guide](docs/developer/FRONTEND-MODULAR-GUIDE.md)** - API JavaScript
- 📅 **[Sistema Calendario](docs/developer/CALENDAR-SIMPLIFIED.md)** - Architettura calendario
- 🛠️ **[Playbook](docs/developer/PLAYBOOK.md)** - Workflow sviluppo

### Documentazione Tecnica
- 🔍 **[Calendar System](docs/technical/CALENDAR-SYSTEM.md)** - Verifica sistema calendario
- 📊 **[Audit Completo](docs/technical/AUDIT-COMPLETO.md)** - Sicurezza e performance
- ✅ **[Production Readiness](docs/technical/PRODUCTION-READINESS-REPORT.md)** - Checklist

---

## 🛠️ Build e Sviluppo

### Setup Ambiente Dev

```bash
# Clone repository
git clone https://github.com/your-repo/fp-experiences.git
cd fp-experiences

# Installa dipendenze
composer install
npm install
```

### Build Plugin

```bash
# Build con bump version automatico
bash build.sh --bump=patch    # 0.3.4 → 0.3.5
bash build.sh --bump=minor    # 0.3.4 → 0.4.0
bash build.sh --bump=major    # 0.3.4 → 1.0.0

# Build con versione specifica
bash build.sh --set-version=1.0.0

# Build semplice (senza bump)
bash build.sh
```

Il package finale viene creato in `/build/fp-experiences-{version}.zip`

### Testing

```bash
# Syntax check PHP
bash tools/run-php-syntax-check.sh

# Verifica calendario
bash tools/verification/verify-calendar-system.sh

# Test flusso dati
php tools/verification/test-calendar-data-flow.php

# PHPUnit (se configurato)
composer test
```

### Linting

```bash
# PHP CodeSniffer
composer run phpcs

# ESLint
npm run lint

# Fix automatico
composer run phpcbf
npm run lint:fix
```

---

## 🚀 Deployment Automatico

Il plugin è configurato con **GitHub Actions** per deployment automatico su merge.

### Cosa Succede Automaticamente

Ogni volta che fai un **merge su `main`**:

1. ✅ GitHub Actions crea la build del plugin
2. ✅ Viene creata una release su GitHub con il file ZIP
3. ✅ (Opzionale) Il plugin viene deployato automaticamente su WordPress

### Setup Rapido (5 minuti)

#### Opzione A: GitHub Updater (CONSIGLIATO)

1. Installa [GitHub Updater](https://github.com/afragen/github-updater) su WordPress
2. Configura con il tuo repository
3. ✅ WordPress si aggiorna automaticamente ad ogni release!

#### Opzione B: Deploy Diretto SSH

1. Configura i secrets su GitHub (vedi documentazione)
2. Abilita `ENABLE_WP_DEPLOY=true`
3. ✅ Deploy automatico via SSH ad ogni merge!

### Aggiorna Versione

```bash
# Usa lo script helper
.github/scripts/update-version.sh 0.3.7

# Poi commit e push su main
git commit -am "Bump version to 0.3.7"
git push origin main

# GitHub Actions fa il resto! 🎉
```

### 📖 Documentazione Completa

- 📘 **[Setup Rapido](DEPLOYMENT-SETUP.md)** - Configurazione in 5 minuti
- 📗 **[Guida Completa](.github/DEPLOYMENT.md)** - Tutte le opzioni di deployment
- 📙 **[Test Sistema](.github/QUICK-TEST.md)** - Come testare il deployment
- 📕 **[Riepilogo](GITHUB-DEPLOYMENT-SUMMARY.md)** - Panoramica generale

### Workflow Disponibili

| Workflow | Trigger | Funzione |
|----------|---------|----------|
| `deploy-on-merge.yml` | Push su `main` | Build + Release + Deploy |
| `build-zip.yml` | Push/tag | Solo build |
| `build-plugin-zip.yml` | Push su main/tag | Build ZIP |

---

## 📝 Changelog

Vedi **[CHANGELOG.md](docs/CHANGELOG.md)** per la cronologia completa.

### [0.3.4] - 2025-01-27

**Ottimizzazioni:**
- ✨ Riorganizzazione completa documentazione
- 📚 Nuova struttura docs/ con cartelle per pubblico
- 🗂️ Archiviazione file obsoleti
- 📖 Nuovo indice principale con navigazione migliorata
- 🔍 Guide quick-start per admin e dev

### [0.3.3] - 2025-01-27

**Miglioramenti Admin:**
- ✨ Filtraggio esperienza nel calendario admin
- 🎨 UI/UX migliorata per check-in e gestione ordini
- 📧 Potenziamento sezione email con layout moderno
- 🔍 Logs avanzati con filtri e diagnostica
- ♿ Accessibilità migliorata
- 🇮🇹 Localizzazione italiana completa

### [0.3.2] - 2025-01-26
- Hero gallery manager con drag & drop
- Lingue ed esperienza con preview badge
- Biblioteca badge configurabile
- Controlli branding estesi
- Fix UI ticket e CTA sticky

### [0.3.0] - 2025-09-30
- Gift Your Experience workflow
- Meeting point importer CSV
- Pagine dedicate auto-generate
- Simple archive layout
- ISO language flags
- Migrazione runner

---

## 🆘 Supporto

### Problemi Comuni

**Calendario non mostra slot:**
```
✓ Verifica esperienza pubblicata
✓ Controlla giorni settimana selezionati
✓ Verifica time_slots configurati
✓ Controlla capacità generale > 0
```

**Modifiche non salvate:**
```
✓ Abilita WP_DEBUG
✓ Controlla logs in wp-content/debug.log
✓ Verifica permessi utente
✓ Ispeziona Network tab browser
```

**Errori REST API:**
```
✓ Verifica permalink settings
✓ Controlla .htaccess
✓ Testa endpoint con Postman
✓ Verifica nonce e auth
```

### Debug Mode

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### Risorse

- 📖 **[Documentazione Completa](docs/README.md)**
- 🐛 **[Issue Tracker](https://github.com/your-repo/issues)**
- 💬 **[Discussions](https://github.com/your-repo/discussions)**
- 📧 **Email:** support@formazionepro.it

---

## 🤝 Contribuire

Contributi benvenuti! Per contribuire:

1. Fork il repository
2. Crea branch feature (`git checkout -b feature/AmazingFeature`)
3. Commit modifiche (`git commit -m 'Add AmazingFeature'`)
4. Push al branch (`git push origin feature/AmazingFeature`)
5. Apri Pull Request

### Linee Guida

- ✅ Segui PSR-12 per PHP
- ✅ Usa ESLint per JavaScript
- ✅ Scrivi test per nuove feature
- ✅ Aggiorna documentazione
- ✅ Mantieni retrocompatibilità

---

## 📄 Licenza

Questo plugin è rilasciato sotto licenza **GPL v2 o successiva**.

```
FP Experiences WordPress Plugin
Copyright (C) 2024-2025 Formazione Pro

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

---

## 👥 Credits

**Sviluppato da:** [Formazione Pro](https://formazionepro.it)

**Contributors:**
- Development Team
- QA Team
- Documentation Team

---

## 🔗 Link Utili

- **[Documentazione](docs/README.md)**
- **[Changelog](docs/CHANGELOG.md)**
- **[Release Notes](https://github.com/your-repo/releases)**
- **[Roadmap](https://github.com/your-repo/projects)**

---

**Versione:** 0.3.4  
**Ultimo aggiornamento:** 7 Ottobre 2025  
**Status:** ✅ Production Ready