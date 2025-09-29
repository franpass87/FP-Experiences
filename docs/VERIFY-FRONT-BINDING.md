# Verifica front-end shortcode

## Problemi individuati
- Gli shortcode `[fp_exp_page]` e `[fp_exp_widget]` venivano registrati subito al bootstrap del plugin: in alcuni contesti WordPress la registrazione preventiva impediva ai builder di rilevare i tag dopo un salvataggio.
- Mancava un fallback sicuro per l'ID esperienza: senza attributo `id` i render restituivano errore senza tentare `get_the_ID()`, e non veniva loggata la condizione.
- I metadati elenco (highlights, inclusions, ecc.) venivano letti direttamente da `get_post_meta` senza normalizzazione, causando stringhe vuote o valori serialized quando l'editor salvava formati differenti.
- Il widget serializzava l'intero payload in `data-config` senza versione, quindi HTML già in cache non veniva invalidato.
- Gli asset front-end usavano una versione statica (`FP_EXP_VERSION`), lasciando CSS/JS in cache dopo un deploy.
- Il markup del meeting point mostrava sempre il link “Apri in Maps” anche quando mancavano coordinate/indirizzo.
- Nessun log di diagnostica aiutava a capire quali meta mancassero durante il render.
- Nessun invalidamento dei transients collegati all’esperienza al salvataggio del CPT.

## Fix applicati
- **`src/Shortcodes/Registrar.php`** – registrazione su `init` e flush mirato dei transients all’hook `save_post_fp_experience`.
- **`src/Shortcodes/BaseShortcode.php`** – header `Cache-Control: no-store` per ogni render, evitando cache stale.
- **`src/Shortcodes/ExperienceShortcode.php`** e **`src/Shortcodes/WidgetShortcode.php`** – fallback ID, normalizzazione meta tramite `Helpers::get_meta_array()`, log diagnostici e passaggio della versione configurazione al template.
- **`src/Utils/Helpers.php`** – nuovo helper `get_meta_array()`, toggle per il debug logging, invalidamento transients ed utility `log_debug()`.
- **`src/Shortcodes/Assets.php`** – versionamento dinamico CSS/JS basato su `filemtime`.
- **`templates/front/widget.php`** – serializzazione JSON con chiave `version` e attributo `data-config-version`.
- **`templates/front/partials/meeting-point.php`** – generazione sicura del link Maps e soppressione quando mancano dati.

## Da testare manualmente
1. Inserire `[fp_exp_page]` su una singola esperienza senza parametro `id`: verificare che carichi i dati e che il log non mostri errori.
2. Aggiornare highlights/inclusions e ricaricare la pagina: i cambiamenti devono comparire subito, senza cache.
3. Salvare una variazione prezzo biglietti/add-on: ricaricando la pagina il widget deve mostrare i nuovi valori e `data-config-version` deve variare.
4. Disabilitare/abilitare meeting point o rimuovere l’ID: la sezione deve sparire senza errori e senza link Maps vuoti.
5. Controllare i log strumenti → Tools: devono esistere voci `shortcodes` con meta mancanti solo quando effettivamente assenti.
