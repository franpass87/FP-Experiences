<?php

/**
 * CLI: genera HTML di post-new.php?post_type=fp_experience (utente admin) per debug script inline.
 * Uso: php tools/dump-post-new-html.php [ABS_PATH_WORDPRESS_ROOT]
 *      (path obbligatorio se il plugin è una junction fuori dalla cartella del sito)
 *
 * @package FP_Exp
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

// Prima di qualsiasi output WP: cattura tutto lo stdout dell’intera richiesta admin.
$out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fp-exp-post-new-dump.html';
$targetLine = 5799;
ob_start(
    static function (string $buffer, int $phase): string {
        static $acc = '';
        $acc .= $buffer;
        $dump = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fp-exp-post-new-dump.html';
        file_put_contents($dump, $acc);
        return $buffer;
    },
    0,
    PHP_OUTPUT_HANDLER_STDFLAGS
);

$wp_root = isset($argv[1]) && is_string($argv[1]) && $argv[1] !== ''
    ? rtrim($argv[1], "/\\")
    : '';

if ($wp_root === '' || ! is_readable($wp_root . '/wp-load.php')) {
    $wp_root = __DIR__;
    for ($i = 0; $i < 16; $i++) {
        if (is_readable($wp_root . '/wp-load.php')) {
            break;
        }
        $parent = dirname($wp_root);
        if ($parent === $wp_root) {
            $wp_root = '';
            break;
        }
        $wp_root = $parent;
    }
}

if ($wp_root === '' || ! is_readable($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "wp-load.php non trovato. Passa la root WP come primo argomento, es.:\n");
    fwrite(STDERR, "  php tools/dump-post-new-html.php \"C:\\path\\to\\public\"\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'fp-development.local';
$_SERVER['SERVER_NAME'] = 'fp-development.local';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_URI'] = '/wp-admin/post-new.php?post_type=fp_experience';
$_SERVER['SCRIPT_NAME'] = '/wp-admin/post-new.php';
$_SERVER['PHP_SELF'] = '/wp-admin/post-new.php';
$_GET['post_type'] = 'fp_experience';

define('WP_USE_THEMES', false);

require $wp_root . '/wp-load.php';

$admins = get_users([
    'role' => 'administrator',
    'number' => 1,
    'orderby' => 'ID',
    'order' => 'ASC',
]);

if ($admins === []) {
    fwrite(STDERR, "Nessun amministratore.\n");
    exit(1);
}

$user_id = (int) $admins[0]->ID;
wp_set_current_user($user_id);

// auth_redirect() in admin.php usa il cookie, non solo wp_set_current_user (CLI senza cookie → redirect vuoto).
$exp = time() + (defined('WEEK_IN_SECONDS') ? WEEK_IN_SECONDS : 604800) * 2;
if (function_exists('wp_generate_auth_cookie')) {
    if (function_exists('is_ssl') && is_ssl()) {
        $_COOKIE[SECURE_AUTH_COOKIE] = wp_generate_auth_cookie($user_id, $exp, 'secure_auth');
    } else {
        $_COOKIE[AUTH_COOKIE] = wp_generate_auth_cookie($user_id, $exp, 'auth');
    }
    $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($user_id, $exp, 'logged_in');
}

register_shutdown_function(static function () use ($out, $targetLine): void {
    $html = (string) @file_get_contents($out);
    if ($html === '') {
        $html = '';
        while (ob_get_level() > 0) {
            $c = ob_get_clean();
            if (is_string($c)) {
                $html = $c . $html;
            }
        }
        if ($html !== '') {
            file_put_contents($out, $html);
        }
    }

    if ($html === '') {
        fwrite(STDERR, "Nessun HTML catturato. Vedi {$out}\n");
        return;
    }

    $lines = explode("\n", $html);
    $total = count($lines);
    fwrite(STDOUT, "Scritto: {$out}\nRighe: {$total}\n");

    $from = max(0, $targetLine - 15);
    $to = min($total - 1, $targetLine + 15);
    for ($i = $from; $i <= $to; $i++) {
        $n = $i + 1;
        $prefix = ($n === $targetLine) ? '>>>' : '   ';
        $line = $lines[$i] ?? '';
        fwrite(STDOUT, "{$prefix} {$n}: " . mb_substr($line, 0, 220) . "\n");
    }
});

// WooCommerce: OrderAttributionController può chiamare wc_get_page_screen_id prima del bootstrap admin completo.
if (defined('WC_ABSPATH')) {
    $wc_admin_fn = WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
    if (is_readable($wc_admin_fn)) {
        require_once $wc_admin_fn;
    }
}

try {
    require ABSPATH . 'wp-admin/post-new.php';
} catch (Throwable $e) {
    fwrite(STDERR, 'Errore: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
