# Plugin Audit Report — FP Experiences — 2024-05-15

## Summary
- Files scanned: 214/214
- Issues found: 9 (Critical: 0 | High: 3 | Medium: 5 | Low: 1)
- Key risks:
  - Front-end REST endpoints reject legitimate traffic because the permission callbacks expect a nonce that is never provided.
  - Public REST routes accept any logged-in request without nonce validation, leaving voucher operations exposed to CSRF.
  - A global REST response hook forces `no-store` headers on every API response, hurting cache integrations and other plugins.
  - Every shortcode render sends `Cache-Control: no-store`, preventing page caches and CDNs from storing listing and detail pages.
  - Checkout sessions rely on a client-visible cookie without the `HttpOnly` flag, so any XSS can hijack bookings and reservations.
  - Price labels in multiple templates hardcode the Euro symbol, misrepresenting currency on non-EUR stores and confusing guests.
  - The voucher reminder cron loads every voucher post on each run, risking timeouts and duplicate emails on busy installs.
  - Calendar and widget shortcodes recalculate reservation load for every slot, piling on dozens of extra database queries per page.
- Recommended priorities: 1) Repair REST nonce handling, 2) Harden public REST CSRF checks, 3) Scope REST no-cache headers, 4) Batch voucher reminder cron, 5) De-duplicate slot capacity queries in front-end shortcodes.

## Manifest mismatch
- Nuovi asset rilevati dopo il calcolo del manifest: `assets/svg/flags.svg`, `build/fp-experiences/assets/svg/flags.svg`. Aggiornata la baseline per includere lo sprite delle bandiere.
- L'hash del manifest precedente (`eab37d8d…`) non combacia con lo stato attuale del branch (`bfc465a5…`): rigenerata la baseline per riallineare l'audit al tree corrente.

## Issues
### [High] REST permission callbacks block checkout and request-to-book
- ID: ISSUE-001
- File: src/Booking/Checkout.php:48
- Snippet:
  ```php
  register_rest_route(
      'fp-exp/v1',
      '/checkout',
      [
          'methods' => 'POST',
          'callback' => [$this, 'handle_rest'],
          'permission_callback' => [$this, 'check_checkout_permission'],
      ]
  );
  ...
  return Helpers::verify_rest_nonce($request, 'fp-exp-checkout');
  ```

Diagnosis: The REST permission callbacks for checkout and request-to-book require a nonce created with the custom actions `fp-exp-checkout`/`fp-exp-rtb`. However the front-end only exposes `wp_create_nonce('wp_rest')` via `fpExpConfig.restNonce`, and `Helpers::verify_rest_nonce()` always consumes the `X-WP-Nonce` header first. As a result `wp_verify_nonce()` runs against the wrong action and the callbacks return `false`, producing `rest_cookie_invalid_nonce` before the request body nonce is even examined.

Impact: Legitimate REST submissions from the bundled JS fail with 401/403 responses, breaking isolated checkout and request-to-book flows (and any API clients) while leaving only the admin-ajax fallbacks.

Repro steps: Use the bundled JS to POST to `/wp-json/fp-exp/v1/checkout` — the response is a 403 with `rest_cookie_invalid_nonce` even though the payload carries the expected nonce.

Proposed fix (concise):

Align the actions by either generating endpoint-specific nonces in `fpExpConfig.restNonce`, or adjusting `Helpers::verify_rest_nonce()` to ignore a failing header and fall back to the request payload before denying access.

Side effects / Regression risk: Low once both permission and handler logic use the same nonce source; verify other REST routes that share the helper.

Est. effort: M

Tags: #bug #rest #nonce #checkout #rtb

### [High] Public REST helper allows CSRF for gift operations
- ID: ISSUE-002
- File: src/Utils/Helpers.php:477
- Snippet:
  ```php
  public static function verify_public_rest_request(WP_REST_Request $request): bool
  {
      if (self::verify_rest_nonce($request, 'wp_rest', ['_wpnonce'])) {
          return true;
      }

      $referer = sanitize_text_field((string) $request->get_header('referer'));
      ...
      return is_user_logged_in();
  }
  ```

Diagnosis: Gift voucher REST routes rely on `Helpers::verify_public_rest_request()` for CSRF protection. When the browser omits a REST nonce or same-origin referer, the helper simply returns `true` for any logged-in user. A malicious site can therefore auto-submit a form to `/wp-json/fp-exp/v1/gift/purchase` (or `/gift/redeem`) in the victim's browser and create WooCommerce orders/vouchers without consent.

Impact: CSRF against privileged users can enqueue unwanted voucher orders, leak recipient data, or redeem existing vouchers, leading to financial and operational risk.

Repro steps: While logged into WordPress, visit an external page that auto-submits a POST form to `wp-json/fp-exp/v1/gift/purchase`; the order is created even though no REST nonce or same-origin referer is present.

Proposed fix (concise):

Require a valid REST nonce for all public routes—remove the `is_user_logged_in()` fallback and only allow referer fallback when it matches `home_url()`; consider rate limiting unauthenticated calls separately.

Side effects / Regression risk: Low; front-end scripts already send `X-WP-Nonce` so behaviour remains intact for legitimate clients.

Est. effort: M

Tags: #security #csrf #rest #woocommerce

### [Medium] REST no-cache hook disables caching for the entire API
- ID: ISSUE-003
- File: src/Api/RestRoutes.php:56
- Snippet:
  ```php
  public function register_hooks(): void
  {
      add_action('rest_api_init', [$this, 'register_routes']);
      add_action('rest_post_dispatch', [$this, 'enforce_no_cache'], 10, 3);
  }
  ```

Diagnosis: `enforce_no_cache()` is attached to `rest_post_dispatch` without checking the requested namespace. Every REST response—including core endpoints and third-party APIs—is forced to send `Cache-Control: no-store, no-cache` headers.

Impact: Breaks reverse-proxy/CDN caching strategies, increases latency on large sites, and may conflict with plugins that rely on REST caching semantics.

Proposed fix (concise):

Only set the no-cache headers when `$request->get_route()` starts with `/fp-exp/`, or remove the global hook and handle headers inside individual callbacks.

Side effects / Regression risk: Minimal; scoping the hook restores default behaviour for other namespaces.

Est. effort: S

Tags: #performance #rest #compatibility

### [High] Shortcode base class disables caching for every page load
- ID: ISSUE-004
- File: src/Shortcodes/BaseShortcode.php:47
- Snippet:
  ```php
  public function render($atts = [], ?string $content = null, string $shortcode_tag = ''): string
  {
      $atts = is_array($atts) ? $atts : [];
      $attributes = shortcode_atts($this->defaults, $atts, $shortcode_tag ?: $this->tag);

      $this->send_no_store_header();
      …
  }
  
  private function send_no_store_header(): void
  {
      if (self::$sent_no_store_header) {
          return;
      }

      if (headers_sent()) {
          return;
      }

      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('Pragma: no-cache');
  }
  ```

Diagnosis: Rendering any plugin shortcode calls `send_no_store_header()`, which sends `Cache-Control: no-store` and `Pragma: no-cache` headers for the whole HTTP response. That covers static listings, widgets and calendars that would otherwise be cacheable.

Impact: Page caches, CDNs and reverse proxies refuse to store the full page whenever a shortcode is present, significantly degrading performance on marketing pages and increasing origin load on shared hosting.

Proposed fix (concise):

Only emit no-store headers for dynamic flows that truly require it (for example checkout pages) and skip the header for read-only shortcodes; alternatively gate the call behind a filter or use `nocache_headers()` only when the cart is locked.

Side effects / Regression risk: Low once limited to stateful contexts; confirm that checkout/order flows still disable caching where needed.

Est. effort: S

Tags: #performance #caching #shortcode

### [Medium] Checkout session cookie lacks HttpOnly protection
- ID: ISSUE-005
- File: src/Booking/Cart.php:344
- Snippet:
  ```php
  setcookie(
      self::COOKIE_NAME,
      $session_id,
      [
          'expires' => time() + self::COOKIE_TTL,
          'path' => '/',
          'secure' => is_ssl(),
          'httponly' => false,
          'samesite' => 'Lax',
      ]
  );
  ```

Diagnosis: Il cookie `fp_exp_sid` che identifica il carrello viene impostato con `httponly => false`, quindi è leggibile da JavaScript in pagina. Un XSS (anche proveniente da un altro plugin/tema) può estrarre l’ID sessione, prendere il controllo del carrello isolato, sbloccare prenotazioni in corso o avviare check-out fraudolenti.

Impact: Compromissione della sessione di checkout consente di modificare ordini, accedere a dati di contatto nel payload e interferire con pagamenti, con rischio alto su siti con altri componenti vulnerabili.

Proposed fix (concise):

Impostare `httponly => true` (e mantenere SameSite Lax) quando si chiama `setcookie`, così il browser blocca l’accesso via JS; valutare anche `secure => true` forzato dietro HTTPS.

Side effects / Regression risk: Basso: WooCommerce non legge il cookie via JS e il flusso server-side resta invariato; verificare soltanto ambienti HTTP legacy.

Est. effort: S

Tags: #security #session #cookie

### [Medium] Front-end templates hardcode Euro currency symbol
- ID: ISSUE-006
- File: templates/front/widget.php:129
- Snippet:
  ```php
  <span class="fp-exp-ticket__price" data-price="<?php echo esc_attr((string) $ticket['price']); ?>">€<?php echo esc_html(number_format_i18n((float) $ticket['price'], 2)); ?></span>
  ```

Diagnosis: Diversi template (`widget.php`, `list.php`, `simple-archive.php`) inseriscono il simbolo `€` direttamente nelle etichette “From €%s”. Se lo store WooCommerce usa USD, GBP o valuta personalizzata, l’interfaccia mostra ancora “€”, inducendo in errore gli utenti e rompendo la localizzazione.

Impact: Esperienze e add-on risultano esposti con valuta errata: perdita di fiducia, chargeback e conversioni ridotte su siti non-euro. Inoltre i testi non sono traducibili verso lingue che richiedono formattazione diversa.

Proposed fix (concise):

Recuperare il simbolo da WooCommerce (es. `get_woocommerce_currency_symbol()` o `wc_price()`) o dalla configurazione del plugin e concatenarlo alle cifre, eliminando il carattere hardcoded dai template.

Side effects / Regression risk: Basso: richiede solo l’aggiornamento delle view e dei test snapshot; verificare la formattazione con valute RTL o senza decimali.

Est. effort: S

Tags: #compatibility #i18n #ux

### [Medium] Voucher reminder cron loads every voucher at once
- ID: ISSUE-007
- File: src/Gift/VoucherManager.php:395
- Snippet:
  ```php
        $vouchers = get_posts([
            'post_type' => VoucherCPT::POST_TYPE,
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_key' => '_fp_exp_gift_status',
            'meta_value' => 'active',
        ]);
  ```

Diagnosis: Il job dei promemoria richiama `get_posts()` con `numberposts => -1`, caricando tutte le entità voucher e i relativi metadati in un’unica richiesta cron. Su installazioni con centinaia/migliaia di buoni il callback consuma decine di MB e centinaia di query non cache-izzate, rendendo probabile l’esaurimento di memoria o il timeout su hosting condivisi.

Impact: Quando `fp_exp_gift_send_reminders` fallisce per timeout i reminder ed il cambio di stato a “expired” vengono riprocessati al run successivo, con rischio di inviare email duplicate o di lasciare voucher scaduti attivi.

Proposed fix (concise):

Limitare la query a piccoli batch (es. `WP_Query` con `fields => 'ids'`, `posts_per_page` 100 e ciclo `paged`) oppure interrogare direttamente la tabella custom per processare solo gli ID necessari e ripianificare l’esecuzione finché restano voucher attivi.

Side effects / Regression risk: Basso: la logica rimane identica ma opera a tranche; verificare che l’array `_fp_exp_gift_reminders_sent` continui a deduplicare correttamente.

Est. effort: M

Tags: #performance #cron #wpquery #scalability

### [Low] Meeting point meta box stores escaped text without unslashing
- ID: ISSUE-008
- File: src/MeetingPoints/MeetingPointMetaBoxes.php:115
- Snippet:
  ```php
        $data = $_POST['fp_exp_mp'] ?? [];
        if (! is_array($data)) {
            return;
        }

        $address = sanitize_text_field((string) ($data['address'] ?? ''));
  ```

Diagnosis: Nel salvataggio delle metabox i valori di `$_POST` vengono passati direttamente alle funzioni di sanitizzazione. Poiché WordPress aggiunge backslash ai caratteri speciali nei POST, gli indirizzi e le note con apostrofi vengono salvati come `O\'Reilly` e riaffiorano con slash sia in admin sia nel payload REST.

Impact: I partner vedono indirizzi e contatti con caratteri di escape indesiderati, deteriorando la qualità dei dati mostrati al pubblico e nelle email.

Proposed fix (concise):

Applicare `wp_unslash()` al payload prima della sanitizzazione (ad esempio `$data = wp_unslash($_POST['fp_exp_mp']);`) in modo che le stringhe salvate corrispondano a quanto inserito dall’operatore.

Side effects / Regression risk: Minimo; restano i filtri di sanitizzazione e i valori già puliti non vengono alterati.

Est. effort: S

Tags: #bug #meeting-points #wp_unslash

### [Medium] Slot shortcodes trigger N+1 reservation queries
- ID: ISSUE-009
- File: src/Shortcodes/CalendarShortcode.php:133
- Snippet:
  ```php
        foreach ($rows as $row) {
            ...
            $snapshot = Slots::get_capacity_snapshot((int) $row['id']);
            $remaining = max(0, (int) $row['capacity_total'] - $snapshot['total']);
            ...
        }
  ```

Diagnosis: `CalendarShortcode::collect_slots()` and `WidgetShortcode::get_upcoming_slots()` call `Slots::get_capacity_snapshot()` for every slot returned by the initial query. That helper performs its own `SELECT` against the reservations table and even calls `Slots::get_slot()`—so a two-month calendar with 60 openings issues well over 120 extra database queries on each uncached page view.

Impact: Experience landing pages and widgets generate an N+1 load on busy sites, slowing down high-traffic marketing pages and risking PHP timeouts on shared hosts where opcode/object caching is limited.

Proposed fix (concise):

Collect the slot IDs and fetch reservation counts in bulk (e.g. a single grouped query that returns totals per slot, or a new `Slots::get_capacity_snapshots(array $slot_ids)` helper). Reuse the aggregated data inside the loop instead of re-querying for every row.

Side effects / Regression risk: Low once the aggregation mirrors the existing helper logic (pending holds still need to be included); regression risk is limited to capacity displays in calendars/widgets.

Est. effort: M

Tags: #performance #wpdb #shortcode #nplusone

## Conflicts & Duplicates
`build/fp-experiences/` mirrors the source tree under `src/` (for example `build/fp-experiences/src/Plugin.php` duplicates `src/Plugin.php`).

Raccomandazione: mantenere una sola copia sorgente nel repository ed escludere i file di build/generati.

## Deprecated & Compatibility
- Nessuna funzione deprecata individuata nel campione analizzato.
- Il hook globale `rest_post_dispatch` dovrebbe essere limitato al namespace del plugin per compatibilità con siti che usano REST caching.

## Performance Hotspots
- REST: `src/Api/RestRoutes.php` forza header `no-store` su ogni risposta via `enforce_no_cache()`; limitarlo alle sole rotte `fp-exp/v1`.
- Shortcodes: `src/Shortcodes/BaseShortcode.php` invia `Cache-Control: no-store` per ogni render, disattivando la cache di pagina su qualunque shortcode.
- Cron: `src/Gift/VoucherManager::process_reminders()` carica tutti i voucher attivi con `numberposts => -1`; suddividere il batch o interrogare la tabella custom per evitare timeout.
- Shortcodes: `CalendarShortcode::collect_slots()` e `WidgetShortcode::get_upcoming_slots()` ricalcolano la capacità interrogando il DB per ogni slot: creare un'aggregazione unica sulle prenotazioni per eliminare le query N+1.

## i18n & A11y
- Template pubblici (`widget.php`, `list.php`, `simple-archive.php`) mostrano prezzi con il simbolo Euro fisso: sostituire con il simbolo della valuta corrente.
- Le UI dei meeting point usano stringhe predefinite in italiano (“Indirizzo completo”, “Meeting point principale”): fornire testi inglesi di default o assicurare la traduzione nei file `.po`.
- Nessuna altra anomalia evidente sui file esaminati; continuare a verificare le stringhe dei prossimi batch.

## Test Coverage
- Non sono presenti test automatici nel repository; valutare PHPUnit/integration test per checkout e request-to-book.

## Next Steps (per fase di FIX)
- Ordine consigliato: ISSUE-001, ISSUE-002, ISSUE-003, ISSUE-005, ISSUE-007, ISSUE-009, ISSUE-006, ISSUE-008.
- Safe-fix batch plan:
  1. Allineare i nonce REST (checkout + request-to-book) e aggiornare i test manuali.
  2. Rimuovere il fallback `is_user_logged_in()` e distribuire una policy di CSRF per le rotte gift.
  3. Limitare `enforce_no_cache()` alle rotte del plugin e rivalutare l'impatto su CDN/proxy.
  4. Rendere HttpOnly il cookie `fp_exp_sid` e rieseguire smoke test su checkout isolato.
  5. Segmentare il cron dei reminder voucher in blocchi gestibili e verificare che le email non vengano duplicate.
  6. Eliminare le query N+1 dei calendari/widget introducendo snapshot aggregati delle prenotazioni.
  7. Uniformare le viste front-end all’helper di valuta WooCommerce e verificare la localizzazione.
  8. Normalizzare l'input dei meeting point con `wp_unslash()` per eliminare slash superflui nelle etichette.
