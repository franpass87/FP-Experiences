# 📚 Guida alla Documentazione FP Experiences

> Come navigare, contribuire e mantenere la documentazione del progetto.

---

## 🎯 Filosofia

La documentazione è organizzata per **pubblico di riferimento**:

1. **👨‍💼 Amministratori** - Usano il plugin via WordPress admin
2. **💻 Sviluppatori** - Estendono o contribuiscono al codice
3. **🔧 Tecnici/QA** - Verificano, testano e auditano il sistema

Ogni sezione è **autonoma** ma con **cross-reference** quando necessario.

---

## 📁 Struttura Documentazione

```
docs/
│
├── README.md                    # 🏠 HOME - Indice principale
├── CHANGELOG.md                 # 📝 Cronologia versioni
├── DOCUMENTATION-GUIDE.md       # 📚 Questa guida
├── RELEASE-CHECKLIST.md         # ✅ Checklist pre-release
├── AVAILABILITY-ON-THE-FLY.md   # 🚀 Feature: Disponibilità on-the-fly
│
├── admin/                       # 👨‍💼 PER AMMINISTRATORI
│   ├── ADMIN-GUIDE.md          # Guida completa funzionalità
│   ├── ADMIN-MENU.md           # Struttura menu WordPress
│   ├── IMPORTER-COMPLETO.md    # Import CSV esperienze/locations
│   └── QUICK-START.md          # ⚡ Setup rapido 15 minuti
│
├── developer/                   # 💻 PER SVILUPPATORI
│   ├── FRONTEND-MODULAR-GUIDE.md  # API JavaScript FPFront.*
│   ├── CALENDAR-SIMPLIFIED.md     # Architettura calendario
│   ├── PLAYBOOK.md                # Metodologia sviluppo fasi
│   └── QUICK-START-DEV.md         # ⚡ Setup dev ambiente
│
├── technical/                   # 🔧 DOCUMENTAZIONE TECNICA
│   ├── CALENDAR-SYSTEM.md           # Sistema calendario completo
│   ├── CALENDAR-VERIFICATION-REPORT.md  # Report verifica tecnica
│   ├── AUDIT-COMPLETO.md            # Sicurezza, performance, A11y
│   ├── PRODUCTION-READINESS-REPORT.md   # Checklist produzione
│   ├── TRACKING-AUDIT.md            # Audit analytics/tracking
│   ├── AUDIT_PLUGIN.md              # Audit generale plugin
│   ├── AUDIT_PLUGIN.json            # Audit data JSON
│   └── DEEP-AUDIT.md                # Analisi approfondita codice
│
├── archived/                    # 🗂️ DOCUMENTI STORICI
│   ├── VERIFICA-COMPATIBILITA-FRONTEND-BACKEND.md
│   ├── RIEPILOGO-MODIFICHE.md
│   ├── CONTROLLO-FINALE-PERFETTO.md
│   ├── [altri file storici...]
│   └── [mantenuti per reference storica]
│
└── QA/                          # 🧪 TEST E QUALITY ASSURANCE
    ├── full-regression.md
    ├── phase-01.md
    ├── phase-02.md
    ├── ...
    └── phase-08.md
```

---

## 🎯 Per Chi Legge

### 👨‍💼 Sei un Amministratore?

**Inizia qui:**
1. 📖 **[Quick Start Admin](admin/QUICK-START.md)** - 15 minuti per prima esperienza
2. 📚 **[Guida Admin](admin/ADMIN-GUIDE.md)** - Reference completo funzionalità
3. 📥 **[Guida Importer](admin/IMPORTER-COMPLETO.md)** - Import bulk CSV

**Cosa puoi fare:**
- Creare e gestire esperienze
- Configurare calendario e slot
- Gestire prenotazioni e ordini
- Impostare prezzi e biglietti
- Configurare meeting points
- Gestire gift vouchers
- Personalizzare branding
- Integrazioni esterne (Google, Brevo, Analytics)

### 💻 Sei uno Sviluppatore?

**Inizia qui:**
1. 🚀 **[Quick Start Dev](developer/QUICK-START-DEV.md)** - Setup ambiente in 5 minuti
2. 💡 **[Frontend Modular](developer/FRONTEND-MODULAR-GUIDE.md)** - API JavaScript
3. 📅 **[Sistema Calendario](developer/CALENDAR-SIMPLIFIED.md)** - Architettura calendario

**Cosa puoi fare:**
- Estendere funzionalità plugin
- Creare custom integrations
- Aggiungere endpoint REST API
- Modificare template frontend
- Contribuire al core
- Sviluppare addon/extensions

### 🔧 Sei un Tecnico/QA?

**Inizia qui:**
1. ✅ **[Calendar System](technical/CALENDAR-SYSTEM.md)** - Verifica completa sistema
2. 🔍 **[Audit Completo](technical/AUDIT-COMPLETO.md)** - Sicurezza e performance
3. 📊 **[Production Readiness](technical/PRODUCTION-READINESS-REPORT.md)** - Checklist

**Cosa puoi fare:**
- Verificare integrità sistema
- Audit sicurezza e performance
- Test regressione completo
- Validare release pre-produzione
- Monitorare tracking e analytics

---

## 📝 Come Contribuire alla Documentazione

### 1. Trova il File Giusto

**Regole di posizionamento:**

| Se documenti... | Metti in... |
|----------------|-------------|
| Feature per admin | `docs/admin/` |
| API o codice per dev | `docs/developer/` |
| Audit, test, verifiche | `docs/technical/` |
| Funzionalità trasversale | `docs/` (root) |
| File storico/obsoleto | `docs/archived/` |

### 2. Segui il Template

**Ogni documento dovrebbe avere:**

```markdown
# Titolo Documento

> Breve descrizione (1-2 righe)

---

## 📋 Indice (se lungo)

---

## Sezioni principali

### Sottosezioni

---

## Link Utili / Risorse

---

**Ultimo aggiornamento:** [Data]
```

### 3. Usa Emoji Consistenti

| Emoji | Uso |
|-------|-----|
| 📚 | Documentazione generale |
| 🚀 | Quick start, setup rapidi |
| 🎯 | Obiettivi, target, scopi |
| ✨ | Feature nuove, highlights |
| 🔧 | Fix, troubleshooting |
| 💻 | Codice, sviluppo |
| 👨‍💼 | Admin, gestione |
| 🔐 | Sicurezza |
| ⚡ | Performance |
| ♿ | Accessibilità |
| 🌍 | i18n, localizzazione |
| 🧪 | Test, QA |
| 📊 | Analytics, reports |
| ✅ | Checklist, completato |
| ⚠️ | Avvisi, attenzione |
| 💡 | Tips, suggerimenti |
| 🆘 | Supporto, aiuto |

### 4. Cross-Reference

**Collegamenti tra documenti:**

```markdown
<!-- Relativo alla cartella docs -->
Vedi [Quick Start](admin/QUICK-START.md)

<!-- Dal README root -->
Vedi [Documentazione](docs/README.md)

<!-- Link esterno -->
Vedi [WordPress Codex](https://codex.wordpress.org/)
```

### 5. Code Blocks

**Usa sintassi highlighting:**

```markdown
```php
// PHP code
function my_function() {
    return true;
}
```

```javascript
// JavaScript code
function myFunction() {
    return true;
}
```

```bash
# Shell commands
npm install
composer update
```
```

### 6. Tabelle

**Per confronti e reference:**

```markdown
| Colonna 1 | Colonna 2 | Colonna 3 |
|-----------|-----------|-----------|
| Valore A  | Valore B  | Valore C  |
```

### 7. Aggiorna il CHANGELOG

**Quando aggiungi feature documentata:**

```markdown
## [Versione] - Data

### 📚 Documentazione
- Aggiunta guida [Nome Guida]
- Aggiornato [File] con [cosa]
```

---

## 🔄 Workflow Aggiornamenti

### Update Routine

```bash
# 1. Pull latest changes
git pull origin main

# 2. Crea branch
git checkout -b docs/update-admin-guide

# 3. Modifica file
vim docs/admin/ADMIN-GUIDE.md

# 4. Commit
git add docs/
git commit -m "docs(admin): update admin guide with new feature"

# 5. Push e PR
git push origin docs/update-admin-guide
```

### Commit Messages

**Format:** `docs(scope): description`

**Scopes:**
- `admin` - Documentazione amministratori
- `dev` - Documentazione sviluppatori
- `technical` - Documentazione tecnica
- `general` - Documentazione generale

**Esempi:**
```
docs(admin): add troubleshooting section
docs(dev): update API reference
docs(technical): add security audit report
docs(general): update README with new structure
```

---

## 🔍 Review Checklist

Prima di committare documentazione:

- [ ] **Leggibilità:** Testo chiaro e semplice?
- [ ] **Correttezza:** Info tecniche accurate?
- [ ] **Completezza:** Copre tutti i casi d'uso?
- [ ] **Link:** Tutti i link funzionano?
- [ ] **Code samples:** Codice testato e funzionante?
- [ ] **Formatting:** Markdown corretto?
- [ ] **Emoji:** Usati in modo consistente?
- [ ] **Data:** Ultimo aggiornamento inserito?
- [ ] **CHANGELOG:** Aggiornato se necessario?
- [ ] **Index:** docs/README.md aggiornato?

---

## 📐 Best Practices

### ✅ DO

- ✅ Scrivi per il pubblico target
- ✅ Usa esempi pratici e reali
- ✅ Includi screenshot se aiutano
- ✅ Mantieni consistenza terminologia
- ✅ Aggiorna data ultimo aggiornamento
- ✅ Cross-reference documenti correlati
- ✅ Usa tabelle per confronti
- ✅ Codice con syntax highlighting

### ❌ DON'T

- ❌ Gergo tecnico senza spiegazione
- ❌ Assumi conoscenze pregresse
- ❌ Link esterni senza contesto
- ❌ Codice senza commenti
- ❌ Documenti troppo lunghi (split!)
- ❌ Info duplicate in più file
- ❌ Riferimenti hard-coded a versioni
- ❌ Screenshot obsoleti

---

## 🗑️ Policy Archiviazione

### Quando Archiviare

Un documento va in `docs/archived/` quando:

1. **Obsoleto** - Info non più rilevanti
2. **Sostituito** - Nuovo documento copre stesso topic
3. **Storico** - Utile per reference ma non attuale
4. **Deprecato** - Feature rimossa dal plugin

### Come Archiviare

```bash
# Move to archived
mv docs/OLD-DOCUMENT.md docs/archived/

# Add note in archived file
echo "\n---\n**⚠️ ARCHIVED:** This document is obsolete. See [NEW-DOCUMENT.md](../NEW-DOCUMENT.md)\n" >> docs/archived/OLD-DOCUMENT.md

# Update references
grep -r "OLD-DOCUMENT.md" docs/
# Replace con NEW-DOCUMENT.md

# Commit
git commit -m "docs: archive OLD-DOCUMENT, replaced by NEW-DOCUMENT"
```

---

## 🔧 Tools Utili

### Markdown Linting

```bash
# Install markdownlint-cli
npm install -g markdownlint-cli

# Lint docs
markdownlint docs/**/*.md

# Auto-fix
markdownlint --fix docs/**/*.md
```

### Link Checking

```bash
# Install markdown-link-check
npm install -g markdown-link-check

# Check links
find docs -name "*.md" -exec markdown-link-check {} \;
```

### Preview Locale

```bash
# Con VS Code
# Usa Markdown Preview Enhanced extension

# Con grip (GitHub style)
pip install grip
grip docs/README.md
# Apri http://localhost:6419
```

---

## 📊 Metrics

Monitoriamo la qualità della documentazione:

| Metric | Target | Attuale |
|--------|--------|---------|
| **Copertura features** | 100% | ✅ 100% |
| **Link rotti** | 0 | ✅ 0 |
| **File obsoleti** | < 5% | ✅ ~3% |
| **Aggiornamenti mese** | > 5 | 📊 Variabile |
| **Feedback positivo** | > 80% | 📊 N/A |

---

## 🆘 Serve Aiuto?

### Per Documentazione

- 📖 **Leggi:** [docs/README.md](README.md)
- 💬 **Discussioni:** [GitHub Discussions](https://github.com/your-repo/discussions)
- 🐛 **Issue:** [GitHub Issues](https://github.com/your-repo/issues) (label: `documentation`)

### Per Codice

- 💻 **Dev Guide:** [developer/QUICK-START-DEV.md](developer/QUICK-START-DEV.md)
- 🔧 **Technical:** [technical/](technical/)
- 📧 **Email:** dev@formazionepro.it

---

## 🎉 Contributors

Grazie a tutti coloro che contribuiscono a migliorare la documentazione! 

**Hall of Fame:**
- Development Team
- Documentation Team
- Community Contributors

---

**Versione guida:** 1.0  
**Ultimo aggiornamento:** 7 Ottobre 2025  
**Maintainer:** Documentation Team