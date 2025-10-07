# 🚀 Quick Start - Guida Rapida Admin

Questa guida ti aiuterà a configurare e utilizzare FP Experiences in meno di 15 minuti.

---

## 📋 Checklist Setup

- [ ] Plugin installato e attivato
- [ ] WooCommerce installato e configurato
- [ ] Timezone WordPress impostato
- [ ] Permalink su "Nome articolo"
- [ ] Prima esperienza creata
- [ ] Test prenotazione completato

---

## ⚙️ Step 1: Configurazione Base (5 min)

### 1.1 Impostazioni Generali

Vai su **FP Experiences → Impostazioni → Generali**

```
✓ Timezone: [Seleziona il tuo timezone]
✓ Ruoli: Abilita operatori e manager se necessario
✓ Meeting Points: Abilita se usi locations fisiche
```

### 1.2 Impostazioni Branding

Vai su **FP Experiences → Impostazioni → Branding**

```
✓ Logo: Carica il logo della tua azienda
✓ Colori primari: Imposta i colori del brand
✓ Colori sezioni: Personalizza colori icone
```

### 1.3 Verifica Permalink

Vai su **Impostazioni → Permalink**

```
✓ Assicurati che sia selezionato "Nome articolo"
✓ Se cambi, clicca "Salva modifiche"
```

---

## 🎯 Step 2: Crea Prima Esperienza (5 min)

### 2.1 Informazioni Base

1. Vai su **FP Experiences → Aggiungi nuova esperienza**
2. **Tab Dettagli:**

```
Titolo: [es. "Tour guidato del centro storico"]
Descrizione breve: [Max 160 caratteri per SEO]
Descrizione completa: [Testo ricco con dettagli]

Durata: 2 ore
Partecipanti: Min 2, Max 15
```

### 2.2 Gallery Immagini

**Nel pannello "Galleria immagini":**

```
1. Clicca "Seleziona immagini"
2. Scegli 3-5 immagini belle della tua esperienza
3. Trascina per riordinare (la prima è l'hero image)
4. Salva
```

**Tip:** Usa immagini:
- Formato: 16:9 (es. 1920x1080px)
- Qualità: Alta ma ottimizzate (<500KB)
- Contenuto: Coinvolgenti, autentiche, rappresentative

### 2.3 Lingue e Badge

```
Lingue disponibili: 
☑ Italiano
☑ Inglese
☐ Francese

Badge esperienza:
☑ Family Friendly
☑ Best Seller
☐ Outdoor Activity
```

---

## 💰 Step 3: Configura Prezzi (3 min)

### 3.1 Tab "Biglietti & Prezzi"

**Aggiungi tipi biglietto:**

| Tipo | Prezzo | Min | Max |
|------|--------|-----|-----|
| Adulto | €29.00 | 1 | 99 |
| Bambino (6-12 anni) | €15.00 | 0 | 99 |
| Gratis (0-5 anni) | €0.00 | 0 | 10 |

**Clicca "+ Aggiungi tipo biglietto" per ogni riga**

### 3.2 Add-ons Opzionali (Facoltativo)

```
Add-on 1:
Nome: "Audio guida"
Prezzo: €5.00
[Carica immagine cuffie/audio]

Add-on 2:
Nome: "Pranzo incluso"  
Prezzo: €12.00
[Carica immagine cibo]
```

---

## 📅 Step 4: Calendario e Slot (5 min)

### 4.1 Impostazioni Generali

**Tab "Calendario & Slot":**

```
Capacità generale: 15 persone
Preavviso minimo: 24 ore
Buffer prima: 30 minuti
Buffer dopo: 15 minuti
```

### 4.2 Giorni Disponibili

**Seleziona i giorni della settimana:**

```
☑ Lunedì
☑ Mercoledì
☑ Venerdì
☑ Sabato
☑ Domenica
```

### 4.3 Slot Orari

**Aggiungi gli orari:**

| Orario | Capacità Override | Buffer Prima | Buffer Dopo |
|--------|-------------------|--------------|-------------|
| 10:00 | - | - | - |
| 14:00 | - | - | - |
| 16:00 | 10 (max ridotto) | 45 min | 30 min |

**Note:**
- Lascia vuoto = usa impostazioni generali
- Compila = override per quello slot specifico

---

## 📍 Step 5: Meeting Point (2 min)

### 5.1 Crea Location

**Se non hai locations:**

1. Vai su **FP Experiences → Meeting Points**
2. Clicca "Aggiungi nuovo"

```
Titolo: "Piazza del Duomo - Fontana centrale"
Indirizzo: "Piazza del Duomo, 1, 20100 Milano MI"
Latitudine: 45.464161
Longitudine: 9.189944
Note: "Ritrovo davanti alla fontana centrale"
```

3. Salva

### 5.2 Assegna all'Esperienza

**Torna sull'esperienza:**

**Tab "Meeting Point":**

```
Location primaria: [Seleziona "Piazza del Duomo"]
Location alternative: [Opzionale - per flessibilità]
```

---

## ✅ Step 6: Pubblica e Testa

### 6.1 Pubblica

```
1. Controlla che tutti i tab siano compilati
2. Clicca "Pubblica" in alto a destra
3. ✅ Gli slot vengono generati automaticamente!
```

### 6.2 Visualizza Frontend

**Clicca "Visualizza esperienza"**

Dovresti vedere:
- ✅ Hero con gallery
- ✅ Badge lingue e showcase
- ✅ Calendario con slot disponibili
- ✅ Prezzi biglietti
- ✅ Meeting point con mappa
- ✅ Pulsante "Prenota ora"

### 6.3 Test Prenotazione

1. **Seleziona un giorno verde** nel calendario
2. **Scegli uno slot** dalla lista
3. **Seleziona quantità biglietti**
4. **Aggiungi eventuali add-ons**
5. **Clicca "Prenota ora"**
6. **Compila form checkout** WooCommerce
7. **Completa ordine test**

---

## 🎉 Congratulazioni!

Hai configurato la tua prima esperienza! 

### Prossimi Passi

**Per migliorare:**
- 📧 Configura email transazionali (Impostazioni → Email)
- 🗺️ Aggiungi più meeting points
- 🎁 Abilita gift vouchers
- 📊 Connetti Google Analytics
- 📧 Integra Brevo per newsletter

**Per vendere:**
- 📱 Condividi link esperienza sui social
- 🔗 Inserisci shortcode in pagine
- 🎨 Usa widget Elementor per design custom
- 📈 Monitora prenotazioni da Dashboard

---

## 💡 Tips Pro

### Ottimizza Calendario

**Per massimizzare prenotazioni:**

```
✓ Offri almeno 3 slot/giorno
✓ Copri weekend e festivi
✓ Mantieni capacità realistica
✓ Buffer adeguati tra slot
```

### Prezzi Efficaci

```
✓ Prezzi chiari e trasparenti
✓ Sconti bambini (50-70% adulto)
✓ Add-ons a valore percepito
✓ Prezzi competitivi ma sostenibili
```

### Descrizioni Coinvolgenti

```
✓ Usa storytelling emotivo
✓ Evidenzia cosa rende unica l'esperienza
✓ Includi cosa è incluso/escluso
✓ Aggiungi review/testimonial
✓ Usa immagini professionali
```

### SEO Friendly

```
✓ Titolo con parole chiave (max 60 caratteri)
✓ Descrizione breve unica per ogni esperienza
✓ Alt text su tutte le immagini
✓ URL parlante (slug pulito)
```

---

## 🆘 Problemi Comuni

### "Il calendario è vuoto"

**Soluzione:**
```
1. Controlla che l'esperienza sia "Pubblicata"
2. Verifica giorni settimana selezionati
3. Verifica almeno un time_slot configurato
4. Controlla capacità generale > 0
```

### "Non vedo gli slot generati"

**Soluzione:**
```
1. Vai su FP Experiences → Dashboard → Calendario
2. Filtra per la tua esperienza
3. Verifica che ci siano slot nel DB
4. Se mancano: modifica esperienza e salva di nuovo
```

### "Errore al checkout"

**Soluzione:**
```
1. Verifica WooCommerce configurato
2. Controlla pagine checkout esistenti
3. Test payment gateway
4. Controlla logs: FP Experiences → Logs
```

### "Meeting point non mostra mappa"

**Soluzione:**
```
1. Verifica latitudine/longitudine corrette
2. Controlla JavaScript console per errori
3. Verifica che il tema supporti script footer
```

---

## 📚 Approfondimenti

Per funzionalità avanzate consulta:

- **[Guida Admin Completa](ADMIN-GUIDE.md)** - Tutte le funzionalità dettagliate
- **[Guida Importer](IMPORTER-COMPLETO.md)** - Import CSV multiplo
- **[Menu Admin](ADMIN-MENU.md)** - Struttura completa menu

---

## 📞 Supporto

Serve aiuto? 

- 📖 **Documentazione:** [docs/README.md](../README.md)
- 🐛 **Issue Tracker:** [GitHub Issues](https://github.com/your-repo/issues)
- 📧 **Email:** support@formazionepro.it

---

**Buon lavoro!** 🚀  
*Ultimo aggiornamento: 7 Ottobre 2025*