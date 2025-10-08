# Funzionalità Selezione Addon: Checkbox e Radio

## Panoramica

È stata implementata una nuova funzionalità che permette di configurare gli addon con diversi tipi di selezione:
- **Checkbox**: Permette di selezionare multipli addon insieme (selezione multipla)
- **Radio**: Permette di selezionare solo un addon tra quelli del gruppo (selezione singola, mutuamente esclusiva)

Gli addon possono anche essere organizzati in **gruppi** per raggruppare opzioni correlate.

## Caratteristiche Implementate

### 1. Nuovi Campi nell'Admin

Nell'editor dell'esperienza, nella sezione "Biglietti & Prezzi" → "Extra", ogni addon ora ha due nuovi campi:

#### **Tipo selezione**
- **Checkbox (multipla)**: Gli utenti possono selezionare questo addon insieme ad altri addon
- **Radio (singola)**: Gli utenti possono selezionare solo un addon tra quelli dello stesso gruppo

#### **Gruppo selezione**
- Campo di testo opzionale per raggruppare addon correlati
- Per gli addon **radio**: gli addon con lo stesso nome di gruppo diventano mutuamente esclusivi (l'utente può sceglierne solo uno)
- Per gli addon **checkbox**: gli addon con lo stesso nome di gruppo vengono visualizzati insieme in una sezione distinta per organizzazione visuale
- Se lasciato vuoto, l'addon apparirà nel gruppo predefinito

### 2. Funzionamento nel Frontend

#### **Widget di Prenotazione**
- Gli addon vengono raggruppati automaticamente in base al campo "Gruppo selezione"
- Ogni gruppo con un nome viene visualizzato in un fieldset separato con il nome del gruppo come titolo
- Gli addon radio dello stesso gruppo permettono la selezione di una sola opzione
- Gli addon checkbox possono essere selezionati liberamente (multipli o nessuno)

#### **Modal Regalo**
- Stessa logica del widget di prenotazione
- I gruppi di addon vengono visualizzati in sezioni separate
- Supporto completo per radio e checkbox

## Esempi di Utilizzo

### Esempio 1: Opzioni di Trasporto (Radio - Mutuamente Esclusive)

Configurazione nell'admin:

| Nome Extra | Tipo selezione | Gruppo selezione | Prezzo |
|------------|----------------|------------------|--------|
| Transfer Standard | Radio (singola) | Trasporto | €15 |
| Transfer VIP | Radio (singola) | Trasporto | €30 |
| Transfer Privato | Radio (singola) | Trasporto | €50 |

**Risultato**: L'utente potrà selezionare **solo una** delle opzioni di trasporto (o nessuna).

### Esempio 2: Extra Multipli (Checkbox - Selezione Multipla)

Configurazione nell'admin:

| Nome Extra | Tipo selezione | Gruppo selezione | Prezzo |
|------------|----------------|------------------|--------|
| Audio guida | Checkbox (multipla) | Extra | €5 |
| Pranzo | Checkbox (multipla) | Extra | €20 |
| Fotografia | Checkbox (multipla) | Extra | €10 |

**Risultato**: L'utente potrà selezionare **quanti extra vuole** (tutti, alcuni, o nessuno).

### Esempio 3: Configurazione Mista con Gruppi

Configurazione nell'admin:

| Nome Extra | Tipo selezione | Gruppo selezione | Prezzo |
|------------|----------------|------------------|--------|
| Transfer Standard | Radio (singola) | Trasporto | €15 |
| Transfer VIP | Radio (singola) | Trasporto | €30 |
| Audio guida | Checkbox (multipla) | Servizi | €5 |
| Pranzo | Checkbox (multipla) | Servizi | €20 |
| Vino | Checkbox (multipla) | Servizi | €8 |

**Risultato**: 
- Nel frontend appariranno 2 sezioni distinte:
  - **Trasporto**: con radio button (l'utente può sceglierne solo uno)
  - **Servizi**: con checkbox (l'utente può sceglierne quanti vuole)

### Esempio 4: Addon Senza Gruppo

Se lasci il campo "Gruppo selezione" vuoto, l'addon apparirà nella sezione predefinita "Extra" senza un raggruppamento particolare.

## Come Configurare

### Passo 1: Accedi all'Admin di WordPress
1. Vai su **FP Experiences** → **Esperienze**
2. Modifica un'esperienza esistente o creane una nuova

### Passo 2: Configura gli Addon
1. Scorri fino alla sezione **Biglietti & Prezzi**
2. Clicca sulla tab **Extra**
3. Per ogni addon che aggiungi o modifichi:
   - **Nome extra**: Nome visualizzato (es. "Transfer VIP")
   - **Codice**: Slug univoco (es. "transfer-vip")
   - **Descrizione breve**: Descrizione opzionale
   - **Prezzo**: Prezzo dell'addon
   - **Calcolo**: Per persona o per prenotazione
   - **Tipo selezione**: Scegli tra Checkbox o Radio
   - **Gruppo selezione**: Nome del gruppo (es. "Trasporto", "Pranzo", "Servizi")

### Passo 3: Salva e Verifica
1. Clicca su **Aggiorna** o **Pubblica**
2. Visualizza l'esperienza nel frontend
3. Verifica che gli addon siano visualizzati correttamente nel widget di prenotazione

## Best Practices

### 1. Quando Usare Radio Button
- **Opzioni mutuamente esclusive**: Quando l'utente deve scegliere solo una opzione tra diverse alternative
- Esempi comuni:
  - Tipo di trasporto (Standard, VIP, Privato)
  - Orario pranzo (Primo turno, Secondo turno)
  - Livello servizio (Base, Premium, Deluxe)

### 2. Quando Usare Checkbox
- **Opzioni indipendenti**: Quando l'utente può scegliere multipli addon senza restrizioni
- Esempi comuni:
  - Servizi aggiuntivi (Audio guida, Fotografia, Souvenir)
  - Pasti e bevande (Pranzo, Cena, Vino)
  - Attrezzatura (Casco, Giacca, Guanti)

### 3. Come Organizzare i Gruppi
- **Usa nomi chiari e descrittivi**: "Trasporto", "Pasti", "Servizi Extra"
- **Raggruppa opzioni correlate**: Tutti gli addon di trasporto insieme, tutti i servizi insieme
- **Non mischiare radio e checkbox nello stesso gruppo**: Se usi radio per "Trasporto", tutti gli addon di trasporto dovrebbero essere radio

### 4. Esperienza Utente
- **Limita il numero di gruppi**: Troppi gruppi possono confondere l'utente
- **Usa prezzi coerenti**: Se usi il gruppo "Trasporto", assicurati che le opzioni abbiano senso insieme
- **Descrizioni chiare**: Usa il campo descrizione per spiegare cosa include ogni addon

## Retrocompatibilità

Gli addon esistenti senza i nuovi campi:
- Verranno visualizzati automaticamente come **checkbox** (comportamento predefinito)
- Non avranno un gruppo specifico e appariranno nella sezione "Extra" predefinita
- Continueranno a funzionare normalmente

## File Modificati

### Backend (Admin)
- `/workspace/src/Admin/ExperienceMetaBoxes.php`
- `/workspace/build/fp-experiences/src/Admin/ExperienceMetaBoxes.php`

### Frontend (Template)
- `/workspace/templates/front/widget.php`
- `/workspace/templates/front/experience.php`
- `/workspace/build/fp-experiences/templates/front/widget.php`
- `/workspace/build/fp-experiences/templates/front/experience.php`

### Stili
- `/workspace/assets/css/front.css`
- `/workspace/build/fp-experiences/assets/css/front.css`

## Note Tecniche

### Struttura Dati Addon

Ogni addon ora include:

```php
[
    'name' => 'Nome addon',
    'slug' => 'slug-addon',
    'price' => 15.00,
    'type' => 'person', // o 'booking'
    'description' => 'Descrizione opzionale',
    'image_id' => 123,
    'selection_type' => 'checkbox', // o 'radio'
    'selection_group' => 'Nome Gruppo' // opzionale
]
```

### Rendering nel Template

Gli addon vengono automaticamente raggruppati in base al campo `selection_group`:

```php
// Raggruppamento automatico
$grouped_addons = [];
foreach ($addons as $addon) {
    $group = $addon['selection_group'] ?: '__default__';
    $grouped_addons[$group][] = $addon;
}

// Rendering per gruppo
foreach ($grouped_addons as $group_name => $group_addons) {
    // Render fieldset se gruppo nominato
    // Render addon con radio o checkbox
}
```

### Radio Button Name Attribute

Per i radio button dello stesso gruppo viene generato automaticamente un attributo `name` univoco basato sul nome del gruppo:

```html
<!-- Esempio per gruppo "Trasporto" -->
<input type="radio" name="addon_trasporto" value="transfer-standard" />
<input type="radio" name="addon_trasporto" value="transfer-vip" />
<input type="radio" name="addon_trasporto" value="transfer-privato" />
```

Questo garantisce che solo una opzione possa essere selezionata per gruppo.

## Supporto

Per domande o problemi relativi a questa funzionalità, consulta la documentazione principale del plugin o contatta il supporto tecnico.