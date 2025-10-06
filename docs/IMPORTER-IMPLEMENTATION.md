# Implementazione Sistema Importer Esperienze

## Panoramica

È stato implementato un sistema completo per l'importazione veloce di esperienze tramite file CSV, comprensivo di template scaricabile e guida dettagliata integrata.

## Componenti Implementati

### 1. Classe ImporterPage (`src/Admin/ImporterPage.php`)

Nuova classe che gestisce:
- Rendering della pagina admin dell'importer
- Form di upload del file CSV
- Generazione e download del template CSV
- Parsing e validazione del file CSV caricato
- Creazione automatica delle esperienze da CSV
- Gestione errori e feedback utente

**Caratteristiche principali:**
- Guida integrata nella pagina admin
- Template CSV con esempio precompilato
- Validazione dati con feedback dettagliato
- Log degli errori per debugging
- Gestione sicura dei file caricati
- Sanitizzazione completa dei dati

### 2. Integrazione nel Menu Admin

**File modificati:**
- `src/Admin/AdminMenu.php` - Aggiunta voce menu "Importer Esperienze"
- `src/Plugin.php` - Registrazione e boot della classe ImporterPage

**Posizione nel menu:**
```
FP Experiences
├── Dashboard
├── Esperienze
├── Nuova esperienza
├── Importer Esperienze  ← NUOVO
├── Meeting point
├── Calendario
...
```

### 3. Stili CSS

**File modificato:** `assets/css/admin.css`

Aggiunti stili per:
- `.fp-exp-card` - Card container per sezioni
- `.fp-exp-guide-section` - Sezioni della guida
- `.fp-exp-guide-list` - Liste con stile
- `.fp-exp-guide-tip` - Box suggerimenti
- `.fp-exp-import-form` - Form di upload
- Input file con stile drag-and-drop

### 4. Documentazione

Creati tre documenti nella cartella `docs/`:

#### a) `IMPORTER-GUIDE.md`
Guida completa e dettagliata (oltre 500 righe) che copre:
- Introduzione e accesso
- Processo di import step-by-step
- Descrizione dettagliata di TUTTI i campi
- Best practices e workflow consigliati
- Troubleshooting completo
- FAQ
- Esempi pratici

#### b) `IMPORTER-QUICK-START.md`
Guida rapida di riferimento con:
- Avvio veloce in 4 passi
- Tabella campi essenziali
- Esempi CSV immediati
- Checklist pre-import
- Problemi comuni e soluzioni rapide

#### c) `IMPORTER-IMPLEMENTATION.md`
Questo documento tecnico per sviluppatori

## Campi Supportati nell'Import

### Campi Obbligatori
1. `title` - Titolo dell'esperienza

### Campi Opzionali - Base
2. `status` - Stato pubblicazione (publish/draft/pending/private)
3. `description` - Descrizione completa (supporta HTML)
4. `excerpt` - Breve estratto
5. `short_desc` - Descrizione breve

### Campi Opzionali - Numerici
6. `duration_minutes` - Durata in minuti
7. `base_price` - Prezzo base (decimale)
8. `min_party` - Minimo partecipanti
9. `capacity_slot` - Capacità massima
10. `age_min` - Età minima
11. `age_max` - Età massima

### Campi Opzionali - Testo
12. `meeting_point` - Punto d'incontro
13. `what_to_bring` - Cosa portare
14. `notes` - Note importanti
15. `policy_cancel` - Politica cancellazione

### Campi Opzionali - Liste (separatore |)
16. `highlights` - Punti salienti
17. `inclusions` - Cosa è incluso
18. `exclusions` - Cosa NON è incluso
19. `languages` - Lingue disponibili
20. `themes` - Temi/categorie

### Campi Opzionali - Booleani
21. `family_friendly` - Adatto alle famiglie (yes/no)

## Funzionalità Chiave

### Template CSV
- Generazione dinamica del template
- Include tutte le colonne necessarie
- Riga di esempio con dati dimostrativi
- Codifica UTF-8 con BOM per compatibilità Excel
- Download sicuro con headers corretti

### Processo di Import
1. **Upload**: Form sicuro con validazione file
2. **Parsing**: Lettura CSV con gestione errori
3. **Validazione**: Controllo dati per ogni riga
4. **Creazione**: Inserimento esperienza con wp_insert_post()
5. **Meta**: Aggiornamento meta fields
6. **Tassonomie**: Creazione/assegnazione termini
7. **Feedback**: Messaggi di successo/errore

### Sicurezza
- Nonce verification per tutte le form
- Capability check (Helpers::can_manage_fp())
- Sanitizzazione di tutti gli input
  - `sanitize_text_field()` per testi brevi
  - `sanitize_textarea_field()` per testi lunghi
  - `wp_kses_post()` per HTML
  - Type casting per numerici
- Validazione separatori e formati
- Gestione sicura file upload

### Gestione Errori
- Try-catch per operazioni critiche
- Validazione riga per riga
- Righe con errori vengono saltate
- Log dettagliato degli errori
- Report finale import con conteggi
- Messaggi admin notice per feedback utente

## Workflow Utente

### Scenario Tipico

1. **Preparazione**
   - Utente accede a "Importer Esperienze"
   - Legge la guida integrata
   - Scarica il template CSV

2. **Compilazione**
   - Apre template con Excel/Sheets
   - Compila i dati delle esperienze
   - Salva in formato CSV UTF-8

3. **Import**
   - Carica il file CSV
   - Clicca "Importa Esperienze"
   - Riceve conferma con numero esperienze importate

4. **Completamento**
   - Verifica esperienze create
   - Aggiunge manualmente immagini
   - Configura calendari e biglietti

## Limitazioni Note

### Non Supportato
- ❌ Immagini (thumbnail, gallery)
- ❌ Schedule/calendari
- ❌ Tipi di biglietto custom
- ❌ Add-ons
- ❌ FAQ strutturate
- ❌ Meeting point ID (solo testo)
- ❌ Risorse
- ❌ Pricing rules avanzate

### Perché?
Questi elementi richiedono:
- Upload di file (immagini)
- Strutture dati complesse (schedule, tickets)
- Relazioni con altri post types (meeting points)
- Configurazione interattiva (calendar rules)

Sono più adatti a configurazione manuale post-import.

## Performance

### Ottimizzazioni
- Processing batch row-by-row
- Skip immediato righe vuote
- Validazione early-exit su errori critici
- No query inutili per campi vuoti

### Raccomandazioni
- Import batch di 50-100 righe consigliato
- Evitare file con migliaia di righe
- Possibile timeout PHP su server lenti
- Usare PHP max_execution_time adeguato

## Estensioni Future Possibili

### Funzionalità Aggiuntive
1. **Import immagini via URL**
   - Colonna `image_url` per scaricare immagini
   - Automatic thumbnail generation

2. **Update mode**
   - Matching per post_title o meta
   - Aggiornamento esperienze esistenti

3. **Excel support**
   - Parsing file .xlsx nativi
   - Richiede libreria PHPSpreadsheet

4. **Validation preview**
   - Preview dati prima import
   - Correzione errori inline

5. **Progress bar**
   - Import asincrono via AJAX
   - Real-time progress reporting

6. **Export CSV**
   - Esportazione esperienze esistenti
   - Modifica e re-import

## Testing

### Scenari da Testare

#### Test Base
- [ ] Import file vuoto → errore
- [ ] Import singola esperienza → successo
- [ ] Import 10 esperienze → successo
- [ ] Import con riga errata → skip riga, log errore

#### Test Campi
- [ ] Solo title → esperienza minima creata
- [ ] Tutti i campi → esperienza completa
- [ ] HTML in description → sanitizzato correttamente
- [ ] Liste con pipe → array corretto
- [ ] Numeri decimali → float corretto
- [ ] Status invalido → default a draft

#### Test Sicurezza
- [ ] Upload file non-CSV → errore
- [ ] File gigante → timeout gestito
- [ ] Caratteri speciali → sanitizzati
- [ ] Script injection → bloccato

#### Test Usabilità
- [ ] Template scaricabile → CSV valido
- [ ] Guida leggibile → informazioni chiare
- [ ] Form intuitivo → facile da usare
- [ ] Errori comprensibili → messaggi chiari

## Manutenzione

### Aggiornamenti Futuri
Quando si aggiungono nuovi meta fields a ExperienceCPT:

1. Aggiornare `generate_template_csv()` con nuova colonna
2. Aggiornare `update_experience_meta()` con mapping
3. Aggiornare documentazione guida
4. Aggiungere esempi nel template

### Compatibilità
- WordPress 6.0+
- PHP 8.0+
- Nessuna dipendenza esterna
- Usa solo core WordPress functions

## Conclusione

Il sistema di import è:
- ✅ **Completo**: Copre tutti i campi essenziali
- ✅ **Sicuro**: Validazione e sanitizzazione completa
- ✅ **Documentato**: Guide dettagliate per utenti e sviluppatori
- ✅ **Usabile**: Interfaccia intuitiva con guida integrata
- ✅ **Estensibile**: Facile aggiungere nuovi campi
- ✅ **Manutenibile**: Codice pulito e commentato

Pronto per l'utilizzo in produzione! 🚀
