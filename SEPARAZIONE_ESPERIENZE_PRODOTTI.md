# ✅ ESPERIENZE SEPARATE DAI PRODOTTI WOOCOMMERCE

**v0.5.0 - Architettura Separata**

---

## 🎯 **SÌ, SONO COMPLETAMENTE SEPARATE!**

Le **esperienze** rimangono **SEPARATE** dai prodotti WooCommerce.

---

## 📊 **ARCHITETTURA**

### **Esperienze (CPT `fp_experience`):**

```
Dashboard → FP Experiences → Esperienze
    ↓
CPT: fp_experience
ID: 9104, 9137, 9138, ecc.
Gestite in: FP Experiences plugin
```

**NON appaiono in:**
- ❌ Dashboard → Prodotti
- ❌ Catalogo WooCommerce  
- ❌ Shop page
- ❌ Categorie prodotti

### **Prodotti WooCommerce:**

```
Dashboard → Prodotti → Tutti i prodotti
    ↓
CPT: product
ID: (separati dalle esperienze)
Gestiti in: WooCommerce
```

---

## 🔧 **COME FUNZIONA L'INTEGRAZIONE**

### **1. Prodotto Virtuale Unico**

```
WooCommerce crea UNA VOLTA:
Product ID: XXX (es. 15000)
Nome: "Experience Booking"
Tipo: Virtual, Hidden
Prezzo: 0 (variabile)
```

**Questo prodotto:**
- ✅ È **nascosto** dal catalogo (non appare in shop)
- ✅ È **virtuale** (no shipping)
- ✅ Serve SOLO come "contenitore" nel carrello WooCommerce
- ✅ **NON è un'esperienza** - è solo il veicolo per il checkout

### **2. Quando Utente Prenota Esperienza**

```
Utente seleziona: "Degustazione Premium" (ID: 9138)
    ↓
Carrello CUSTOM: experience_id=9138, slot_start, slot_end, tickets
    ↓
Redirect a /checkout/
    ↓
Sync a WooCommerce:
WC()->cart->add_to_cart(
    15000,  ← Product virtuale (SEMPRE lo stesso)
    2,      ← Quantity
    [],
    [
        'fp_exp_experience_id' => 9138,  ← VERA esperienza
        'fp_exp_slot_start' => '2025-11-07 11:00:00',
        'fp_exp_slot_end' => '2025-11-07 12:00:00',
        'fp_exp_tickets' => ['adulto' => 2],
    ]
)
```

**Nel carrello WooCommerce vede:**
```
Nome: "Degustazione Premium"  ← Letto da experience_id=9138
Prezzo: 80,00 €              ← Letto da _fp_price meta
Data: 2025-11-07 11:00:00    ← Letto da fp_exp_slot_start
```

### **3. Nell'Ordine WooCommerce**

```
Order Item:
- product_id: 15000 (prodotto virtuale)
- Meta:
  - fp_exp_experience_id: 9138  ← VERA esperienza
  - fp_exp_slot_id: 123
  - fp_exp_slot_start: 2025-11-07 11:00:00
  - fp_exp_slot_end: 2025-11-07 12:00:00
  - fp_exp_tickets: {adulto: 2}
```

**Quando processi l'ordine:**
- Leggi `fp_exp_experience_id` per sapere QUALE esperienza
- Usa i meta per creare reservations, send email, ecc.

---

## ✅ **GARANZIE DI SEPARAZIONE**

### **Dashboard:**

- ✅ Esperienze in: `Dashboard → FP Experiences → Esperienze`
- ✅ Prodotti in: `Dashboard → Prodotti → Tutti i prodotti`
- ✅ **NON si mescolano!**

### **Frontend:**

- ✅ Esperienze: Shortcode `[fp_exp_list]` + pagine custom
- ✅ Prodotti: Shop WooCommerce standard
- ✅ **Cataloghi separati!**

### **Carrello:**

- ✅ Filtro `prevent_mixed_carts()` ANCORA attivo
- ✅ Se c'è esperienza → NO prodotti
- ✅ Se ci sono prodotti → NO esperienze
- ✅ **Mai mescolati!**

### **Database:**

- ✅ Experiences: `wp_posts` con `post_type='fp_experience'`
- ✅ Prodotti: `wp_posts` con `post_type='product'`
- ✅ ID diversi, meta diversi
- ✅ **Completamente separati!**

---

## 💡 **ANALOGIA**

Pensa alle esperienze come **"biglietti per eventi"**:

```
Eventbrite:
- Ha EVENTI (separati dai prodotti)
- Ma usa Stripe per il checkout (standard)
- Gli eventi NON sono prodotti Stripe
- Ma il checkout USA la piattaforma Stripe

FP Experiences v0.5.0:
- Ha ESPERIENZE (separate dai prodotti)
- Ma usa WooCommerce per il checkout (standard)
- Le esperienze NON sono prodotti WooCommerce
- Ma il checkout USA la piattaforma WooCommerce
```

---

## 🔍 **VERIFICA SEPARAZIONE**

### **Test 1: Dashboard**

1. Vai in `Dashboard → Prodotti`
2. ✅ **NON vedi** le esperienze
3. ✅ Vedi solo i prodotti WooCommerce

4. Vai in `Dashboard → FP Experiences → Esperienze`
5. ✅ Vedi SOLO le esperienze
6. ✅ **NON vedi** i prodotti WooCommerce

### **Test 2: Frontend**

1. Vai su `/shop/` (catalogo WooCommerce)
2. ✅ **NON vedi** le esperienze
3. ✅ Vedi solo i prodotti

4. Vai su `/esperienze/` (shortcode experiences)
5. ✅ Vedi SOLO le esperienze
6. ✅ **NON vedi** i prodotti

### **Test 3: Carrello**

1. Aggiungi un'esperienza al carrello
2. Vai su `/cart/`
3. ✅ Vedi l'esperienza

4. Prova ad aggiungere un prodotto WooCommerce
5. ✅ **DOVREBBE** bloccare (mixed cart prevention)
6. Messaggio: "Non puoi mescolare esperienze e prodotti"

---

## 🎯 **CONCLUSIONE**

### **Esperienze:**

✅ Gestite in FP Experiences plugin  
✅ CPT separato (`fp_experience`)  
✅ **NON appaiono** in catalogo WooCommerce  
✅ **NON mescolabili** con prodotti  
✅ Hanno il LORO sistema di slot, availability, ecc.  

### **WooCommerce:**

✅ **Usato SOLO per il checkout** (form + payment)  
✅ 1 prodotto virtuale nascosto come "veicolo"  
✅ Experience data salvati nelle meta  
✅ **NON interferisce** con prodotti WooCommerce reali  

---

## ✅ **RISPOSTA**

**Sì, le esperienze rimangono COMPLETAMENTE SEPARATE dai prodotti WooCommerce!**

WooCommerce è usato solo per:
- Form checkout (nome, email, ecc.)
- Gateway pagamento (Stripe)
- Email transazionali

Le esperienze sono ANCORA gestite nel tuo CPT custom separato! 🎯

---

**Vedi:** `RIEPILOGO_FINALE_v0.5.0.md` per dettagli completi.

