# Analisi Spazio Interno Box Calendario

## Mobile (default)
### Container
- `min-height`: 2.5rem = **40px**
- `padding`: 0.5rem 0.25rem = **8px verticale**, **4px orizzontale**
- Border: 1px per lato = **2px totali verticali**

### Contenuto
- `day-label`: 0.85rem × 1.2 (line-height) = 13.6px × 1.2 = **16.32px**
- `margin-top`: 0.15rem = **2.4px**
- `day-count`: 0.65rem × 1 (line-height) = **10.4px**

### Calcolo Spazio Mobile
- Altezza disponibile: 40px - (8px × 2) - 2px = **30px**
- Contenuto richiesto: 16.32px + 2.4px + 10.4px = **29.12px**
- ✅ **Margine: 0.88px** (OK ma molto stretto)

**PROBLEMA ORIZZONTALE MOBILE:**
- Larghezza padding: 4px per lato
- Numeri a due cifre (28, 29, 30, 31) potrebbero essere compressi
- Font 0.85rem ≈ 13.6px, due cifre richiedono ~16-18px

---

## Desktop (min-width: 768px)
### Container
- `min-height`: 3.5rem = **56px**
- `padding`: 0.65rem 0.4rem = **10.4px verticale**, **6.4px orizzontale**
- Border: 1px per lato = **2px totali verticali**

### Contenuto
- `day-label`: 0.95rem × 1.2 (line-height) = 15.2px × 1.2 = **18.24px**
- `margin-top`: 0.25rem = **4px**
- `day-count`: 0.7rem × 1 (line-height) = **11.2px**

### Calcolo Spazio Desktop
- Altezza disponibile: 56px - (10.4px × 2) - 2px = **33.2px**
- Contenuto richiesto: 18.24px + 4px + 11.2px = **33.44px**
- ⚠️ **Margine: -0.24px** (TROPPO STRETTO!)

---

## CONCLUSIONE
❌ **Desktop:** Contenuto eccede di 0.24px - il testo potrebbe essere troncato
⚠️ **Mobile:** Padding orizzontale troppo stretto (4px) per numeri a due cifre

## RACCOMANDAZIONI
1. Aumentare `min-height` desktop a 3.75rem (60px)
2. Aumentare padding orizzontale mobile a 0.35rem (5.6px)
3. Oppure ridurre leggermente i font-size
