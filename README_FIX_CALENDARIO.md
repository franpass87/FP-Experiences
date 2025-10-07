# 🚀 Fix Calendario: README Veloce

## ❌ Problema
Calendario non mostra disponibilità, console dice: `CalendarMap is empty or not initialized`

## ✅ Soluzione
Ho modificato il sistema per usare **UN SOLO FORMATO** invece di due, eliminando completamente il problema di sincronizzazione.

## 📦 File Modificati
✅ `src/Booking/AvailabilityService.php` - Legge direttamente da `_fp_exp_recurrence`  
✅ `src/Shortcodes/CalendarShortcode.php` - Verifica `time_sets` invece di `availability`  
✅ `src/Admin/ExperienceMetaBoxes.php` - Sincronizzazione sempre attiva  

Tutti i file sono già copiati in `build/fp-experiences/`

## 🎯 Cosa Fare Ora

### 1. Distribuisci i File (2 min)
```bash
# Copia build/ sul server
scp -r build/fp-experiences/* user@server:/path/to/wp-content/plugins/fp-experiences/

# OPPURE via git
git add src/ build/
git commit -m "fix: formato unico per calendario, elimina sincronizzazione"
git push
```

### 2. Ri-Salva l'Esperienza (1 min)
1. Admin WordPress → Esperienze → Modifica "Test"
2. Tab **"Calendario & Slot"**
3. Verifica che in **"Set di orari e capienza"** ci sia:
   - ✅ Almeno 1 orario (es. 09:00)
   - ✅ Giorni selezionati (es. Lunedì, Mercoledì, Venerdì)
   - ✅ Capienza > 0 (es. 10)
4. Clicca **"Aggiorna"**

### 3. Svuota Cache (30 sec)
```bash
wp cache flush
# Browser: Ctrl+Shift+R
```

### 4. Verifica (30 sec)
Vai a: https://fpdevelopmentenviron6716.live-website.com/experience/test/

✅ **Dovresti vedere giorni evidenziati (non grigi)**  
✅ **Cliccando su un giorno appaiono le fasce orarie**  
✅ **Console NON mostra "CalendarMap is empty"**

## 🔍 Se Non Funziona

### Debug Rapido
```bash
# Copia script debug
scp debug-calendar-data.php user@server:/path/to/wordpress/

# Esegui
wp eval-file debug-calendar-data.php [ID_ESPERIENZA]
```

**Cerca nell'output**:
- ❌ "NESSUN TIME SET" → Configura time set nell'admin
- ❌ "Campo 'times' vuoto" → Aggiungi orari nel time set
- ❌ "Capienza 0" → Imposta capienza > 0
- ✅ "X slot generati" → Tutto OK

### Test Console Browser
```javascript
const cal = document.querySelector('[data-fp-shortcode="calendar"]');
console.log('Slots:', JSON.parse(cal.getAttribute('data-slots')));
// Deve mostrare oggetto con date e slot, NON {}
```

## 📚 Documenti Dettagliati

- **`SOLUZIONE_FINALE_FORMATO_UNICO.md`** - Guida completa con tutti i dettagli
- **`FIX_CALENDARIO_NO_DISPONIBILITA.md`** - Guida passo-passo con troubleshooting
- **`GUIDA_RAPIDA_FIX_CALENDARIO.md`** - Istruzioni rapide

## 🎓 Cosa È Cambiato

### Prima (Problema)
```
Admin → _fp_exp_recurrence
          ↓ sync (può fallire ❌)
        _fp_exp_availability
          ↓
       Frontend
```

### Dopo (Soluzione)
```
Admin → _fp_exp_recurrence
          ↓ (legge direttamente ✅)
       Frontend
```

**Zero sincronizzazione = Zero problemi**

## ✅ Checklist Veloce

- [ ] File distribuiti sul server
- [ ] Esperienza ri-salvata
- [ ] Cache svuotata
- [ ] Frontend mostra giorni disponibili
- [ ] Console NON mostra errori

## 📞 Supporto

Se dopo aver seguito i passi il problema persiste:

1. Esegui `debug-calendar-data.php` e salva l'output
2. Controlla console browser per errori
3. Verifica che time_sets abbiano:
   - Almeno 1 orario
   - Giorni selezionati
   - Capienza > 0

---

**Tempo totale**: ~5 minuti  
**Difficoltà**: 🟢 Facile  
**Breaking Changes**: Nessuno (retrocompatibile)
