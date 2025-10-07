# Guida Completa all'Importer di Esperienze

## Introduzione

L'Importer di Esperienze è uno strumento potente che permette di caricare velocemente multiple esperienze tramite un file CSV. Questa guida completa include tutto quello che serve per utilizzare l'importer, dalla configurazione iniziale alle best practices.

## Accesso all'Importer

1. Accedi al pannello di amministrazione di WordPress
2. Nel menu laterale, vai su **FP Experiences** → **Importer Esperienze**
3. La pagina dell'importer si aprirà con la guida integrata e il form di caricamento

## Quick Start

### Passo 1: Scarica il Template CSV
Nella pagina dell'importer, clicca sul pulsante **"Scarica Template CSV"**. Questo scaricherà un file CSV pre-formattato con:
- Tutte le colonne necessarie con i nomi corretti
- Una riga di esempio con dati dimostrativi
- La corretta codifica UTF-8

### Passo 2: Compila il File CSV
Apri il file CSV scaricato con:
- **Microsoft Excel**
- **Google Sheets**
- **LibreOffice Calc**
- Qualsiasi altro editor di fogli di calcolo

**IMPORTANTE**: Mantieni la prima riga (intestazioni delle colonne) invariata!

### Passo 3: Importa
1. Carica il file CSV compilato
2. Verifica i risultati nella pagina di anteprima
3. Conferma l'import

## Struttura dei Campi

### Campi Obbligatori

#### `title`
- **Tipo**: Testo
- **Descrizione**: Il nome dell'esperienza
- **Esempio**: `"Tour della città storica"`
- **Note**: Questo è l'unico campo veramente obbligatorio

#### `status`
- **Tipo**: Testo
- **Valori permessi**: `publish`, `draft`, `pending`, `private`
- **Default**: `draft`
- **Descrizione**: Lo stato di pubblicazione dell'esperienza

### Campi di Contenuto

#### `description`
- **Tipo**: HTML/Testo lungo
- **Descrizione**: Descrizione completa dell'esperienza
- **Supporto HTML**: ✅ Sì (tag sicuri)
- **Esempio**: `"Scopri i segreti della nostra bellissima città con una guida esperta."`

#### `excerpt`
- **Tipo**: Testo breve
- **Descrizione**: Breve estratto dell'esperienza
- **Esempio**: `"Un tour imperdibile per scoprire la città"`
- **Lunghezza consigliata**: 100-200 caratteri

#### `short_desc`
- **Tipo**: Testo breve
- **Descrizione**: Descrizione breve per overview
- **Esempio**: `"Tour guidato di 2 ore nel centro storico"`

### Campi Numerici

#### `duration_minutes`
- **Tipo**: Numero intero
- **Descrizione**: Durata dell'esperienza in minuti
- **Esempio**: `120` (per 2 ore)
- **Range tipico**: 30-480 minuti

#### `base_price`
- **Tipo**: Numero decimale
- **Formato**: Usa il punto come separatore decimale
- **Esempi**: `35.00`, `49.99`, `150`
- **Note**: Non includere simboli di valuta

#### `min_party`, `capacity_slot`, `age_min`, `age_max`
- **Tipo**: Numero intero
- **Descrizione**: Limiti di partecipanti, capacità, età
- **Esempi**: `2`, `15`, `8`, `99`

### Campi di Testo Lungo

#### `meeting_point`
- **Tipo**: Testo
- **Descrizione**: Descrizione del punto d'incontro
- **Esempio**: `"Piazza del Duomo, davanti alla fontana centrale"`

#### `what_to_bring`
- **Tipo**: Testo
- **Descrizione**: Cosa dovrebbero portare i partecipanti
- **Esempio**: `"Scarpe comode, acqua, macchina fotografica"`

#### `notes`, `policy_cancel`
- **Tipo**: Testo lungo
- **Descrizione**: Note importanti e politiche di cancellazione

### Campi Lista (con separatore |)

I seguenti campi accettano più valori separati dal carattere **pipe** (`|`):

#### `highlights`
- **Tipo**: Lista di testi
- **Separatore**: `|`
- **Esempio**: `"Centro storico|Monumenti principali|Guida esperta|Storia affascinante"`
- **Note**: NON aggiungere spazi prima o dopo il separatore

#### `inclusions`, `exclusions`
- **Tipo**: Lista di testi
- **Separatore**: `|`
- **Esempio**: `"Guida turistica|Biglietti d'ingresso|Mappa della città"`

#### `languages`, `themes`
- **Tipo**: Lista di codici/nomi
- **Separatore**: `|`
- **Esempio**: `"Italiano|English|Español|Français"`
- **Note**: Se il tema non esiste, verrà creato automaticamente

### Campi Booleani

#### `family_friendly`
- **Tipo**: Sì/No
- **Valori accettati**: `yes`, `si`, `sì`, `1`, `true`, `no`, `0`, `false`
- **Descrizione**: Indica se l'esperienza è adatta alle famiglie
- **Note**: Case-insensitive

## Best Practices

### 1. Preparazione dei Dati
- ✅ **Controlla** tutti i campi obbligatori prima dell'import
- ✅ **Verifica** che i numeri usino il punto come separatore decimale
- ✅ **Usa** il carattere pipe (`|`) per i campi lista
- ✅ **Mantieni** la codifica UTF-8 del file
- ✅ **Salva** una copia di backup prima di modificare

### 2. Codifica e Formato
- **Codifica**: UTF-8 (obbligatorio)
- **Separatore**: Virgola (standard CSV)
- **Separatore lista**: Pipe (`|`)
- **Separatore decimale**: Punto (`.`)

### 3. Contenuto di Qualità
- **Descrizioni**: Scrivi descrizioni dettagliate e coinvolgenti
- **Highlights**: Massimo 5-7 punti salienti per esperienza
- **Immagini**: L'importer non carica immagini - aggiungile manualmente dopo
- **SEO**: Usa parole chiave rilevanti nelle descrizioni

### 4. Testing
Prima di importare molte esperienze:
1. **Test con una riga**: Prova con una singola esperienza
2. **Verifica**: Controlla che tutti i campi siano importati correttamente
3. **Correggi**: Se necessario, modifica il CSV e riprova
4. **Import completo**: Solo dopo aver verificato che tutto funzioni

## Risoluzione Problemi

### Il file non viene caricato
**Causa**: Formato file non corretto
**Soluzione**: 
- Assicurati che il file sia in formato CSV
- Verifica la codifica UTF-8
- Prova a salvare nuovamente da Excel con "CSV UTF-8"

### Alcuni campi non vengono importati
**Causa**: Nomi colonne non corrispondono
**Soluzione**:
- Usa esattamente i nomi delle colonne del template
- Non modificare l'intestazione (prima riga)
- Controlla che non ci siano spazi extra

### Caratteri strani nel testo
**Causa**: Problema di codifica
**Soluzione**:
- Assicurati che il file sia salvato in UTF-8
- In Excel: Salva come "CSV UTF-8 (delimitato da virgole)"
- In Google Sheets: File → Scarica → CSV (UTF-8)

### Le liste non vengono separate correttamente
**Causa**: Separatore errato
**Soluzione**:
- Usa solo il carattere pipe (`|`)
- Non aggiungere spazi: `item1|item2` ✅ non `item1 | item2` ❌

### L'import è lento
**Causa**: File molto grande
**Soluzione**:
- Dividi il file in batch più piccoli (50-100 righe)
- Importa in più volte
- Evita di importare durante ore di punta

## Limitazioni

### Cosa NON viene importato
L'importer CSV ha alcune limitazioni intenzionali:
- ❌ **Immagini**: Non supportate (aggiungi manualmente)
- ❌ **Gallery**: Non supportate (aggiungi manualmente)
- ❌ **Calendari/Schedule**: Non supportati (configura manualmente)
- ❌ **Tipi di biglietto**: Non supportati (configura manualmente)
- ❌ **Add-ons**: Non supportati (configura manualmente)
- ❌ **FAQ**: Non supportate (aggiungi manualmente)
- ❌ **Meeting points**: Solo descrizione testuale (configura ID manualmente)

### Cosa viene creato
- ✅ **Post esperienza** con tutti i campi base
- ✅ **Meta campi** (durata, prezzi, limiti, ecc.)
- ✅ **Tassonomie** (temi, family-friendly)
- ✅ **Contenuti testuali** (descrizione, highlights, inclusions, ecc.)

## Workflow Consigliato

### Per nuove esperienze
1. **Scarica** il template CSV
2. **Compila** i campi base per tutte le esperienze
3. **Importa** il CSV
4. **Verifica** le esperienze create
5. **Completa** manualmente:
   - Immagini in evidenza
   - Gallery
   - Calendari e disponibilità
   - Tipi di biglietto e prezzi avanzati
   - FAQ se necessarie

### Per aggiornamenti
L'importer **NON** aggiorna esperienze esistenti. Crea sempre nuove esperienze.

Per aggiornamenti di massa:
- Usa l'editor WordPress standard
- Oppure usa plugin di import/export più avanzati come WP All Import

## Supporto Tecnico

### File di Log
Gli errori di import vengono registrati nei log del plugin:
- Vai su **FP Experiences** → **Logs**
- Filtra per categoria `experience_import`
- Controlla i messaggi di errore

### Esempi di File CSV

#### Esempio Minimo (solo campi obbligatori)
```csv
title,status
"Tour Colosseo",publish
"Visita Musei Vaticani",draft
"Cooking Class",publish
```

#### Esempio Completo
```csv
title,status,description,duration_minutes,base_price,highlights,inclusions,themes,languages,family_friendly
"Tour della città storica",publish,"Scopri i segreti della nostra bellissima città con una guida esperta.",120,35.00,"Centro storico|Monumenti|Guida esperta","Guida turistica|Biglietti d'ingresso","Cultura|Storia","Italiano|English",yes
```

## Domande Frequenti

### Posso importare immagini?
No, l'importer non supporta le immagini. Aggiungile manualmente dopo l'import modificando ogni esperienza.

### Come importo più lingue per lo stesso contenuto?
L'importer crea contenuti in una sola lingua. Per contenuti multilingua, usa plugin WPML o Polylang dopo l'import.

### Posso aggiornare esperienze esistenti?
No, l'importer crea solo nuove esperienze. Non sovrascrive o aggiorna quelle esistenti.

### Quante esperienze posso importare alla volta?
Non c'è un limite rigido, ma consigliamo batch di 50-100 esperienze per evitare timeout del server.

### Cosa succede se ci sono errori in alcune righe?
Le righe con errori vengono saltate e registrate nei log. Le righe valide vengono comunque importate.

## Checklist Pre-Import

Prima di procedere con l'import, verifica:

- [ ] Il file è in formato CSV
- [ ] La codifica è UTF-8
- [ ] La prima riga contiene le intestazioni delle colonne
- [ ] I nomi delle colonne corrispondono al template
- [ ] Tutte le righe hanno il campo `title` compilato
- [ ] I numeri decimali usano il punto (`.`)
- [ ] Le liste usano il separatore pipe (`|`)
- [ ] Il campo `status` contiene valori validi
- [ ] Hai fatto un backup del sito (consigliato)
- [ ] Hai testato con 1-2 righe prima dell'import completo

## Conclusione

L'Importer di Esperienze è progettato per velocizzare la creazione iniziale delle esperienze. Dopo l'import, potrai:

1. Arricchire le esperienze con immagini e media
2. Configurare calendari e disponibilità
3. Impostare prezzi avanzati e biglietti
4. Aggiungere FAQ e dettagli extra

Per supporto o segnalazione bug, contatta l'amministratore del plugin.

---

**Ultimo aggiornamento**: 2025-01-27
