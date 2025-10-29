# Fix per visualizzazione multipli addon nel frontend

## Problema identificato

Quando si aggiungono più di 1 addon nell'admin di un'esperienza, nel frontend viene visualizzato solo 1 addon.

## Causa del problema

Il problema era causato dalla gestione degli slug duplicati nel salvataggio degli addon. Se due o più addon avevano:
- Lo stesso nome (usato per generare automaticamente lo slug)
- Lo stesso slug inserito manualmente
- Uno slug vuoto che veniva generato dallo stesso nome

Allora gli addon successivi con slug duplicati venivano comunque salvati, ma quando venivano processati nel frontend, le query basate su slug univoci potevano causare conflitti.

## Soluzione implementata

Ho aggiunto una logica di de-duplicazione automatica degli slug in `src/Admin/ExperienceMetaBoxes.php`:

```php
// Ensure unique slug by appending index if needed
if ('' !== $slug) {
    $existing_slugs = array_column($legacy_addons, 'slug');
    if (in_array($slug, $existing_slugs, true)) {
        $slug = $slug . '-' . $index;
    }
}
```

### Cosa fa questa fix:

1. **Controllo degli slug esistenti**: Prima di salvare un addon, controlla se lo slug è già stato usato da un addon precedente nella stessa esperienza
2. **Generazione automatica di slug univoci**: Se uno slug è duplicato, aggiunge automaticamente un suffisso numerico basato sull'indice dell'addon (es. `transfer-1`, `transfer-2`)
3. **Compatibilità**: Non modifica gli slug già univoci, quindi gli addon esistenti non vengono alterati

## File modificati

1. `/workspace/src/Admin/ExperienceMetaBoxes.php` (linee 2481-2514)
2. `/workspace/build/fp-experiences/src/Admin/ExperienceMetaBoxes.php` (stesso cambio per build)

## Come testare

1. Accedi all'admin di WordPress
2. Crea o modifica un'esperienza
3. Vai alla tab "Biglietti & Prezzi"
4. Nella sezione "Extra", aggiungi 2 o più addon:
   - Addon 1: Nome "Transfer", Codice "transfer", Prezzo "15"
   - Addon 2: Nome "Audio guida", Codice "audio-guida", Prezzo "5"
   - Addon 3: Nome "Transfer VIP", Codice "transfer" (stesso codice dell'Addon 1), Prezzo "30"
5. Salva l'esperienza
6. Visualizza l'esperienza nel frontend
7. Nel widget di prenotazione, verifica che TUTTI gli addon siano visibili nella sezione "Extra"

## Comportamento atteso

- Se inserisci slug duplicati manualmente, il sistema li renderà automaticamente univoci
- Tutti gli addon con un nome valido verranno salvati e visualizzati
- Gli addon appariranno tutti nella lista del frontend

## Note aggiuntive

- Questa fix previene anche problemi futuri con JavaScript che usano selettori basati su `data-addon="${slug}"`
- Il suffisso numerico viene aggiunto solo quando necessario
- Gli slug esistenti univoci non vengono modificati