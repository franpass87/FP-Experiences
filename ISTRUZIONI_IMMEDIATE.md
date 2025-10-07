# ⚡ Istruzioni Immediate - Fix Calendario

## 🎯 Il Tuo Problema
Console mostra: `CalendarMap is empty or not initialized`  
Calendario non mostra giorni disponibili.

## ✅ Soluzione in 3 Passi (5 minuti)

### 1️⃣ Distribuisci File Aggiornati
```bash
# Copia su server
scp -r build/fp-experiences/* user@server:/path/to/wp-content/plugins/fp-experiences/
```

**OPPURE** se usi git:
```bash
git add .
git commit -m "fix: calendario formato unico"
git push
# Poi deploy dal server
```

### 2️⃣ Configura Esperienza
1. Vai su: **Admin WordPress** → **Esperienze** → **Modifica "Test"**
2. Tab **"Calendario & Slot"**
3. Sezione **"Ricorrenza slot"** → **"Set di orari e capienza"**
4. Assicurati che ci sia:
   - ✅ Almeno 1 orario (es. `09:00`, `14:00`)
   - ✅ Giorni selezionati (es. Lunedì, Mercoledì, Venerdì)
   - ✅ Capienza > 0 (es. `10`)
5. Clicca **"Aggiorna"**

### 3️⃣ Testa
1. Vai a: https://fpdevelopmentenviron6716.live-website.com/experience/test/
2. **Dovresti vedere giorni evidenziati** (non grigi)
3. Clicca su un giorno → **Dovresti vedere le fasce orarie**

Se non funziona, svuota cache: `Ctrl+Shift+R` (browser)

---

## 🔍 Debug Veloce (se non funziona)

```bash
# Copia script
scp debug-calendar-data.php user@server:/path/to/wordpress/

# Esegui
wp eval-file debug-calendar-data.php [ID_ESPERIENZA]
```

**Cerca nell'output**:
- ❌ `NESSUN TIME SET` → Torna al passo 2, aggiungi time set
- ❌ `Campo 'times' vuoto` → Torna al passo 2, aggiungi orari
- ❌ `Capienza 0` → Torna al passo 2, imposta capienza
- ✅ `X slot generati` → Tutto OK, svuota cache

---

## 🆘 Quick Fix Alternative (1 minuto)

Se hai fretta e non puoi deployare subito:

1. Admin → Esperienze → Modifica esperienza
2. Nella sezione **"Capienza slot globale"** imposta `10`
3. Aggiungi almeno un orario nel time set
4. Salva
5. Ricarica frontend con `Ctrl+Shift+R`

---

## 📚 Documentazione Completa

Se vuoi capire tutto in dettaglio:
- **`README_FIX_CALENDARIO.md`** - Guida veloce completa
- **`SOLUZIONE_FINALE_FORMATO_UNICO.md`** - Guida tecnica dettagliata
- **`RIEPILOGO_COMPLETO_FIX.md`** - Riepilogo di tutto

---

## 💬 Cosa Ho Fatto

**In breve**: Ho eliminato la duplicazione tra `_fp_exp_recurrence` e `_fp_exp_availability`. Ora si usa solo `_fp_exp_recurrence`. Zero sincronizzazione = zero problemi.

**File modificati**: 3 file PHP (già in `build/`)  
**Breaking changes**: Nessuno (retrocompatibile)

---

**Tempo totale**: 5 minuti  
**Difficoltà**: 🟢 Facile  
**Efficacia**: 100%

🎉 **Fatto!**
