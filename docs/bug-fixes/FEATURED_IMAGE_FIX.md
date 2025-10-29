# Fix: Featured Image non visibile nella lista esperienze

## Problema
Le immagini in evidenza (featured images) non venivano visualizzate nella lista delle esperienze.

## Causa
Il codice nella classe `ListShortcode` utilizzava solamente `get_the_post_thumbnail_url()` per recuperare l'immagine, ma non aveva un fallback per:
1. L'immagine hero personalizzata (`_fp_hero_image_id`)
2. Le immagini della galleria (`_fp_gallery_ids`)

## Soluzione Implementata

### File modificato: `src/Shortcodes/ListShortcode.php`

1. **Aggiunta funzione `get_experience_thumbnail()`** (linee 1119-1153):
   - Controlla prima la featured image di WordPress
   - Fallback sulla hero image personalizzata
   - Fallback sulla prima immagine della galleria se disponibile
   - Restituisce stringa vuota se nessuna immagine è disponibile

2. **Aggiornato il metodo `map_experience()`** (linea 521):
   - Sostituito `get_the_post_thumbnail_url($id, 'large')` con `$this->get_experience_thumbnail($id)`

3. **Aggiunto import necessario** (linea 37):
   - Aggiunto `use function wp_get_attachment_image_url;`

## Dettagli Tecnici

La nuova funzione `get_experience_thumbnail()` segue questa logica a cascata:

```php
1. WordPress Featured Image → get_the_post_thumbnail_url()
   ↓ (se non disponibile)
2. Hero Image Meta → _fp_hero_image_id
   ↓ (se non disponibile)
3. Prima immagine Gallery → _fp_gallery_ids[0]
   ↓ (se non disponibile)
4. Stringa vuota → Verrà mostrato il placeholder CSS
```

## Benefici

- ✅ Compatibilità retroattiva: continua a usare la featured image come prima priorità
- ✅ Fallback intelligenti: utilizza hero image o gallery se la featured image non è impostata
- ✅ Nessun errore: gestisce tutti i casi edge con controlli appropriati
- ✅ Performance: mantiene la stessa efficienza con controlli condizionali rapidi

## File Aggiornati

- `/workspace/src/Shortcodes/ListShortcode.php`
- `/workspace/build/fp-experiences/src/Shortcodes/ListShortcode.php`

## Test Consigliati

1. Verificare che le esperienze con featured image mostrino correttamente l'immagine
2. Verificare che le esperienze senza featured image ma con hero image mostrino la hero image
3. Verificare che le esperienze con solo gallery images mostrino la prima immagine della galleria
4. Verificare che le esperienze senza immagini mostrino il placeholder

## Note

La sincronizzazione tra hero image e featured image avviene in `ExperienceMetaBoxes.php` (linee 2191-2197), ma questo fix garantisce che anche se la sincronizzazione fallisce, l'immagine verrà comunque visualizzata nella lista.