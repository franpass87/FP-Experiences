# 🛠️ Tools & Utility Scripts

Questa cartella contiene script utility per sviluppo, testing e verifica del plugin FP Experiences.

---

## 📁 Struttura

```
tools/
├── README.md                       # Questo file
├── run-php-syntax-check.sh        # Verifica sintassi PHP
└── verification/                   # Script di verifica sistema
    ├── verify-calendar-system.sh  # Verifica automatica calendario
    ├── verify-calendar-system.php # Verifica PHP calendario
    └── test-calendar-data-flow.php # Test flusso dati calendario
```

---

## 🔧 Script Disponibili

### 1. PHP Syntax Check

**File:** `run-php-syntax-check.sh`

**Descrizione:** Verifica la sintassi di tutti i file PHP nel progetto (src/ e build/).

**Uso:**
```bash
bash tools/run-php-syntax-check.sh
```

**Output:**
- ✅ Tutti i file validi → exit code 0
- ❌ Errore sintassi → mostra file e riga, exit code 1

**Quando usare:**
- Prima di ogni commit
- Prima di build/release
- Dopo refactoring esteso
- Come check in CI/CD

---

### 2. Calendar System Verification (Bash)

**File:** `verification/verify-calendar-system.sh`

**Descrizione:** Esegue 34 controlli automatici sul sistema calendario, verificando:
- Backend PHP (Recurrence, AvailabilityService, ExperienceMetaBoxes, RestRoutes)
- Frontend JavaScript (admin.js, availability.js)
- Supporto formato time_slots e retrocompatibilità time_sets
- Presenza endpoint REST API
- Documentazione

**Uso:**
```bash
bash tools/verification/verify-calendar-system.sh
```

**Output:**
```
================================================================================
  1. VERIFICA BACKEND - Recurrence.php
================================================================================
✓ File Recurrence.php trovato
✓ defaults() include 'time_slots'
✓ sanitize() supporta time_slots
...

================================================================================
  RIEPILOGO VERIFICA CALENDARIO
================================================================================
Controlli effettuati: 34
Errori critici:       0
Avvisi:               0

✓ SISTEMA CALENDARIO COMPLETAMENTE VERIFICATO E FUNZIONANTE!
```

**Exit codes:**
- `0` - Tutti i controlli passati ✅
- `1` - Avvisi presenti ⚠️
- `2` - Errori critici trovati ❌

**Quando usare:**
- Dopo modifiche al sistema calendario
- Prima di release
- Per debugging
- Come smoke test

---

### 3. Calendar System Verification (PHP)

**File:** `verification/verify-calendar-system.php`

**Descrizione:** Versione PHP dello script di verifica calendario. Stessi controlli della versione Bash.

**Uso:**
```bash
php tools/verification/verify-calendar-system.php
```

**Note:** Richiede PHP 8.1+ installato. Se PHP non è disponibile, usa la versione Bash.

---

### 4. Calendar Data Flow Test

**File:** `verification/test-calendar-data-flow.php`

**Descrizione:** Simula il flusso completo dei dati del calendario dal form admin al frontend:

1. **Dati raw** dal form admin
2. **Sanitizzazione** (Recurrence::sanitize)
3. **Conversione** in regole (Recurrence::build_rules)
4. **Generazione slot** nel database
5. **Risposta API** JSON
6. **Formattazione frontend** per display

**Uso:**
```bash
php tools/verification/test-calendar-data-flow.php
```

**Output:**
```
=================================================================
  TEST FLUSSO DATI CALENDARIO FP EXPERIENCES
=================================================================

1️⃣  DATI RAW DAL FORM ADMIN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Array (
    [frequency] => weekly
    [days] => Array ( [0] => monday ... )
    [time_slots] => Array ( ... )
)

2️⃣  DOPO SANITIZZAZIONE (Recurrence::sanitize)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Sanitizzazione completata
...
```

**Verifica anche:**
- Retrocompatibilità time_sets → time_slots
- Generazione slot per gennaio 2025
- Formato JSON risposta API
- Formattazione frontend slot

**Quando usare:**
- Per capire il flusso dati completo
- Debugging problemi calendario
- Testing modifiche logica ricorrenze
- Documentazione sistema

---

## 📋 Checklist Pre-Release

Prima di ogni release, esegui:

```bash
# 1. Syntax check
bash tools/run-php-syntax-check.sh

# 2. Calendar verification
bash tools/verification/verify-calendar-system.sh

# 3. Test data flow (opzionale, per documentazione)
php tools/verification/test-calendar-data-flow.php

# 4. Linting
composer run phpcs
npm run lint

# 5. Fix auto (se necessario)
composer run phpcbf
npm run lint:fix
```

---

## 🔧 Aggiungere Nuovi Script

### Template Bash

```bash
#!/bin/bash
# Script description

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}✓ Success message${NC}"
echo -e "${RED}✗ Error message${NC}"

exit 0
```

### Template PHP

```php
#!/usr/bin/env php
<?php
/**
 * Script description
 */

declare(strict_types=1);

echo "Script output\n";

exit(0);
```

### Naming Convention

- **Bash:** `kebab-case.sh`
- **PHP:** `kebab-case.php`
- **Eseguibili:** Aggiungi shebang e `chmod +x`

### Documentazione

Dopo aver creato un nuovo script:

1. Aggiungi sezione in questo README
2. Includi usage example
3. Specifica quando usarlo
4. Aggiorna checklist se pertinente

---

## 🆘 Troubleshooting

### "Permission denied"

```bash
chmod +x tools/your-script.sh
```

### "PHP not found"

Se PHP non è installato, usa alternative:
- Bash scripts quando possibile
- Docker: `docker run --rm -v $(pwd):/app php:8.2-cli php /app/tools/script.php`

### "Command not found"

Verifica di essere nella root del progetto:
```bash
cd /path/to/fp-experiences
bash tools/run-php-syntax-check.sh
```

---

## 📚 Risorse

- **[Developer Quick-Start](../docs/developer/QUICK-START-DEV.md)** - Setup ambiente dev
- **[Technical Docs](../docs/technical/)** - Documentazione tecnica
- **[Calendar System](../docs/technical/CALENDAR-SYSTEM.md)** - Sistema calendario

---

**Ultimo aggiornamento:** 13 Ottobre 2025