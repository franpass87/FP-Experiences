# Problema: Aggiornamenti non visibili sul sito

## Analisi del Problema

### Situazione Attuale

Il problema si manifesta quando vengono fatti aggiornamenti ai file del plugin, ma le modifiche non sono immediatamente visibili sul sito frontend.

### Cause Identificate

#### 1. **Sistema di Asset Fallback** ✅ RISOLTO
- **Problema**: Il sistema preferisce file minificati anche se più vecchi dei sorgenti
- **Soluzione implementata**: Commit `38479c6` - Il metodo `Helpers::resolve_asset_rel()` ora confronta i timestamp (`filemtime`) e usa automaticamente i file sorgente se più recenti dei minificati
- **File interessati**: 
  - `src/Utils/Helpers.php` (righe 201-266)
  - `src/Shortcodes/Assets.php` (righe 94-102)

#### 2. **File CSS Minificati Mancanti** ⚠️ PARZIALE
- **Situazione**: La directory `assets/css/dist/` non esiste
- **Impatto**: Il sistema usa sempre i file CSS sorgente (positivo per vedere aggiornamenti)
- **Nota**: I file JS minificati esistono in `assets/js/dist/`

#### 3. **Cache del Browser** ⚠️ DA MIGLIORARE
- **Problema**: Il browser memorizza file CSS/JS con cache aggressiva
- **Soluzione attuale**: Il sistema usa `filemtime()` come versione query string (`?ver=1760197887`)
- **Limitazione**: Se i file vengono copiati (rsync, deploy) mantengono il timestamp originale

#### 4. **Cache di WordPress** ⚠️ DA VERIFICARE
- **Potenziali fonti**:
  - Plugin di cache (WP Super Cache, W3 Total Cache, ecc.)
  - Object cache (Redis, Memcached)
  - Transient cache
  - CDN cache (Cloudflare, ecc.)

#### 5. **Processo di Build e Deploy** ⚠️ DA MIGLIORARE
- **Situazione attuale**:
  - `build.sh` usa `rsync` che preserva i timestamp originali
  - I file minificati non vengono rigenerati automaticamente ad ogni build
- **Impatto**: Files vecchi possono essere deployati con timestamp che non riflettono l'ultimo aggiornamento

## Soluzioni Proposte

### Soluzione 1: Utilizzare la Versione del Plugin invece di filemtime() ⭐ RACCOMANDATO

**Vantaggi**:
- Garantisce cache busting ad ogni release
- Non dipende dai timestamp dei file
- Più prevedibile e affidabile

**Implementazione**:
```php
// In src/Utils/Helpers.php
public static function asset_version(string $relative_path): string
{
    // Usa sempre la versione del plugin per production
    if (defined('FP_EXP_VERSION')) {
        return FP_EXP_VERSION;
    }
    
    // Fallback a filemtime per development
    $absolute_path = trailingslashit(FP_EXP_PLUGIN_DIR) . ltrim($relative_path, '/');
    if (is_readable($absolute_path)) {
        $mtime = filemtime($absolute_path);
        if (false !== $mtime) {
            return (string) $mtime;
        }
    }
    
    return '1.0.0';
}
```

### Soluzione 2: Aggiungere un Parametro di Cache Busting Aggiuntivo

**Implementazione**:
```php
// In src/Shortcodes/Assets.php
wp_register_style(
    'fp-exp-front',
    $style_url,
    ['fp-exp-fontawesome'],
    Helpers::asset_version($css_rel) . '-' . md5_file(trailingslashit(FP_EXP_PLUGIN_DIR) . $css_rel)
);
```

### Soluzione 3: Rigenerare i Bundle Minificati ad Ogni Build

**Modifica a build.sh**:
```bash
# Dopo rsync, prima di creare lo ZIP
if command -v node &> /dev/null && [[ -f "$ROOT_DIR/build-optimize.js" ]]; then
    echo "Generating minified bundles..."
    node "$ROOT_DIR/build-optimize.js"
fi
```

### Soluzione 4: Funzione di Pulizia Cache

**Aggiungere in src/Admin/ToolsPage.php**:
```php
public function clear_all_plugin_cache(): void
{
    // Pulisci transient
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fp_exp_%' OR option_name LIKE '_transient_timeout_fp_exp_%'"
    );
    
    // Pulisci cache degli asset (se presente)
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Hook per plugin di cache esterni
    do_action('fp_exp_clear_cache');
}
```

### Soluzione 5: Header Cache-Control più Aggressivi

**Aggiungere filtro WordPress**:
```php
// In src/Plugin.php o in un nuovo file src/Utils/CacheHeaders.php
add_filter('style_loader_tag', function($html, $handle) {
    if (strpos($handle, 'fp-exp-') === 0) {
        // Forza no-cache per asset del plugin in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
    return $html;
}, 10, 2);
```

## Raccomandazioni Immediate

### Per il Deployment
1. ✅ Verificare che la versione del plugin sia incrementata ad ogni release
2. ✅ Assicurarsi che `build.sh` aggiorni la versione prima di creare lo ZIP
3. ⚠️ Considerare di rigenerare i bundle minificati durante il build
4. ⚠️ Verificare che il processo di deploy invalidi cache CDN/server

### Per lo Sviluppo
1. ✅ Usare sempre `WP_DEBUG = true` in development
2. ✅ Disabilitare cache del browser durante lo sviluppo (DevTools Network → Disable cache)
3. ⚠️ Considerare di usare sempre file non minificati in development

### Per il Monitoraggio
1. ⚠️ Aggiungere logging quando viene scelto un asset minificato vs sorgente
2. ⚠️ Creare un tool admin per verificare quale versione degli asset è caricata
3. ⚠️ Aggiungere un pulsante "Clear All Cache" nell'area Tools

## Test da Effettuare

1. **Test Cache Browser**:
   ```
   - Aprire DevTools → Network
   - Hard refresh (Ctrl+Shift+R)
   - Verificare header Cache-Control e ETag
   - Verificare query string ?ver=xxx
   ```

2. **Test Cache WordPress**:
   ```
   - Controllare plugin di cache installati
   - Pulire cache da WP Admin
   - Verificare se esiste object cache (Redis/Memcached)
   ```

3. **Test Asset Loading**:
   ```
   - View source della pagina
   - Cercare tag <link> e <script> con fp-exp
   - Verificare URL completi e versioni
   ```

4. **Test Timestamp**:
   ```bash
   # Verificare timestamp dei file dopo deploy
   stat -c "%Y %n" assets/js/front.js assets/js/dist/front.js
   ```

## Log delle Soluzioni Implementate

### 2025-10-11 - Fix Iniziale Cache Asset
- **Commit**: `38479c6` - Implementato sistema di fallback intelligente per asset
  - `Helpers::resolve_asset_rel()` confronta timestamp e usa file più recente
  - Fix applicato a `src/Shortcodes/Assets.php` per CSS e JS

### 2025-10-11 - Miglioramento Cache Busting ✅ COMPLETATO
- **Modifica**: `src/Utils/Helpers.php::asset_version()`
  - **Production**: Usa sempre `FP_EXP_VERSION` come versione degli asset
  - **Development** (WP_DEBUG=true): Aggiunge timestamp del file alla versione (es: `0.3.7.1760197887`)
  - **Beneficio**: Garantisce cache busting immediato ad ogni release del plugin
  - **Fallback**: Se la versione non è definita, usa il timestamp del file

- **Modifica**: `src/Utils/Helpers.php::clear_asset_version_cache()`
  - Nuovo metodo pubblico per pulire la cache in memoria delle versioni asset
  - Utilizzato dall'azione admin "Clear caches"

- **Potenziamento**: `src/Api/RestRoutes.php::tool_clear_cache()`
  - Aggiunta pulizia di tutti i transient del plugin (`_transient_fp_exp_*`)
  - Aggiunta pulizia object cache WordPress (`wp_cache_flush()`)
  - Aggiunta pulizia cache asset versioning
  - Report dettagliato del numero di transient eliminati
  - Mantiene rate limiting per evitare abusi

### Risultati Attesi

1. **In Production**:
   - Ogni volta che si incrementa la versione del plugin (es: da 0.3.7 a 0.3.8), tutti gli asset vengono automaticamente ricaricati
   - Non è più necessario fare clear cache manuale dopo gli aggiornamenti
   - Gli asset hanno URL tipo: `front.css?ver=0.3.8`

2. **In Development**:
   - Con `WP_DEBUG=true`, ogni modifica ai file asset genera un nuovo timestamp
   - Gli asset hanno URL tipo: `front.css?ver=0.3.7.1760197887`
   - Cache busting immediato senza bisogno di incrementare la versione

3. **Tool Admin "Clear Caches"**:
   - Ora pulisce effettivamente tutti i transient del plugin
   - Pulisce l'object cache di WordPress
   - Pulisce la cache in memoria delle versioni asset
   - Mostra quanti transient sono stati eliminati

### Come Verificare il Fix

1. **Verifica Versione Asset**:
   ```bash
   # View source della pagina e cercare:
   # <link ... href="...front.css?ver=0.3.7" ...>
   # In production la versione dovrebbe essere uguale a FP_EXP_VERSION
   ```

2. **Verifica Dopo Aggiornamento**:
   ```bash
   # Dopo aver incrementato la versione e deployato:
   # - Hard refresh (Ctrl+Shift+R)
   # - Verificare che ?ver= abbia il nuovo numero di versione
   # - Non dovrebbero esserci file cached con versione vecchia
   ```

3. **Test Clear Cache**:
   ```bash
   # Andare in FP Experiences → Strumenti
   # Cliccare "Clear caches"
   # Verificare il messaggio di successo con il numero di transient eliminati
   ```
