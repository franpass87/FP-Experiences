# Fix per visualizzazione addon mancanti nel frontend

## Problema identificato

Gli addon con nome valido ma slug vuoto (o viceversa) venivano skippati durante il salvataggio, anche se teoricamente uno dei due campi era presente. Questo causava la visualizzazione di meno addon nel frontend rispetto a quanti ne erano stati configurati nell'admin.

## Causa del problema

Nel file `src/Admin/ExperienceMetaBoxes.php` alla **riga 2516**, c'era un errore logico:

```php
// Skip only if both name and slug are empty
if ('' === $name || '' === $slug) {  // ❌ BUG: usa OR invece di AND
    continue;
}
```

Il commento diceva "Skip only if **both** name and slug are empty" (salta solo se ENTRAMBI sono vuoti), ma il codice usava l'operatore **OR** (`||`) invece dell'operatore **AND** (`&&`).

### Conseguenza del bug:

- Se il **nome** era vuoto **O** lo **slug** era vuoto → l'addon veniva saltato
- Invece dovrebbe essere: se il **nome** è vuoto **E** lo **slug** è vuoto → salta l'addon

Questo significa che:
- Un addon con nome ma senza slug veniva saltato (anche se lo slug poteva essere generato dal nome)
- Un addon con slug ma senza nome veniva saltato

## Soluzione implementata

Ho corretto l'operatore logico da **OR** a **AND**:

```php
// Skip only if both name and slug are empty
if ('' === $name && '' === $slug) {  // ✅ CORRETTO: usa AND
    continue;
}
```

Ora l'addon viene skippato **solo se entrambi** i campi sono vuoti, come indicato dal commento.

## File modificati

1. `/workspace/src/Admin/ExperienceMetaBoxes.php` (riga 2516)
2. `/workspace/build/fp-experiences/src/Admin/ExperienceMetaBoxes.php` (riga 2516)

## Come testare la correzione

1. Accedi all'admin di WordPress
2. Modifica un'esperienza esistente
3. Vai alla tab "Biglietti & Prezzi"
4. Nella sezione "Extra", aggiungi 2 o più addon con dati validi:
   - Addon 1: Nome "Transfer", Codice "transfer", Prezzo "15"
   - Addon 2: Nome "Audio guida", Codice "audio-guida", Prezzo "5"
5. Salva l'esperienza
6. Visualizza l'esperienza nel frontend
7. Nel widget di prenotazione, verifica che **TUTTI** gli addon configurati siano visibili nella sezione "Extra"

## Comportamento atteso dopo la fix

- Tutti gli addon con un nome valido vengono salvati e visualizzati
- Gli addon con nome ma senza slug ottengono uno slug generato automaticamente dal nome
- Solo gli addon completamente vuoti (senza nome E senza slug) vengono ignorati
- Tutti gli addon validi appaiono nella lista del frontend

## Note tecniche

La logica completa del salvataggio addon è:

1. Se lo slug è vuoto e il nome non è vuoto → genera lo slug dal nome (riga 2503)
2. Se lo slug non è vuoto e già esiste → aggiungi suffisso numerico per renderlo unico (righe 2508-2513)
3. Skippa l'addon solo se **ENTRAMBI** nome e slug sono vuoti (riga 2516) ✅ **CORRETTO**

## Data fix

8 Ottobre 2025