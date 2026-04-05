# FP Experiences - Modular Architecture

## Struttura Modulare

### JavaScript Modules

#### Admin Modules
- `core.js` - Funzioni di base e utilità
- `tabs.js` - Gestione tab
- `media-controls.js` - Controlli media
- `gallery-controls.js` - Controlli galleria
- `taxonomy-editors.js` - Editor tassonomie
- `repeaters.js` - Componenti ripetibili
- `form-validation.js` - Validazione form
- `calendar.js` - Componenti calendario
- `tools.js` - Strumenti vari

#### Frontend Modules
- `checkout.js` - Processo checkout
- `importer.js` - Importazione dati

### CSS Modules

#### Admin CSS
- `variables.css` - Variabili CSS e stili base
- `layout.css` - Layout e struttura
- `tabs.css` - Componenti tab
- `forms.css` - Stili form
- `media.css` - Controlli media
- `taxonomy.css` - Editor tassonomie
- `repeaters.css` - Componenti ripetibili
- `calendar.css` - Calendario
- `settings.css` - Impostazioni
- `buttons.css` - Bottoni e azioni

#### Frontend CSS
- `variables.css` - Variabili CSS
- `buttons.css` - Bottoni
- `cards.css` - Card
- `listing.css` - Listing

## Utilizzo

### Caricamento Modulare
```javascript
// Carica solo i moduli necessari
const loader = new FpExperiencesLoader();
await loader.loadPageModules('admin');
```

### Caricamento Tradizionale
```html
<!-- Carica tutto -->
<link rel="stylesheet" href="assets/css/dist/fp-experiences.min.css">
<script src="assets/js/dist/fp-experiences.min.js"></script>
```

## Vantaggi

1. **Performance**: Caricamento solo dei moduli necessari
2. **Manutenibilità**: Codice organizzato in moduli logici
3. **Debugging**: Più facile identificare e correggere problemi
4. **Scalabilità**: Facile aggiungere nuovi moduli
5. **Cache**: Migliore gestione della cache del browser
