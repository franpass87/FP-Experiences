# Verifica Spazio Corretto - Calendario Box

## Mobile (Dopo Fix)
### Container
- `min-height`: 2.75rem = **44px** (era 40px, +4px)
- `padding`: 0.5rem 0.35rem = **8px verticale**, **5.6px orizzontale** (era 4px, +1.6px)
- Border: 1px per lato = **2px totali verticali**

### Contenuto
- `day-label`: 0.85rem × 1.2 (line-height) = **16.32px**
- `margin-top`: 0.15rem = **2.4px**
- `day-count`: 0.65rem × 1 (line-height) = **10.4px**
- **Totale**: 16.32px + 2.4px + 10.4px = **29.12px**

### Calcolo Spazio Mobile
- Altezza disponibile: 44px - (8px × 2) - 2px = **26px**
- Contenuto richiesto: **29.12px**
- ❌ **Eccede di 3.12px** - ANCORA TROPPO STRETTO!

**SOLUZIONE MOBILE:** Ridurre margin-top da 0.15rem a 0.05rem o aumentare min-height

---

## Desktop (Dopo Fix)
### Container
- `min-height`: 3.75rem = **60px** (era 56px, +4px)
- `padding`: 0.6rem 0.4rem = **9.6px verticale**, **6.4px orizzontale**
- Border: 1px per lato = **2px totali verticali**

### Contenuto
- `day-label`: 0.95rem × 1.2 (line-height) = **18.24px**
- `margin-top`: 0.2rem = **3.2px** (era 4px, -0.8px)
- `day-count`: 0.7rem × 1 (line-height) = **11.2px**
- **Totale**: 18.24px + 3.2px + 11.2px = **32.64px**

### Calcolo Spazio Desktop
- Altezza disponibile: 60px - (9.6px × 2) - 2px = **38.8px**
- Contenuto richiesto: **32.64px**
- ✅ **Margine: 6.16px** - PERFETTO!

---

## NECESSARIA ULTERIORE CORREZIONE MOBILE
Opzioni:
1. ✅ Ridurre margin-top da 0.15rem a 0.05rem (-1.6px)
2. Aumentare min-height a 3rem (48px)
3. Ridurre font-size leggermente

**RACCOMANDAZIONE:** Opzione 1 (più conservativa)
