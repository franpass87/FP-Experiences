# 🎫 FP Experiences

> Plugin WordPress per booking di esperienze turistiche stile GetYourGuide

[![Version](https://img.shields.io/badge/version-0.3.7-blue.svg)](https://github.com/franpass87/FP-Experiences)
[![WordPress](https://img.shields.io/badge/wordpress-6.2+-green.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/php-8.0+-purple.svg)](https://www.php.net/)

---

## 📋 Indice

- [Caratteristiche](#caratteristiche)
- [Requisiti](#requisiti)
- [Installazione](#installazione)
- [Configurazione Rapida](#configurazione-rapida)
- [Struttura Plugin](#struttura-plugin)
- [Documentazione](#documentazione)
- [Sviluppo](#sviluppo)
- [Licenza](#licenza)

---

## ✨ Caratteristiche

### **Core Features**
- 🎫 **Custom Post Type** - Esperienze con meta fields completi
- 📅 **Calendario Dinamico** - Slot, ricorrenze, eccezioni
- 🛒 **Carrello Isolato** - Non usa WooCommerce cart
- 💳 **Checkout Dedicato** - Form custom + integrazione pagamenti
- 📧 **Email Automatiche** - Conferme, reminder, modifiche
- 🎁 **Gift Vouchers** - Buoni regalo acquistabili e riscattabili

### **Integrazioni**
- ✉️ **Brevo** - Email marketing e automazioni
- 📊 **Google Analytics 4** - Tracking eventi
- 📅 **Google Calendar** - Sincronizzazione automatica
- 🎯 **Meta Pixel** - Tracking conversioni Facebook
- 📈 **Google Ads** - Conversion tracking
- 🔍 **Microsoft Clarity** - Session recording

### **Frontend**
- 📱 **Responsive** - Mobile-first design
- ⚡ **Performance** - Lazy load, caching, ottimizzazioni
- ♿ **Accessibile** - WCAG 2.1 compliant
- 🎨 **Tematizzabile** - Branding customizzabile
- 🧩 **Elementor** - 6 widget dedicati
- 📝 **Shortcodes** - 8 shortcode disponibili

### **Backend**
- 🎯 **Setup Guidato** - Checklist configurazione
- 📊 **Dashboard** - Metriche e panoramica
- 🛠️ **Tools** - Utility manutenzione
- 📝 **Logs** - Sistema logging integrato
- 🔒 **Sicuro** - Capabilities, nonce, sanitizzazione
- 🌍 **Multilingua** - Pronto per traduzioni

---

## 📦 Requisiti

| Requisito | Versione | Note |
|-----------|----------|------|
| **WordPress** | ≥ 6.2 | Testato fino a 6.7 |
| **PHP** | ≥ 8.0 | Raccomandato 8.1+ |
| **MySQL** | ≥ 5.7 | O MariaDB ≥ 10.2 |
| **WooCommerce** | ≥ 7.0 | Per pagamenti (opzionale) |

---

## 🚀 Installazione

### **Via Git (Sviluppo)**

```bash
cd wp-content/plugins
git clone https://github.com/franpass87/FP-Experiences.git
cd FP-Experiences
composer install
npm install
```

### **Via ZIP (Produzione)**

1. Scarica l'ultimo release da GitHub
2. Carica via WP Admin → Plugin → Aggiungi Nuovo
3. Attiva il plugin

---

## ⚙️ Configurazione Rapida

### **1. Setup Iniziale**

Dopo l'attivazione, vai alla Dashboard:
```
WP Admin → FP Experiences → Dashboard
```

Segui la **Setup Checklist** che ti guida attraverso:
1. ✅ Crea la tua prima esperienza
2. ✅ Configura calendario disponibilità
3. ✅ Configura metodo di pagamento
4. ✅ Crea pagina Checkout
5. ✅ Configura email (opzionale)

### **2. Crea Prima Esperienza**

```
WP Admin → FP Experiences → Nuova Esperienza
```

Compila:
- Titolo, descrizione, immagine
- Prezzi e ticket types
- Meeting point
- Schedule rules (calendario)
- FAQ e dettagli

### **3. Crea Pagina Checkout**

```
WP Admin → Pagine → Aggiungi Nuova
Titolo: Checkout
Contenuto: [fp_exp_checkout]
Pubblica
```

### **4. Configura Pagamenti**

```
WP Admin → WooCommerce → Impostazioni → Pagamenti
Abilita: Stripe, PayPal, o altro gateway
```

### **5. Inserisci Esperienza nel Sito**

Usa shortcode o Elementor:

```
[fp_exp_page id="123"]          - Pagina esperienza completa
[fp_exp_list]                   - Lista esperienze
[fp_exp_calendar]               - Calendario standalone
```

---

## 📁 Struttura Plugin

```
FP-Experiences/
├── 📄 fp-experiences.php       # File principale
├── 📄 composer.json            # Dipendenze PHP
├── 📄 package.json             # Dipendenze JS
├── 📄 README.md                # Questo file
│
├── 📂 src/                     # Codice sorgente PHP (PSR-4)
│   ├── Plugin.php              # Classe principale
│   ├── Activation.php          # Hook attivazione
│   ├── Admin/                  # Interfaccia backend
│   ├── Api/                    # REST API endpoints
│   ├── Booking/                # Sistema prenotazioni
│   ├── PostTypes/              # CPT Experiences & Meeting Points
│   ├── Shortcodes/             # Shortcode frontend
│   ├── Elementor/              # Widget Elementor
│   ├── Integrations/           # Brevo, GA4, Calendar, etc.
│   └── Utils/                  # Helper e utility
│
├── 📂 assets/                  # Asset frontend/backend
│   ├── css/                    # Stili
│   ├── js/                     # JavaScript
│   └── svg/                    # Icone
│
├── 📂 templates/               # Template PHP
│   ├── admin/                  # Template backend
│   ├── emails/                 # Template email
│   └── front/                  # Template frontend
│
├── 📂 docs/                    # Documentazione
│   ├── admin/                  # Guide amministratore
│   ├── developer/              # Guide sviluppatore
│   ├── features/               # Documentazione feature
│   ├── bug-fixes/              # Fix applicati
│   ├── releases/               # Release notes
│   ├── ux/                     # Miglioramenti UX
│   ├── deployment/             # Deploy e setup
│   ├── verification/           # Test e QA
│   └── technical/              # Documentazione tecnica
│
├── 📂 tools/                   # Script utility
│   ├── bump-version.php        # Versioning
│   └── wp-*.sh                 # Script WordPress CLI
│
├── 📂 tests/                   # Unit tests
│   └── Booking/                # Test modulo booking
│
└── 📂 languages/               # Traduzioni
    └── fp-experiences.pot      # Template traduzioni
```

---

## 📚 Documentazione

### **Per Amministratori**
- [Guida Rapida](docs/admin/QUICK-START.md) - Primi passi
- [Menu Admin](docs/admin/ADMIN-MENU.md) - Panoramica menu
- [Importer CSV](docs/admin/IMPORTER-COMPLETO.md) - Import esperienze

### **Per Sviluppatori**
- [Quick Start Dev](docs/developer/QUICK-START-DEV.md) - Setup sviluppo
- [Playbook](docs/developer/PLAYBOOK.md) - Best practices
- [Frontend Guide](docs/developer/FRONTEND-MODULAR-GUIDE.md) - Moduli frontend

### **Feature & UX**
- [Miglioramenti UX](docs/ux/UX-IMPROVEMENTS-COMPLETE.md) - Tutti i miglioramenti
- [Settings UI](docs/ux/SETTINGS-UI-IMPROVEMENTS.md) - Design settings
- [Riepilogo Finale](docs/ux/FINAL-SUMMARY.md) - Summary completo

### **Technical**
- [Architettura](docs/technical/MODULAR-ARCHITECTURE.md) - Struttura codice
- [Security](docs/technical/SECURITY_FIXES_APPLIED.md) - Fix sicurezza
- [Changelog](docs/CHANGELOG.md) - Storia modifiche

---

## 🛠️ Sviluppo

### **Setup Ambiente**

```bash
# Clone repository
git clone https://github.com/franpass87/FP-Experiences.git
cd FP-Experiences

# Install dependencies
composer install
npm install

# Build assets
npm run build
```

### **Build Commands**

```bash
npm run build           # Build production
npm run dev             # Build development
npm run watch           # Watch mode
```

### **Testing**

```bash
# PHP Unit tests
composer test

# PHP Syntax check
./tools/run-php-syntax-check.sh

# WordPress CLI tests
./tools/wp-qa-all.sh
```

---

## 🎨 Shortcodes Disponibili

| Shortcode | Descrizione | Parametri |
|-----------|-------------|-----------|
| `[fp_exp_page]` | Pagina esperienza completa | `id`, `sections`, `theme` |
| `[fp_exp_list]` | Lista esperienze | `limit`, `category`, `layout` |
| `[fp_exp_calendar]` | Calendario interattivo | `experience_id`, `month` |
| `[fp_exp_checkout]` | Form checkout | - |
| `[fp_exp_widget]` | Widget esperienza | `id`, `style` |
| `[fp_exp_archive]` | Archivio esperienze | `layout`, `filters` |
| `[fp_exp_gift_redeem]` | Riscatta voucher | - |

**Documentazione completa:** [docs/admin/ADMIN-GUIDE.md](docs/admin/ADMIN-GUIDE.md)

---

## 🔌 Elementor Widgets

- **FP Experience List** - Griglia/lista esperienze
- **FP Experience Widget** - Card singola esperienza
- **FP Experience Calendar** - Calendario prenotazioni
- **FP Experience Checkout** - Form checkout
- **FP Experience Page** - Pagina completa
- **FP Meeting Points** - Mappa punti d'incontro

---

## 📊 REST API

**Namespace:** `/fp-exp/v1`

### **Principali Endpoint**

| Endpoint | Metodo | Descrizione |
|----------|--------|-------------|
| `/availability` | GET | Slot disponibili per data |
| `/cart/set` | POST | Aggiungi al carrello |
| `/cart/status` | GET | Stato carrello |
| `/checkout` | POST | Finalizza prenotazione |
| `/calendar/slots` | GET | Elenco slot calendario |
| `/gift/purchase` | POST | Acquista voucher |

**Documentazione API:** [docs/technical/](docs/technical/)

---

## 🤝 Contribuire

1. Fork il repository
2. Crea branch feature (`git checkout -b feature/AmazingFeature`)
3. Commit modifiche (`git commit -m 'Add AmazingFeature'`)
4. Push al branch (`git push origin feature/AmazingFeature`)
5. Apri Pull Request

### **Coding Standards**

- PSR-4 autoloading
- PSR-12 coding style
- WordPress Coding Standards (PHPCS)
- PHPStan level 5

---

## 📝 Changelog

Vedi [docs/CHANGELOG.md](docs/CHANGELOG.md) per la storia completa delle modifiche.

### **v0.3.7+ (Corrente)**
- ✅ Bug fix traduzioni WordPress 6.7+
- ✅ Fix `register_meta` default values
- ✅ Fix `map_meta_cap` capabilities
- ✅ Setup checklist banner
- ✅ Integration status badges
- ✅ Toast notifications system
- ✅ Empty states migliorati
- ✅ UI/UX improvements completi

---

## 📄 Licenza

GPL v2 or later

```
Copyright (C) 2024 Francesco Passeri

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

---

## 👤 Autore

**Francesco Passeri**
- Website: [francescopasseri.com](https://francescopasseri.com)
- GitHub: [@franpass87](https://github.com/franpass87)

---

## 🆘 Supporto

- 📖 **Documentazione:** [docs/](docs/)
- 🐛 **Bug Reports:** [GitHub Issues](https://github.com/franpass87/FP-Experiences/issues)
- 💬 **Discussioni:** [GitHub Discussions](https://github.com/franpass87/FP-Experiences/discussions)

---

## 🎯 Use Case

**Perfetto per:**
- Tour operator locali
- Guide turistiche
- Esperienze enogastronomiche
- Attività outdoor
- Cooking class
- Workshop creativi
- Eventi privati

**Ottimizzato per:**
- Single business (1 cliente)
- Poche esperienze (3-20)
- Booking diretto dal sito
- Gestione semplificata

---

## 🚀 Quick Start

```bash
# 1. Attiva plugin
wp plugin activate FP-Experiences

# 2. Vai alla Dashboard
http://your-site.com/wp-admin/admin.php?page=fp_exp_dashboard

# 3. Segui la Setup Checklist (5 step)

# 4. Crea la tua prima esperienza

# 5. Pubblica e accetta prenotazioni!
```

---

**Fatto con ❤️ per semplificare il booking di esperienze**
