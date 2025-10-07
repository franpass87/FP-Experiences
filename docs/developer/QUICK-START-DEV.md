# 🚀 Quick Start - Guida Rapida Sviluppatori

Guida rapida per sviluppatori che vogliono contribuire o estendere FP Experiences.

---

## 📋 Setup Ambiente (5 min)

### Requisiti

```bash
# Verifica versioni
php -v     # >= 8.1
node -v    # >= 16.x
composer -v
```

### Clone e Installazione

```bash
# Clone repository
git clone https://github.com/your-repo/fp-experiences.git
cd fp-experiences

# Installa dipendenze
composer install
npm install
```

---

## 🏗️ Struttura Progetto

```
fp-experiences/
├── src/                    # Codice sorgente PHP
│   ├── Admin/             # Interfacce admin
│   ├── Api/               # REST API endpoints
│   ├── Booking/           # Sistema prenotazioni
│   ├── Front/             # Frontend rendering
│   ├── Integrations/      # Integrazioni esterne
│   └── ...
│
├── assets/                # Assets frontend
│   ├── js/
│   │   ├── admin.js      # JavaScript admin
│   │   └── front/        # Moduli frontend
│   │       ├── availability.js
│   │       ├── calendar.js
│   │       └── ...
│   ├── css/              # Stili
│   └── svg/              # Icone
│
├── templates/            # Template PHP
│   ├── single/          # Single experience
│   ├── archive/         # Archive listing
│   └── widgets/         # Widget templates
│
├── docs/                # Documentazione
│   ├── admin/          # Guide amministratori
│   ├── developer/      # Guide sviluppatori
│   └── technical/      # Documentazione tecnica
│
├── tests/              # Test suite
├── tools/              # Utility scripts
└── build/              # Build output (ignorato)
```

---

## 🔧 Workflow Sviluppo

### 1. Branch Strategy

```bash
# Feature branch
git checkout -b feature/my-amazing-feature

# Bugfix branch
git checkout -b fix/issue-123

# Hotfix branch
git checkout -b hotfix/critical-bug
```

### 2. Convenzioni Commit

```bash
# Format
<type>(<scope>): <subject>

# Esempi
feat(calendar): add slot filtering by experience
fix(booking): resolve checkout validation error
docs(readme): update installation guide
refactor(api): simplify availability service
test(slots): add unit tests for recurring slots
```

**Types:** `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

### 3. Code Style

**PHP (PSR-12):**
```php
<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

final class MyClass
{
    public function myMethod(string $param): bool
    {
        // Code here
        return true;
    }
}
```

**JavaScript (ESLint):**
```javascript
function myFunction(param) {
    // Use const/let, never var
    const result = doSomething(param);
    return result;
}
```

---

## 🎯 Quick Tasks

### Aggiungere un Nuovo Endpoint REST

**File:** `src/Api/RestRoutes.php`

```php
// 1. Registra route nel metodo register_routes()
register_rest_route(
    'fp-exp/v1',
    '/my-endpoint',
    [
        'methods' => 'GET',
        'permission_callback' => static function (): bool {
            return Helpers::can_operate_fp();
        },
        'callback' => [$this, 'my_endpoint_callback'],
        'args' => [
            'param' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]
);

// 2. Crea callback method
public function my_endpoint_callback(WP_REST_Request $request): WP_REST_Response
{
    $param = $request->get_param('param');
    
    // Your logic here
    $data = [
        'success' => true,
        'data' => $param,
    ];
    
    return rest_ensure_response($data);
}
```

**Test:**
```bash
curl http://localhost/wp-json/fp-exp/v1/my-endpoint?param=test
```

### Aggiungere un Hook WordPress

**Registra hook:**
```php
// src/MyClass.php
public function register_hooks(): void
{
    add_action('init', [$this, 'my_init_callback']);
    add_filter('the_content', [$this, 'modify_content']);
}

public function my_init_callback(): void
{
    // Runs on WordPress init
}

public function modify_content(string $content): string
{
    // Modify content
    return $content;
}
```

**Registra classe nel Plugin.php:**
```php
$my_class = new MyClass();
$my_class->register_hooks();
```

### Aggiungere un Modulo JavaScript Frontend

**File:** `assets/js/front/my-module.js`

```javascript
(function() {
    'use strict';
    
    // Verifica namespace FPFront
    if (!window.FPFront) {
        window.FPFront = {};
    }
    
    // API pubblica del modulo
    const publicAPI = {
        init: function(config) {
            console.log('My module initialized', config);
            // Init logic
        },
        
        myMethod: function(param) {
            // Your logic
            return param;
        }
    };
    
    // Esponi API
    window.FPFront.myModule = publicAPI;
    
    // Auto-init se configurato
    if (window.fpExpMyModuleConfig) {
        publicAPI.init(window.fpExpMyModuleConfig);
    }
})();
```

**Enqueue in PHP:**
```php
wp_enqueue_script(
    'fp-exp-my-module',
    plugins_url('assets/js/front/my-module.js', FP_EXP_PLUGIN_FILE),
    ['fp-exp-front-core'],
    FP_EXP_VERSION,
    true
);

// Pass config
wp_localize_script(
    'fp-exp-my-module',
    'fpExpMyModuleConfig',
    [
        'apiUrl' => rest_url('fp-exp/v1/'),
        'nonce' => wp_create_nonce('fp_exp_nonce'),
    ]
);
```

### Aggiungere un Meta Box Admin

**Registra meta box:**
```php
add_action('add_meta_boxes_fp_experience', function() {
    add_meta_box(
        'fp_exp_my_metabox',
        __('My Custom Meta Box', 'fp-experiences'),
        'render_my_metabox_callback',
        'fp_experience',
        'side',
        'default'
    );
});

function render_my_metabox_callback(WP_Post $post): void
{
    $value = get_post_meta($post->ID, '_fp_exp_my_field', true);
    ?>
    <label for="fp-exp-my-field">
        <?php esc_html_e('My Field', 'fp-experiences'); ?>
    </label>
    <input 
        type="text" 
        id="fp-exp-my-field" 
        name="fp_exp_my_field" 
        value="<?php echo esc_attr($value); ?>"
        class="widefat"
    />
    <?php
}
```

**Salva dato:**
```php
add_action('save_post_fp_experience', function($post_id) {
    if (isset($_POST['fp_exp_my_field'])) {
        $value = sanitize_text_field($_POST['fp_exp_my_field']);
        update_post_meta($post_id, '_fp_exp_my_field', $value);
    }
}, 10, 1);
```

---

## 🧪 Testing

### Syntax Check

```bash
# PHP
bash tools/run-php-syntax-check.sh

# Verifica calendario
bash tools/verification/verify-calendar-system.sh
```

### Linting

```bash
# PHP CodeSniffer
composer run phpcs

# ESLint
npm run lint

# Fix automatici
composer run phpcbf
npm run lint:fix
```

### Unit Tests (se configurati)

```bash
# PHPUnit
composer test

# Specific test
./vendor/bin/phpunit tests/Booking/RecurrenceTest.php
```

### Manual Testing

```bash
# Test flusso calendario
php tools/verification/test-calendar-data-flow.php
```

---

## 📦 Build e Deploy

### Build Plugin ZIP

```bash
# Bump patch version (0.3.4 → 0.3.5)
bash build.sh --bump=patch

# Bump minor version (0.3.4 → 0.4.0)
bash build.sh --bump=minor

# Set specific version
bash build.sh --set-version=1.0.0

# Build without version bump
bash build.sh
```

**Output:** `build/fp-experiences-{version}.zip`

### Release Workflow

```bash
# 1. Update CHANGELOG.md
vim docs/CHANGELOG.md

# 2. Commit changes
git add .
git commit -m "chore: release v1.0.0"

# 3. Tag version
git tag v1.0.0

# 4. Push
git push origin main
git push origin v1.0.0

# 5. GitHub Action builds automatically
```

---

## 🔍 Debugging

### Enable Debug Mode

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### Custom Logging

```php
// In your code
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('FP_EXP: ' . print_r($data, true));
}
```

**View logs:**
```bash
tail -f wp-content/debug.log
```

### JavaScript Console

```javascript
// In your JS
if (window.console && window.console.log) {
    console.log('[FP-EXP]', 'Debug info:', data);
}
```

### REST API Testing

```bash
# With curl
curl -X GET "http://localhost/wp-json/fp-exp/v1/availability?experience=123&start=2025-01-01&end=2025-01-31"

# With jq for pretty JSON
curl -s "http://localhost/wp-json/fp-exp/v1/availability?experience=123&start=2025-01-01&end=2025-01-31" | jq
```

---

## 📚 Resources

### Key Files da Conoscere

| File | Scopo |
|------|-------|
| `src/Plugin.php` | Bootstrap principale, registra tutti i componenti |
| `src/Booking/Recurrence.php` | Sistema calendario e ricorrenze |
| `src/Booking/AvailabilityService.php` | Generazione slot disponibilità |
| `src/Api/RestRoutes.php` | Tutti gli endpoint REST API |
| `assets/js/front/availability.js` | Calendario frontend |
| `assets/js/admin.js` | Form e UI admin |

### Documentation Links

- **[Frontend Modular Guide](FRONTEND-MODULAR-GUIDE.md)** - API moduli JS
- **[Calendar System](CALENDAR-SIMPLIFIED.md)** - Architettura calendario
- **[Playbook](PLAYBOOK.md)** - Metodologia sviluppo
- **[Technical Docs](../technical/)** - Audit e verifiche

### External Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WooCommerce Docs](https://woocommerce.com/documentation/)
- [REST API Handbook](https://developer.wordpress.org/rest-api/)

---

## 🤝 Contribuire

### Before Submitting PR

```bash
# 1. Syntax check
bash tools/run-php-syntax-check.sh

# 2. Verifica calendario
bash tools/verification/verify-calendar-system.sh

# 3. Linting
composer run phpcs
npm run lint

# 4. Fix issues
composer run phpcbf
npm run lint:fix

# 5. Test manually
# - Crea esperienza
# - Verifica calendario
# - Test prenotazione

# 6. Update docs se necessario
```

### PR Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tested locally
- [ ] Syntax check passed
- [ ] Linting passed
- [ ] Manual testing completed

## Screenshots (if UI changes)
[Add screenshots]

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No breaking changes (or documented)
```

---

## 💡 Tips Pro

### Faster Development

```bash
# Watch files and rebuild
npm run watch

# Use local wp-env (Docker)
npm -g install @wordpress/env
wp-env start
```

### Code Snippets

**Get experience ID in template:**
```php
$experience_id = get_the_ID();
```

**Get availability for experience:**
```php
use FP_Exp\Booking\AvailabilityService;

$slots = AvailabilityService::get_virtual_slots(
    $experience_id,
    '2025-01-01',
    '2025-01-31'
);
```

**Call REST API from JS:**
```javascript
const url = `${wpApiSettings.root}fp-exp/v1/availability`;
const response = await fetch(url + '?experience=123&start=2025-01-01&end=2025-01-31', {
    credentials: 'same-origin'
});
const data = await response.json();
```

### Database Queries

```php
global $wpdb;

// Get slots table
$table = $wpdb->prefix . 'fp_exp_slots';

// Query slots
$slots = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE experience_id = %d AND start_utc >= %s",
    $experience_id,
    gmdate('Y-m-d H:i:s')
));
```

---

## 🆘 Common Issues

### "Class not found"

**Solution:**
```bash
# Rebuild autoload
composer dump-autoload
```

### "JavaScript not working"

**Solution:**
```
1. Check browser console for errors
2. Verify script enqueued correctly
3. Check dependencies loaded first
4. Enable SCRIPT_DEBUG in wp-config.php
```

### "REST API returns 404"

**Solution:**
```
1. Flush permalinks: Settings → Permalinks → Save
2. Check .htaccess permissions
3. Verify endpoint registered correctly
4. Test with: /wp-json/fp-exp/v1/
```

### "Build fails"

**Solution:**
```bash
# Clean and rebuild
rm -rf vendor node_modules build
composer install
npm install
bash build.sh
```

---

**Happy Coding!** 🚀

*Ultimo aggiornamento: 7 Ottobre 2025*