# 📚 Documentazione FP Experiences

Benvenuto nella documentazione del plugin **FP Experiences** per WordPress. Questa guida è organizzata per ruolo e tipo di contenuto per facilitare la navigazione.

---

## 🚀 Quick Start

### Per Amministratori
- **[Guida Admin](admin/ADMIN-GUIDE.md)** - Gestione completa del plugin dall'interfaccia admin
- **[Menu Admin](admin/ADMIN-MENU.md)** - Struttura e navigazione del menu
- **[Guida Importer](admin/IMPORTER-COMPLETO.md)** - Import esperienze e meeting points in massa

### Per Sviluppatori
- **[Frontend Modulare](developer/FRONTEND-MODULAR-GUIDE.md)** - API moduli JavaScript FPFront.*
- **[Sistema Calendario](developer/CALENDAR-SIMPLIFIED.md)** - Architettura calendario semplificato
- **[Playbook Sviluppo](developer/PLAYBOOK.md)** - Metodologia e workflow di sviluppo

### Per Tecnici e QA
- **[Sistema Calendario](technical/CALENDAR-SYSTEM.md)** - Verifica completa sistema calendario
- **[Report Verifica Calendario](technical/CALENDAR-VERIFICATION-REPORT.md)** - Report tecnico dettagliato
- **[Audit Completo](technical/AUDIT-COMPLETO.md)** - Sicurezza, performance, accessibilità
- **[Production Readiness](technical/PRODUCTION-READINESS-REPORT.md)** - Checklist produzione

---

## 📖 Indice per Categoria

### 👨‍💼 Documentazione Admin

| Documento | Descrizione |
|-----------|-------------|
| [ADMIN-GUIDE.md](admin/ADMIN-GUIDE.md) | Guida completa per amministratori del plugin |
| [ADMIN-MENU.md](admin/ADMIN-MENU.md) | Struttura menu e navigazione interfaccia |
| [IMPORTER-COMPLETO.md](admin/IMPORTER-COMPLETO.md) | Import CSV esperienze e meeting points |

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
| [FRONTEND-MODULAR-GUIDE.md](developer/FRONTEND-MODULAR-GUIDE.md) | API moduli JavaScript frontend |
| [CALENDAR-SIMPLIFIED.md](developer/CALENDAR-SIMPLIFIED.md) | Architettura sistema calendario |
| [PLAYBOOK.md](developer/PLAYBOOK.md) | Metodologia sviluppo a fasi |

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
| [CALENDAR-SYSTEM.md](technical/CALENDAR-SYSTEM.md) | Sistema calendario: verifica completa |
| [CALENDAR-VERIFICATION-REPORT.md](technical/CALENDAR-VERIFICATION-REPORT.md) | Report tecnico verifica calendario |
| [AUDIT-COMPLETO.md](technical/AUDIT-COMPLETO.md) | Audit sicurezza, performance, accessibilità |
| [PRODUCTION-READINESS-REPORT.md](technical/PRODUCTION-READINESS-REPORT.md) | Checklist e report produzione |
| [TRACKING-AUDIT.md](technical/TRACKING-AUDIT.md) | Audit tracking e analytics |
| [AUDIT_PLUGIN.md](technical/AUDIT_PLUGIN.md) | Audit generale plugin |
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

**tools/run-php-syntax-check.sh**
```bash
bash tools/run-php-syntax-check.sh
```
Verifica sintassi PHP di tutti i file sorgente e compilati.

---

## 📦 Build e Release

### Build del Plugin

```bash
# Incrementa versione patch (0.3.4 → 0.3.5)
bash build.sh --bump=patch

# Imposta versione specifica
bash build.sh --set-version=1.0.0

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
git tag v1.0.0
git push origin v1.0.0
```

---

## 📋 Changelog e Release Notes

Vedi **[CHANGELOG.md](CHANGELOG.md)** per:
- Cronologia versioni
- Nuove funzionalità
- Bug fix
- Breaking changes
- Note di migrazione

**Versione corrente:** `0.3.7` (13 ottobre 2025)

---

## 🗂️ Struttura Documentazione

```
docs/
├── README.md                    # Questo file
├── CHANGELOG.md                 # Cronologia versioni
├── RELEASE-CHECKLIST.md         # Checklist pre-release
├── AVAILABILITY-ON-THE-FLY.md   # Disponibilità on-the-fly
│
├── admin/                       # Documentazione amministratori
│   ├── ADMIN-GUIDE.md          # Guida completa admin
│   ├── ADMIN-MENU.md           # Struttura menu
│   └── IMPORTER-COMPLETO.md    # Guida import CSV
│
├── developer/                   # Documentazione sviluppatori
│   ├── FRONTEND-MODULAR-GUIDE.md  # Moduli JavaScript
│   ├── CALENDAR-SIMPLIFIED.md     # Sistema calendario
│   └── PLAYBOOK.md                # Workflow sviluppo
│
├── technical/                   # Documentazione tecnica
│   ├── CALENDAR-SYSTEM.md           # Verifica calendario
│   ├── CALENDAR-VERIFICATION-REPORT.md  # Report calendario
│   ├── AUDIT-COMPLETO.md            # Audit completo
│   ├── PRODUCTION-READINESS-REPORT.md   # Produzione
│   ├── TRACKING-AUDIT.md            # Audit tracking
│   ├── AUDIT_PLUGIN.md              # Audit plugin
│   └── DEEP-AUDIT.md                # Analisi approfondita
│
├── archived/                    # Documentazione storica
│   └── [file obsoleti archiviati]
│
└── QA/                          # Test e quality assurance
    ├── full-regression.md
    └── phase-*.md               # Test per fase
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

- **Repository:** [GitHub](https://github.com/your-repo)
- **Issue Tracker:** [GitHub Issues](https://github.com/your-repo/issues)
- **WordPress Plugin Page:** [WordPress.org](https://wordpress.org/plugins/fp-experiences)

---

## 📝 Contribuire

Per contribuire al progetto:

1. Fork il repository
2. Crea un branch per la feature (`git checkout -b feature/AmazingFeature`)
3. Commit le modifiche (`git commit -m 'Add AmazingFeature'`)
4. Push al branch (`git push origin feature/AmazingFeature`)
5. Apri una Pull Request

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

Sviluppato e mantenuto da Formazione Pro.

---

**Ultimo aggiornamento:** 7 Ottobre 2025  
**Versione documentazione:** 2.0