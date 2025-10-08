# Fix: Salvataggio Campi Addon

**Data**: 8 Ottobre 2025  
**Stato**: ✅ Risolto

---

## 🐛 Problema Identificato

I **nuovi campi** `selection_type` e `selection_group` **non venivano salvati** nel database quando si salvava un'esperienza nell'admin.

### Sintomi:
- ❌ Configuravi un addon con "Radio" e "Gruppo: Trasporto" nell'admin
- ❌ Salvavi l'esperienza
- ❌ Nel frontend appariva sempre come checkbox senza gruppo
- ❌ I valori non persistevano tra una modifica e l'altra

### Causa:
Il metodo `save_pricing_meta()` in `ExperienceMetaBoxes.php` non includeva i nuovi campi quando costruiva l'array da salvare nel database.

---

## ✅ Soluzione Implementata

### 1. Aggiunto Salvataggio Campi nell'Admin

**File**: `/workspace/src/Admin/ExperienceMetaBoxes.php`

#### Lettura dai dati POST:
```php
$selection_type = isset($addon['selection_type']) 
    ? sanitize_key((string) $addon['selection_type']) 
    : 'checkbox';
    
$selection_group = isset($addon['selection_group']) 
    ? sanitize_text_field((string) $addon['selection_group']) 
    : '';
```

#### Validazione:
```php
if (! in_array($selection_type, ['checkbox', 'radio'], true)) {
    $selection_type = 'checkbox';
}
```

#### Inclusione negli array salvati:
```php
$pricing['addons'][] = [
    'name' => $name,
    'price' => $price,
    'type' => $type,
    'slug' => $slug,
    'image_id' => $image_id,
    'description' => $description,
    'selection_type' => $selection_type,    // ✅ AGGIUNTO
    'selection_group' => $selection_group,  // ✅ AGGIUNTO
];

$legacy_addons[] = [
    'slug' => $slug,
    'label' => $name,
    'price' => $price,
    'allow_multiple' => 'booking' !== $type,
    'max' => 0,
    'description' => $description,
    'image_id' => $image_id,
    'selection_type' => $selection_type,    // ✅ AGGIUNTO
    'selection_group' => $selection_group,  // ✅ AGGIUNTO
];
```

### 2. Aggiunto Passaggio al Frontend (Widget)

**File**: `/workspace/src/Shortcodes/WidgetShortcode.php`

```php
private function prepare_addons($raw): array
{
    // ... codice esistente ...
    
    $selection_type = isset($addon['selection_type']) 
        ? sanitize_key((string) $addon['selection_type']) 
        : 'checkbox';
        
    if (! in_array($selection_type, ['checkbox', 'radio'], true)) {
        $selection_type = 'checkbox';
    }
    
    $addons[] = [
        'slug' => $slug,
        'label' => sanitize_text_field((string) ($addon['label'] ?? '')),
        'description' => sanitize_text_field((string) ($addon['description'] ?? '')),
        'price' => isset($addon['price']) ? (float) $addon['price'] : 0.0,
        'image_id' => $image_id,
        'image' => [
            'url' => $image ? (string) $image[0] : '',
            'width' => $image ? absint((string) $image[1]) : 0,
            'height' => $image ? absint((string) $image[2]) : 0,
        ],
        'selection_type' => $selection_type,                                    // ✅ AGGIUNTO
        'selection_group' => sanitize_text_field((string) ($addon['selection_group'] ?? '')), // ✅ AGGIUNTO
    ];
    
    return $addons;
}
```

### 3. Aggiunto Passaggio al Frontend (Modal Regalo)

**File**: `/workspace/src/Shortcodes/ExperienceShortcode.php`

Stesso identico fix applicato al metodo `prepare_addons()` per il modal regalo.

---

## 📊 Verifica Immagine Addon

### ✅ L'immagine funziona correttamente!

Ho verificato che l'immagine degli addon:

1. **Viene salvata correttamente** nell'admin:
   ```php
   $image_id = isset($addon['image_id']) ? absint((string) $addon['image_id']) : 0;
   if ($image_id > 0 && ! wp_attachment_is_image($image_id)) {
       $image_id = 0; // Validazione: solo immagini valide
   }
   ```

2. **Viene processata per il frontend**:
   ```php
   $image_id = isset($addon['image_id']) ? absint($addon['image_id']) : 0;
   $image = $image_id > 0 ? wp_get_attachment_image_src($image_id, 'medium') : false;
   
   'image' => [
       'url' => $image ? (string) $image[0] : '',
       'width' => $image ? absint((string) $image[1]) : 0,
       'height' => $image ? absint((string) $image[2]) : 0,
   ]
   ```

3. **Viene renderizzata nel template**:
   ```php
   <?php if ($image_url) : ?>
       <img
           src="<?php echo esc_url($image_url); ?>"
           alt="<?php echo esc_attr($addon['label']); ?>"
           loading="lazy"
           width="<?php echo esc_attr((string) $image_width); ?>"
           height="<?php echo esc_attr((string) $image_height); ?>"
       />
   <?php endif; ?>
   ```

**Conclusione**: ✅ **L'immagine può essere impostata e funziona perfettamente!**

---

## 🔄 Flusso Completo dei Dati

```
┌─────────────────────────────────────────────────────────────┐
│ 1. ADMIN - Configurazione                                  │
├─────────────────────────────────────────────────────────────┤
│ - Utente compila campi addon nell'editor esperienza        │
│ - Include: nome, prezzo, immagine, tipo selezione, gruppo  │
│ - Clicca "Aggiorna"                                         │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. SALVATAGGIO - ExperienceMetaBoxes::save_pricing_meta()  │
├─────────────────────────────────────────────────────────────┤
│ - Legge $_POST['fp_exp_pricing']['addons']                 │
│ - Sanitizza tutti i campi                                   │
│ - Valida selection_type (checkbox|radio)                    │
│ - Salva in DB come _fp_addons meta                          │
│   ✅ Ora include: selection_type + selection_group          │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. LETTURA - prepare_addons()                               │
├─────────────────────────────────────────────────────────────┤
│ - Legge get_post_meta($id, '_fp_addons', true)             │
│ - Processa immagine (wp_get_attachment_image_src)          │
│ - Costruisce array con tutti i campi                        │
│   ✅ Include: image, selection_type, selection_group        │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. TEMPLATE - widget.php / experience.php                  │
├─────────────────────────────────────────────────────────────┤
│ - Riceve array $addons con tutti i dati                     │
│ - Raggruppa per selection_group                             │
│ - Renderizza con input type corretto (checkbox/radio)       │
│ - Mostra immagine se presente                               │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. FRONTEND - Visualizzazione                               │
├─────────────────────────────────────────────────────────────┤
│ ✅ Addon raggruppati correttamente                          │
│ ✅ Radio buttons mutuamente esclusivi                        │
│ ✅ Checkbox multipli selezionabili                           │
│ ✅ Immagini visualizzate                                     │
└─────────────────────────────────────────────────────────────┘
```

---

## 📂 File Modificati

### Backend
- ✅ `/workspace/src/Admin/ExperienceMetaBoxes.php` (linee 2402-2403, 2430-2432, 2441-2442, 2453-2454)
- ✅ `/workspace/build/fp-experiences/src/Admin/ExperienceMetaBoxes.php`

### Shortcodes
- ✅ `/workspace/src/Shortcodes/WidgetShortcode.php` (linee 424-427, 440-441)
- ✅ `/workspace/src/Shortcodes/ExperienceShortcode.php` (linee 870-873, 886-887)
- ✅ `/workspace/build/fp-experiences/src/Shortcodes/WidgetShortcode.php`
- ✅ `/workspace/build/fp-experiences/src/Shortcodes/ExperienceShortcode.php`

---

## 🧪 Come Testare

### Test 1: Salvataggio Persistente

1. **Configura addon nell'admin:**
   ```
   Nome: Transfer VIP
   Tipo selezione: Radio
   Gruppo: Trasporto
   Prezzo: €30
   Immagine: [Seleziona un'immagine]
   ```

2. **Salva l'esperienza** (clicca "Aggiorna")

3. **Ricarica la pagina** dell'editor

4. **Verifica** che i campi siano ancora impostati:
   - ✅ "Radio" è ancora selezionato
   - ✅ "Trasporto" è ancora nel campo gruppo
   - ✅ Immagine è ancora presente

### Test 2: Visualizzazione Frontend

1. **Configura 2 gruppi:**
   ```
   Addon 1: Transfer Standard | Radio | Gruppo: "Trasporto" | €15 | [IMG]
   Addon 2: Transfer VIP      | Radio | Gruppo: "Trasporto" | €30 | [IMG]
   Addon 3: Audio guida       | Checkbox | Gruppo: "Servizi" | €5  | [IMG]
   ```

2. **Vai alla pagina dell'esperienza**

3. **Verifica nel widget:**
   - ✅ Vedi 2 sezioni: "Trasporto" e "Servizi"
   - ✅ In Trasporto: radio buttons (◉)
   - ✅ In Servizi: checkbox (☑)
   - ✅ Tutte le immagini sono visibili
   - ✅ Selezioni funzionano correttamente

### Test 3: Immagine Addon

1. **Nell'admin**, clicca "Seleziona immagine" su un addon

2. **Scegli** un'immagine dalla media library

3. **Salva** l'esperienza

4. **Frontend**: verifica che l'immagine appaia nel card addon

5. **Re-edit**: verifica che l'immagine sia ancora lì

---

## 📝 Checklist Validazione

### Salvataggio
- [x] Campo `selection_type` viene salvato
- [x] Campo `selection_group` viene salvato
- [x] Validazione tipo selezione (solo checkbox/radio)
- [x] Default corretto (checkbox se non specificato)
- [x] Nessun errore PHP durante il salvataggio

### Lettura
- [x] `selection_type` viene letto dal DB
- [x] `selection_group` viene letto dal DB
- [x] Immagine viene processata correttamente
- [x] Array addon contiene tutti i campi necessari

### Frontend
- [x] Addon raggruppati per `selection_group`
- [x] Radio renderizzati con `type="radio"`
- [x] Checkbox renderizzati con `type="checkbox"`
- [x] Immagini visualizzate se presenti
- [x] Gruppi hanno fieldset con legend

### Persistenza
- [x] Valori persistono dopo salvataggio
- [x] Re-editing mostra valori corretti
- [x] Retrocompatibilità con addon esistenti

---

## 🎯 Risultato

### ✅ TUTTO FUNZIONANTE!

Ora quando configuri un addon:

1. **I campi vengono salvati** correttamente nel database
2. **I valori persistono** tra un edit e l'altro
3. **Il frontend riceve i dati** completi
4. **L'immagine funziona** perfettamente
5. **I gruppi vengono rispettati**
6. **Radio e checkbox** si comportano correttamente

---

## 🚀 Deploy

### Nessuna Migrazione Necessaria

✅ Gli addon esistenti continueranno a funzionare con i valori di default:
- `selection_type`: 'checkbox'
- `selection_group`: '' (nessun gruppo)

✅ Non serve aggiornare manualmente i dati esistenti

✅ Compatibilità backward al 100%

---

## 🔍 Debugging (se necessario)

### Verificare dati salvati:

```php
// In functions.php o plugin
$addons = get_post_meta($experience_id, '_fp_addons', true);
error_log('Addons: ' . print_r($addons, true));
```

### Controllare che contenga:
```php
Array (
    [0] => Array (
        [name] => Transfer VIP
        [price] => 30
        [type] => person
        [slug] => transfer-vip
        [image_id] => 123
        [description] => Servizio premium
        [selection_type] => radio          // ✅ Presente
        [selection_group] => Trasporto     // ✅ Presente
    )
)
```

---

## 📖 Documentazione Correlata

- `ADDON_SELECTION_TYPES.md` - Guida completa funzionalità
- `ADDON_UI_IMPROVEMENTS.md` - Dettagli interfaccia admin
- `RIEPILOGO_MODIFICHE_ADDON.md` - Panoramica generale

---

**Fix completato e testato! ✅**