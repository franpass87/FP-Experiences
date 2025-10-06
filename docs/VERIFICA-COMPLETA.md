# Guida di Verifica Completa - FP Experiences

## Introduzione

Questa guida consolida tutti i controlli di verifica per il plugin FP Experiences, inclusi layout esperienza, binding frontend, listing e meeting experience.

## 1. Verifica Layout Esperienza

### Hero Gallery Management
- [x] Apri il controllo "Immagine hero" e conferma che il container preview mantenga un rapporto 16:9 senza overflow della sidebar
- [x] Carica una nuova immagine hero e assicurati che la thumbnail rimanga visibile dopo la chiusura del modal media
- [x] Rimuovi la selezione e verifica che il placeholder grafico si riallinei correttamente

### Experience Overview Details
- [x] Salva durate aggiuntive e il toggle "Family friendly"; ricarica la pagina e conferma che i valori rimangano selezionati nel form backend
- [x] Pubblica l'esperienza e controlla che il blocco overview frontend elenchi le nuove durate, badge family friendly, e qualsiasi tema o lingua selezionata
- [x] Assicurati che i trust badge appaiano immediatamente dopo la griglia dettagli senza regressioni di spacing

### Children Rules
- [x] Inserisci testo nel textarea "Regole bambini", salva, e conferma che il testo persista quando editi di nuovo
- [x] Visita la pagina esperienza frontend e verifica che il testo sia renderizzato nella colonna "Good to know" extras
- [x] Lascia il campo vuoto e assicurati che il frontend ometta la sezione regole bambini

### Ticket Repeater
- [x] Aggiungi più tipi di biglietto con nomi unici e pubblica il prodotto; riapri l'editor per confermare che tutte le righe rimangano presenti con i loro dati intatti
- [x] Riordina le righe biglietto e verifica che i campi nome, prezzo e capacità rimangano collegati alla riga corretta dopo il salvataggio

### Recurring Slot Configuration
- [x] Regola la frequenza "Ricorrenza slot" e conferma che le checkbox giorno settimanale appaiano solo quando "Settimanale" è selezionato
- [x] Genera slot settimanali con più set di tempo e assicurati che ognuno erediti la selezione giorno top-level quando non sono definiti giorni per-set
- [x] Configura una ricorrenza "Date specifiche" e controlla che la generazione crei solo slot sui giorni elencati
- [x] Tenta di generare slot senza voci di tempo e osserva la validazione inline che previene la richiesta

## 2. Verifica Front Binding

### Meta Data Binding
- [x] Verifica che i metadati esperienza si leghino correttamente ai template frontend
- [x] Controlla che i campi custom si mostrino nelle pagine pubbliche
- [x] Assicurati che i valori predefiniti vengano applicati quando i campi sono vuoti

### Shortcode Integration
- [x] Testa tutti gli shortcode disponibili: `[fp_exp_list]`, `[fp_exp_widget]`, `[fp_exp_calendar]`, `[fp_exp_checkout]`, `[fp_exp_page]`
- [x] Verifica che gli shortcode rispettino gli attributi forniti
- [x] Controlla che i shortcode si integrino correttamente con i template del tema

### Elementor Widgets
- [x] Testa tutti i widget Elementor: List, Widget, Calendar, Checkout, Meeting Points, Experience Page
- [x] Verifica che i widget mantengano le impostazioni quando salvati
- [x] Controlla che i widget si aggiornino correttamente in tempo reale

## 3. Verifica Listing

### Filter System
- [x] Testa tutti i filtri disponibili: tema, lingua, durata, range prezzo, family-friendly, data, ricerca testuale
- [x] Verifica che i filtri attivi appaiano come chip rimovibili
- [x] Controlla che il pulsante reset ripristini tutti i filtri mantenendo sort/view state

### Sorting and Pagination
- [x] Testa tutti gli ordinamenti: prezzo, data, titolo, menu_order
- [x] Verifica che la paginazione funzioni correttamente
- [x] Controlla che i controlli di ordinamento mantengano i filtri attivi

### Price Display
- [x] Verifica che i badge "price from" mostrino il prezzo più basso disponibile
- [x] Controlla che i prezzi vengano formattati correttamente con la valuta del negozio
- [x] Assicurati che i prezzi si aggiornino quando cambiano i tipi di biglietto

### Responsive Design
- [x] Testa il layout su desktop, tablet e mobile
- [x] Verifica che le card si adattino correttamente alle diverse dimensioni
- [x] Controlla che i filtri rimangano usabili su dispositivi touch

## 4. Verifica Meeting Experience

### Meeting Points Display
- [x] Verifica che i meeting point primari e alternativi si mostrino correttamente
- [x] Controlla che i link alle mappe funzionino
- [x] Assicurati che le informazioni di contatto siano accessibili

### Location Integration
- [x] Testa l'integrazione con Google Maps
- [x] Verifica che le coordinate vengano utilizzate correttamente
- [x] Controlla che i link alle mappe si aprano in nuove finestre

### Alternative Meeting Points
- [x] Testa la visualizzazione di meeting point alternativi
- [x] Verifica che i meeting point alternativi siano collassabili
- [x] Controlla che la navigazione tra meeting point funzioni correttamente

## 5. Verifica Booking Flow

### Widget Booking
- [x] Testa la selezione di date e slot
- [x] Verifica che i tipi di biglietto e gli add-on si mostrino correttamente
- [x] Controlla che il calcolo dei prezzi sia accurato
- [x] Assicurati che il widget sia sticky su mobile

### Checkout Process
- [x] Testa il processo di checkout completo
- [x] Verifica che i dati del cliente vengano raccolti correttamente
- [x] Controlla che gli ordini WooCommerce vengano creati
- [x] Assicurati che le email di conferma vengano inviate

### Request to Book (RTB)
- [x] Testa il flusso RTB per prenotazioni non immediate
- [x] Verifica che le richieste vengano salvate correttamente
- [x] Controlla che gli admin possano approvare/rifiutare richieste
- [x] Assicurati che i link di pagamento funzionino

## 6. Verifica Admin Interface

### Experience Editor
- [x] Testa tutte le tab dell'editor: Dettagli, Biglietti & Prezzi, Calendario & Slot, Meeting Point, Extra, Policy/FAQ, SEO/Schema
- [x] Verifica che la navigazione tra tab funzioni correttamente
- [x] Controlla che i metadati vengano salvati correttamente

### Calendar Admin
- [x] Testa la visualizzazione calendario con filtri per esperienza
- [x] Verifica che il drag-and-drop per riprogrammare funzioni
- [x] Controlla che la creazione manuale di prenotazioni funzioni
- [x] Assicurati che i link di pagamento vengano generati correttamente

### Settings and Tools
- [x] Testa tutte le impostazioni: General, Branding, Showcase, Gift, Tracking, Brevo, Calendar
- [x] Verifica che le impostazioni vengano salvate e applicate
- [x] Controlla che gli strumenti di diagnostica funzionino

## 7. Verifica Integrazioni

### Brevo Integration
- [x] Testa la sincronizzazione contatti
- [x] Verifica che le email transazionali vengano inviate
- [x] Controlla che i webhook vengano gestiti correttamente

### Google Calendar
- [x] Testa la connessione OAuth
- [x] Verifica che gli eventi vengano creati/aggiornati/cancellati
- [x] Controlla che i token vengano rinnovati automaticamente

### Marketing Tracking
- [x] Testa GA4, Google Ads, Meta Pixel, Clarity
- [x] Verifica che il consenso venga rispettato
- [x] Controlla che i dati vengano inviati correttamente

## 8. Verifica Performance

### Loading Times
- [x] Misura i tempi di caricamento delle pagine principali
- [x] Verifica che gli asset vengano caricati solo quando necessario
- [x] Controlla che la cache funzioni correttamente

### Database Queries
- [x] Verifica che non ci siano query N+1
- [x] Controlla che gli indici delle tabelle custom funzionino
- [x] Assicurati che le query siano ottimizzate

## 9. Verifica Accessibilità

### Screen Reader Support
- [x] Testa con screen reader (NVDA, JAWS, VoiceOver)
- [x] Verifica che tutti gli elementi interattivi abbiano etichette appropriate
- [x] Controlla che la navigazione da tastiera funzioni

### Keyboard Navigation
- [x] Testa la navigazione completa da tastiera
- [x] Verifica che il focus sia gestito correttamente
- [x] Controlla che i widget sticky catturino il focus

### Color Contrast
- [x] Verifica che i colori rispettino gli standard WCAG AA
- [x] Controlla che i contrasti siano sufficienti
- [x] Assicurati che le informazioni non dipendano solo dal colore

## 10. Verifica Sicurezza

### Input Validation
- [x] Testa con input maliziosi
- [x] Verifica che i dati vengano sanificati
- [x] Controlla che le query SQL siano preparate

### Permission Checks
- [x] Verifica che i controlli di capability funzionino
- [x] Testa che gli utenti non autorizzati non possano accedere
- [x] Controlla che i nonce vengano validati

### Rate Limiting
- [x] Testa il rate limiting sui endpoint pubblici
- [x] Verifica che i burst vengano bloccati
- [x] Controlla che i header no-cache vengano inviati

## Checklist Finale

Prima del rilascio, verifica:

- [ ] Tutti i test di verifica sono stati completati
- [ ] Non ci sono errori JavaScript nella console
- [ ] Non ci sono errori PHP nei log
- [ ] Tutte le funzionalità principali funzionano
- [ ] Le integrazioni sono operative
- [ ] La performance è accettabile
- [ ] L'accessibilità è conforme
- [ ] La sicurezza è verificata

## Conclusione

Questa guida di verifica completa assicura che tutti gli aspetti del plugin FP Experiences siano testati e funzionanti prima del rilascio. Ogni sezione dovrebbe essere verificata sistematicamente per garantire la qualità del prodotto finale.

---

**Ultimo aggiornamento**: 2025-01-27
