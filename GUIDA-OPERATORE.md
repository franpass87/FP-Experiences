# 📖 Guida Operatore - FP Experiences

**Versione:** 1.2.15  
**Data:** Gennaio 2026

Questa guida spiega come utilizzare il plugin **FP Experiences** per gestire le prenotazioni delle esperienze turistiche.

---

## 🎯 Cos'è FP Experiences?

**FP Experiences** è un sistema di prenotazione online per esperienze turistiche (tour, attività, workshop, ecc.). Permette ai clienti di:

- 📅 Scegliere data e orario
- 🎫 Selezionare tipi di biglietti (adulto, bambino, ecc.)
- 💳 Completare il pagamento online
- ✉️ Ricevere conferme via email

Tu, come operatore, puoi gestire tutto dal pannello WordPress.

---

## 📋 Indice

1. [Accesso e Dashboard](#1-accesso-e-dashboard)
2. [Creare una Nuova Esperienza](#2-creare-una-nuova-esperienza)
3. [Gestire le Prenotazioni](#3-gestire-le-prenotazioni)
4. [Visualizzare gli Ordini](#4-visualizzare-gli-ordini)
5. [Gestire il Calendario](#5-gestire-il-calendario)
6. [Configurare Prezzi e Biglietti](#6-configurare-prezzi-e-biglietti)
7. [Impostazioni Generali](#7-impostazioni-generali)
8. [Problemi Comuni](#8-problemi-comuni)

---

## 1. Accesso e Dashboard

### Come Accedere

1. Accedi al pannello WordPress del sito
2. Nel menu laterale, cerca **"FP Experiences"**
3. Clicca su **"Dashboard"**

### Cosa Vedi nella Dashboard

La Dashboard mostra:

- **📊 Prenotazioni oggi**: Numero di prenotazioni ricevute oggi
- **📈 Riempimento settimana**: Percentuale di slot prenotati questa settimana
- **⏳ Richieste in attesa**: Eventuali richieste di prenotazione da approvare
- **📋 Ultimi ordini**: Lista degli ultimi ordini completati

### Azioni Rapide

Dalla Dashboard puoi:

- ➕ **Crea nuova esperienza**: Aggiungi una nuova esperienza al catalogo
- 📝 **Gestisci vetrina**: Vedi tutte le esperienze pubblicate
- ⚙️ **Apri impostazioni**: Configura il plugin

---

## 2. Creare una Nuova Esperienza

### Passo 1: Apri il Form

1. Vai su **FP Experiences → Dashboard**
2. Clicca **"Crea nuova esperienza"** (o **FP Experiences → Aggiungi nuova esperienza**)

### Passo 2: Compila le Informazioni Base

**Tab "Dettagli":**

- **Titolo**: Nome dell'esperienza (es. "Tour guidato del centro storico")
- **Descrizione breve**: Max 160 caratteri (appare nei risultati di ricerca)
- **Descrizione completa**: Testo dettagliato con tutte le informazioni
- **Durata**: Quanto dura l'esperienza (es. "2 ore")
- **Partecipanti**: Minimo e massimo (es. Min: 2, Max: 15)

**Galleria immagini:**

1. Clicca **"Seleziona immagini"**
2. Scegli 3-5 immagini belle
3. Trascina per riordinare (la prima è l'immagine principale)
4. Salva

**Lingue e Badge:**

- Seleziona le lingue disponibili (Italiano, Inglese, ecc.)
- Aggiungi badge (Family Friendly, Best Seller, ecc.)

### Passo 3: Configura i Prezzi

**Tab "Biglietti & Prezzi":**

1. Clicca **"+ Aggiungi tipo biglietto"**
2. Compila:
   - **Nome**: Es. "Adulto", "Bambino (6-12 anni)"
   - **Prezzo**: Es. €29.00
   - **Quantità min/max**: Es. Min 1, Max 99
3. Ripeti per ogni tipo di biglietto

**Add-ons opzionali** (facoltativo):

Puoi aggiungere servizi extra:
- Es. "Audio guida" - €5.00
- Es. "Pranzo incluso" - €12.00

### Passo 4: Imposta il Calendario

**Tab "Calendario & Slot":**

**Impostazioni generali:**

- **Capacità generale**: Quante persone per slot (es. 15)
- **Preavviso minimo**: Quante ore prima si può prenotare (es. 24 ore)
- **Buffer prima/dopo**: Tempo tra uno slot e l'altro (es. 30 min prima, 15 min dopo)

**Giorni disponibili:**

Seleziona i giorni della settimana in cui l'esperienza è disponibile:
- ☑ Lunedì
- ☑ Mercoledì
- ☑ Venerdì
- ☑ Sabato
- ☑ Domenica

**Slot orari:**

Aggiungi gli orari disponibili:
- Es. 10:00, 14:00, 16:00

**Nota**: Puoi impostare capacità e buffer diversi per ogni orario.

### Passo 5: Meeting Point

**Tab "Meeting Point":**

1. Se non hai ancora creato un punto d'incontro:
   - Vai su **FP Experiences → Meeting Points**
   - Clicca **"Aggiungi nuovo"**
   - Compila: Titolo, Indirizzo, Coordinate (lat/lng), Note
   - Salva

2. Torna sull'esperienza e seleziona il **Meeting Point** dalla lista

### Passo 6: Pubblica

1. Controlla che tutti i tab siano compilati
2. Clicca **"Pubblica"** in alto a destra
3. ✅ Gli slot vengono generati automaticamente!

---

## 3. Gestire le Prenotazioni

### Visualizzare le Prenotazioni

1. Vai su **FP Experiences → Ordini**
2. Vedi la lista di tutti gli ordini delle esperienze

### Stati degli Ordini

Gli ordini possono avere questi stati:

- **⏳ In attesa**: Ordine creato, pagamento in corso
- **✅ Completato**: Pagamento ricevuto, prenotazione confermata
- **❌ Cancellato**: Ordine annullato
- **🔄 Rimborsato**: Ordine rimborsato

### Dettagli di una Prenotazione

Cliccando su un ordine vedi:

- **Informazioni cliente**: Nome, email, telefono
- **Esperienza prenotata**: Nome, data, orario
- **Biglietti**: Quantità e tipi selezionati
- **Totale pagato**: Importo e metodo di pagamento
- **Stato prenotazione**: Confermata, in attesa, ecc.

### Azioni Disponibili

Per ogni ordine puoi:

- **📧 Invia email**: Invia conferma o reminder al cliente
- **✏️ Modifica**: Cambia stato, note, ecc.
- **🗑️ Cancella**: Annulla la prenotazione (con rimborso se necessario)

---

## 4. Visualizzare gli Ordini

### Accesso agli Ordini

1. Vai su **FP Experiences → Ordini**
2. Vedi la lista filtrata solo per ordini esperienze

### Filtri Disponibili

Puoi filtrare per:

- **Data**: Ordini di oggi, questa settimana, questo mese
- **Stato**: Completati, in attesa, cancellati
- **Esperienza**: Filtra per esperienza specifica
- **Cliente**: Cerca per nome o email

### Esportazione

Puoi esportare gli ordini in CSV per analisi esterne.

---

## 5. Gestire il Calendario

### Visualizzare il Calendario

1. Vai su **FP Experiences → Dashboard → Calendario**
2. Vedi tutti gli slot disponibili e prenotati

### Cosa Vedi nel Calendario

- **🟢 Verde**: Slot disponibile (posti liberi)
- **🟡 Giallo**: Slot parzialmente prenotato
- **🔴 Rosso**: Slot completo (tutti i posti prenotati)
- **⚫ Grigio**: Slot non disponibile (giorno chiuso, eccezione)

### Modificare Slot Esistenti

1. Clicca su uno slot nel calendario
2. Puoi:
   - **Modificare capacità**: Aumentare/diminuire posti disponibili
   - **Chiudere slot**: Disabilitare temporaneamente
   - **Aggiungere note**: Informazioni per l'operatore

### Eccezioni e Chiusure

Per chiudere giorni specifici:

1. Vai sull'esperienza
2. Tab **"Calendario & Slot"**
3. Sezione **"Eccezioni"**
4. Aggiungi date di chiusura (es. festività, manutenzione)

---

## 6. Configurare Prezzi e Biglietti

### Modificare Prezzi

1. Vai sull'esperienza
2. Tab **"Biglietti & Prezzi"**
3. Modifica i prezzi esistenti o aggiungi nuovi tipi

### Sconti e Promozioni

Puoi creare:

- **Sconti percentuali**: Es. -10% su tutti i biglietti
- **Sconti fissi**: Es. -€5 su biglietti bambino
- **Promozioni stagionali**: Attiva/disattiva automaticamente

### Prezzi Dinamici

Alcune esperienze possono avere:

- **Prezzo a partire da**: Mostra il prezzo più basso
- **Prezzi variabili**: Diversi per stagione, giorno, orario

---

## 7. Impostazioni Generali

### Accesso alle Impostazioni

1. Vai su **FP Experiences → Impostazioni**

### Sezioni Disponibili

**Generali:**

- **Timezone**: Imposta il fuso orario corretto
- **Ruoli utente**: Permessi per operatori e manager
- **Meeting Points**: Abilita/disabilita sistema location

**Branding:**

- **Logo**: Carica il logo della tua azienda
- **Colori**: Personalizza colori primari e sezioni

**Email:**

- **Email conferma**: Template email inviata al cliente
- **Email reminder**: Email di promemoria prima dell'esperienza
- **Email amministratore**: Notifiche per nuove prenotazioni

**Integrazioni:**

- **Brevo**: Connetti per email marketing
- **Google Calendar**: Sincronizza prenotazioni
- **Google Analytics**: Tracking conversioni
- **Meta Pixel**: Tracking Facebook

**Pagamenti:**

- **Metodi pagamento**: Configura Stripe, PayPal, ecc.
- **Checkout**: Personalizza pagina checkout

---

## 8. Problemi Comuni

### "Il calendario è vuoto"

**Causa**: L'esperienza non ha slot generati o non è pubblicata.

**Soluzione**:
1. Verifica che l'esperienza sia **"Pubblicata"**
2. Controlla che ci siano giorni selezionati nella settimana
3. Verifica che ci sia almeno un orario configurato
4. Controlla che la capacità generale sia > 0
5. Modifica l'esperienza e salva di nuovo per rigenerare gli slot

### "Non vedo gli slot nel calendario"

**Causa**: Gli slot non sono stati generati o sono stati eliminati.

**Soluzione**:
1. Vai su **FP Experiences → Dashboard → Calendario**
2. Filtra per la tua esperienza
3. Se non ci sono slot, modifica l'esperienza e salva
4. Se ancora vuoto, vai su **FP Experiences → Strumenti** e usa **"Ripara slot"**

### "Errore al checkout"

**Causa**: Problema con WooCommerce o pagamenti.

**Soluzione**:
1. Verifica che WooCommerce sia installato e attivo
2. Controlla che ci sia una pagina checkout configurata
3. Verifica che il metodo di pagamento sia attivo
4. Controlla i log: **FP Experiences → Logs**

### "Il cliente non riceve email"

**Causa**: Email non configurate o problema SMTP.

**Soluzione**:
1. Vai su **FP Experiences → Impostazioni → Email**
2. Verifica che i template email siano configurati
3. Controlla che l'indirizzo mittente sia valido
4. Testa l'invio email da **FP Experiences → Email → Test**

### "Meeting point non mostra mappa"

**Causa**: Coordinate mancanti o errate.

**Soluzione**:
1. Vai su **FP Experiences → Meeting Points**
2. Apri il meeting point
3. Verifica che latitudine e longitudine siano corrette
4. Usa Google Maps per trovare le coordinate esatte

### "Prezzo non si aggiorna nel frontend"

**Causa**: Cache del browser o del sito.

**Soluzione**:
1. Svuota la cache del browser (Ctrl+F5)
2. Se usi plugin cache, svuota anche quella
3. Vai su **FP Experiences → Strumenti → Pulisci cache**

---

## 📞 Supporto e Aiuto

### Documentazione

- **Guida rapida**: `docs/admin/QUICK-START.md`
- **Guida completa**: `docs/admin/ADMIN-GUIDE.md`
- **FAQ**: `docs/admin/FAQ.md`

### Contatti

Per problemi o domande:

- 📧 **Email**: support@formazionepro.it
- 🐛 **Issue Tracker**: [GitHub Issues](https://github.com/franpass87/FP-Experiences/issues)

---

## ✅ Checklist Operativa Giornaliera

### Mattina

- [ ] Controlla Dashboard per nuove prenotazioni
- [ ] Verifica ordini in attesa di pagamento
- [ ] Controlla email di notifica

### Durante il Giorno

- [ ] Gestisci nuove prenotazioni
- [ ] Aggiorna disponibilità slot se necessario
- [ ] Rispondi a richieste clienti

### Sera

- [ ] Rivedi prenotazioni del giorno
- [ ] Prepara lista partecipanti per domani
- [ ] Verifica calendario per la settimana

---

## 🎯 Suggerimenti per un Uso Efficace

### Ottimizza il Calendario

- Offri almeno **3 slot al giorno** per massimizzare prenotazioni
- Copri **weekend e festivi** quando possibile
- Mantieni capacità **realistica** (non sovrastimare)

### Prezzi Chiari

- Usa prezzi **tondi** (es. €25 invece di €24.99)
- Offri **sconti bambini** (50-70% del prezzo adulto)
- Aggiungi **add-ons a valore percepito**

### Descrizioni Coinvolgenti

- Usa **storytelling emotivo**
- Evidenzia cosa rende **unica** l'esperienza
- Includi cosa è **incluso/escluso**
- Aggiungi **testimonial** se disponibili

### Monitora le Performance

- Controlla **riempimento settimanale** dalla Dashboard
- Analizza quali **slot sono più popolari**
- Aggiusta prezzi e disponibilità in base ai dati

---

**Buon lavoro!** 🚀

*Ultimo aggiornamento: Gennaio 2026*
