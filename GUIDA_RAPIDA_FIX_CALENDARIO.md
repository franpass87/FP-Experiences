# 🚀 Guida Rapida: Fix Calendario Senza Disponibilità

## ⚡ Soluzione Veloce (5 minuti)

### Opzione A: Via Admin WordPress (Più Semplice)

1. **Distribuisci i file aggiornati**:
   ```bash
   # I file sono già modificati in /workspace, caricali sul server
   # Il file principale modificato è:
   build/fp-experiences/src/Admin/ExperienceMetaBoxes.php
   ```

2. **Ri-salva l'esperienza**:
   - Vai nell'admin WordPress
   - Apri l'esperienza che ha il problema
   - Tab "Calendario & Slot"
   - **Importante**: Verifica che ci siano:
     - ✅ Almeno un time set con orari (es. 09:00, 14:00)
     - ✅ Giorni della settimana selezionati
     - ✅ Capienza > 0
     - ✅ Data inizio = oggi o futuro
   - Clicca **"Aggiorna"**

3. **Svuota cache**:
   ```bash
   # Cache WordPress
   wp cache flush
   
   # Cache browser
   Ctrl+Shift+R (su Windows/Linux)
   Cmd+Shift+R (su Mac)
   ```

4. **Verifica**: Apri il calendario nel frontend → Dovresti vedere giorni evidenziati

---

### Opzione B: Via Script Automatico (Più Veloce per Multiple Esperienze)

1. **Carica gli script sul server**:
   ```bash
   # Copia sul server
   scp debug-calendar-data.php user@server:/path/to/wordpress/
   scp force-sync-availability.php user@server:/path/to/wordpress/
   ```

2. **Diagnostica il problema**:
   ```bash
   wp eval-file debug-calendar-data.php [ID_ESPERIENZA]
   ```

3. **Forza la sincronizzazione**:
   ```bash
   # Per una singola esperienza
   wp eval-file force-sync-availability.php [ID_ESPERIENZA]
   
   # Per TUTTE le esperienze
   wp eval-file force-sync-availability.php
   ```

4. **Verifica**: Apri il calendario nel frontend

---

## 📋 Checklist Veloce

Verifica che l'esperienza abbia:
- [ ] Time set configurato con almeno un orario
- [ ] Giorni della settimana selezionati
- [ ] Capienza > 0
- [ ] Data inizio configurata (oggi o futuro)

Se manca qualcosa → Vai in admin e configura → Salva → Ricarica frontend

---

## 🔍 Come Capire Se È Risolto

### Test Rapido Frontend

1. Apri: https://tuosito.com/experience/test/
2. Vedi giorni evidenziati? ✅ Risolto
3. Non vedi giorni evidenziati? ❌ Continua sotto

### Test Rapido Console

1. Apri Developer Tools (F12)
2. Console:
   ```javascript
   // Copia e incolla
   const cal = document.querySelector('[data-fp-shortcode="calendar"]');
   console.log('Slots:', cal ? JSON.parse(cal.getAttribute('data-slots')) : 'Calendar not found');
   ```
3. Vedi slot? ✅ Risolto
4. Vedi `{}` o errore? ❌ Continua sotto

---

## 🐛 Se Non Funziona Ancora

### 1. Verifica Dati Backend

```bash
wp eval-file debug-calendar-data.php [ID_ESPERIENZA]
```

**Cerca nell'output**:
- ❌ "NESSUN TIME SET" → Configura time set nell'admin
- ❌ "Campo 'times' vuoto" → Esegui force-sync
- ❌ "Capienza 0" → Imposta capienza > 0
- ❌ "NESSUNO SLOT GENERATO" → Verifica date

### 2. Forza Ri-sync

```bash
wp eval-file force-sync-availability.php [ID_ESPERIENZA]
```

### 3. Verifica Log

```bash
# Abilita debug
wp config set WP_DEBUG true

# Ri-salva esperienza in admin

# Controlla log
tail -f wp-content/debug.log

# Cerca: "FP_EXP: Syncing recurrence to availability"
```

### 4. Svuota TUTTE le Cache

```bash
wp cache flush
wp transient delete-all

# Se hai plugin di cache
wp super-cache flush
wp w3-total-cache flush all
```

---

## 📞 Quick Support

**Problema**: Nessun time set configurato
**Fix**: Admin → Calendario & Slot → Set di orari → Aggiungi orari → Salva

**Problema**: Times vuoto in availability
**Fix**: `wp eval-file force-sync-availability.php [ID]`

**Problema**: Capienza 0
**Fix**: Admin → Calendario & Slot → Imposta capienza > 0 → Salva

**Problema**: Slot non generati
**Fix**: Verifica che data inizio sia oggi o futuro

**Problema**: Tutto ok ma calendario vuoto
**Fix**: Svuota cache browser (Ctrl+Shift+R)

---

## 📁 File Importanti

```
/workspace/
├── src/Admin/ExperienceMetaBoxes.php          ✅ Modificato
├── build/fp-experiences/src/Admin/            
│   └── ExperienceMetaBoxes.php                ✅ Da distribuire
├── debug-calendar-data.php                    🔧 Script diagnostica
├── force-sync-availability.php                🔧 Script sync
├── FIX_CALENDARIO_NO_DISPONIBILITA.md        📖 Guida completa
└── GUIDA_RAPIDA_FIX_CALENDARIO.md            📖 Questa guida
```

---

## ⚙️ Cosa Fanno i Fix Applicati

1. **Sincronizzazione sempre attiva**: Ogni volta che salvi un'esperienza, i dati vengono sincronizzati tra ricorrenza e availability

2. **Gestione dati vuoti**: Se non ci sono dati validi, il meta viene cancellato invece di rimanere in uno stato inconsistente

3. **Log di debug**: Con WP_DEBUG attivo, puoi vedere nel log cosa viene estratto dai time_sets

---

## 🎯 Prossimi Passi

1. ✅ Distribuisci `ExperienceMetaBoxes.php` aggiornato
2. ✅ Esegui `force-sync-availability.php` per tutte le esperienze
3. ✅ Verifica frontend
4. ✅ Svuota cache
5. ✅ Test completo

---

**Tempo stimato**: 5-10 minuti per singola esperienza, 15-30 minuti per tutte le esperienze

**Difficoltà**: 🟢 Facile (con accesso admin) | 🟡 Media (con WP-CLI)

**Supporto**: Leggi `FIX_CALENDARIO_NO_DISPONIBILITA.md` per guida dettagliata
