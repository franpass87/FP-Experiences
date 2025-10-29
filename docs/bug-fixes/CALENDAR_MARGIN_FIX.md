# Fix per i Box del Calendario - Prevenzione Overflow

## Problema
I box dei giorni del calendario frontend potevano fuoriuscire dai margini del contenitore, causando problemi di layout specialmente su schermi desktop.

## Cause Identificate
1. **Mancanza di `box-sizing: border-box`** - I padding e i bordi venivano aggiunti alla larghezza totale
2. **Grid senza protezione overflow** - L'uso di `1fr` senza `minmax(0, 1fr)` permetteva l'espansione oltre i limiti
3. **Gap eccessivo su desktop** - Il gap di 0.5rem era troppo grande combinato con padding e bordi
4. **Assenza di overflow control** - I contenitori non avevano `overflow: hidden`

## Modifiche Applicate

### 1. Regola Globale per il Calendario
```css
.fp-exp-calendar,
.fp-exp-calendar *,
.fp-exp-calendar-nav,
.fp-exp-calendar-nav * {
    box-sizing: border-box;
}
```
Questa regola assicura che tutti gli elementi del calendario usino il box model corretto.

### 2. Contenitori Principali
**`.fp-exp-calendar`**
- Aggiunto: `overflow: hidden`

**`.fp-exp-calendar__month`**
- Aggiunto: `overflow: hidden`
- Aggiunto: `box-sizing: border-box`

**`.fp-exp-calendar-nav`**
- Aggiunto: `overflow: hidden`
- Aggiunto: `box-sizing: border-box`

### 3. Grid del Calendario
**`.fp-exp-calendar__grid`**
- Modificato: `grid-template-columns: repeat(7, minmax(0, 1fr))` (era `repeat(7, 1fr)`)
- Aggiunto: `box-sizing: border-box`

**`.fp-exp-calendar__weekdays`**
- Modificato: `grid-template-columns: repeat(7, minmax(0, 1fr))` (era `repeat(7, 1fr)`)
- Aggiunto: `box-sizing: border-box`

### 4. Box dei Giorni
**`.fp-exp-calendar__day`**
- Aggiunto: `box-sizing: border-box`
- Aggiunto: `min-width: 0` (previene espansione forzata)
- Aggiunto: `overflow: hidden` (nasconde contenuto eccedente)

**`.fp-exp-calendar__weekday`**
- Aggiunto: `box-sizing: border-box`
- Aggiunto: `min-width: 0`

### 5. Responsive Desktop (min-width: 768px)
**Calendario Principale**
- Gap ridotto: da `0.5rem` a `0.4rem` per `.fp-exp-calendar__grid`
- Gap ridotto: da `0.5rem` a `0.4rem` per `.fp-exp-calendar__weekdays`
- Padding ridotto: da `0.75rem 0.5rem` a `0.65rem 0.4rem` per `.fp-exp-calendar__day`
- Padding ridotto: da `0.5rem` a `0.5rem 0.3rem` per `.fp-exp-calendar__weekday`

**Calendario con Navigazione**
- Gap ridotto: da `0.1rem` a `0.3rem` per `.fp-exp-calendar-nav__grid` (bilanciamento)
- Aggiunto: `box-sizing: border-box`, `min-width: 0`, `overflow: hidden` a `.fp-exp-calendar-nav__day`

## Risultato
I box dei giorni del calendario ora:
- Rimangono sempre all'interno dei margini del contenitore
- Mantengono proporzioni corrette su tutti i dispositivi
- Hanno un layout uniforme e bilanciato
- Non causano overflow orizzontale

## File Modificati
- `/workspace/assets/css/front.css`

## Build
Le modifiche sono state compilate e sincronizzate con:
```bash
npm run build
bash sync-build.sh
```

## Testing
Per testare le modifiche:
1. Visualizzare una pagina con il calendario frontend
2. Verificare su schermi di diverse dimensioni (mobile, tablet, desktop)
3. Controllare che tutti i box dei giorni siano contenuti nei margini
4. Verificare che il calendario con navigazione mesi funzioni correttamente

## Note Tecniche
- L'uso di `minmax(0, 1fr)` invece di `1fr` è fondamentale per prevenire il problema del "minimum content size" in CSS Grid
- Il `box-sizing: border-box` è applicato globalmente a tutti gli elementi del calendario per consistenza
- I gap sono stati bilanciati per mantenere un buon aspetto visivo senza causare overflow
