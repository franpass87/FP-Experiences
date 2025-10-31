<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Booking\Slots;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use ReflectionClass;

use function add_shortcode;
use function current_user_can;
use function esc_html;
use function esc_url;
use function admin_url;
use function get_posts;
use function get_post_meta;
use function home_url;
use function file_exists;
use function is_readable;
use function filesize;
use function filemtime;
use function fopen;
use function fseek;
use function ftell;
use function fread;
use function fclose;
use function explode;
use function array_filter;
use function array_slice;
use function implode;
use function strpos;
use function stripos;
use function trim;
use function gmdate;
use function strtotime;
use function is_array;
use function absint;
use function number_format;
use function date;

/**
 * Shortcode per diagnostica sistema (solo admin)
 * Uso: [fp_exp_diagnostic]
 */
final class DiagnosticShortcode
{
    public static function register(): void
    {
        add_shortcode('fp_exp_diagnostic', [self::class, 'render']);
    }

    public static function render(): string
    {
        if (!current_user_can('manage_options')) {
            return '<p>Accesso negato. Solo amministratori.</p>';
        }

        ob_start();
        self::render_diagnostic();
        return ob_get_clean() ?: '';
    }

    private static function render_diagnostic(): void
    {
        ?>
        <style>
            .fp-diagnostic { font-family: -apple-system, sans-serif; max-width: 1200px; margin: 20px auto; }
            .fp-diag-box { background: white; padding: 20px; margin: 15px 0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .fp-diag-success { border-left: 4px solid #46b450; }
            .fp-diag-error { border-left: 4px solid #dc3232; background: #fff8f8; }
            .fp-diag-warning { border-left: 4px solid #ffb900; background: #fffbf0; }
            .fp-diag-box h2 { margin: 0 0 15px; color: #1d2327; }
            .fp-diag-box h3 { margin: 15px 0 10px; color: #2271b1; font-size: 16px; }
            .fp-diag-pre { background: #f6f7f7; padding: 12px; overflow-x: auto; font-size: 13px; border-radius: 3px; }
            .fp-diag-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            .fp-diag-table th, .fp-diag-table td { padding: 10px; text-align: left; border-bottom: 1px solid #e0e0e0; }
            .fp-diag-table th { background: #f6f7f7; font-weight: 600; }
            .fp-status-ok { color: #46b450; font-weight: bold; }
            .fp-status-error { color: #dc3232; font-weight: bold; }
            .fp-status-warning { color: #ffb900; font-weight: bold; }
        </style>

        <div class="fp-diagnostic">
            <div class="fp-diag-box">
                <h2>🔍 FP Experiences - Diagnostica Sistema</h2>
                <p><strong>Sito:</strong> <?php echo esc_url(home_url()); ?></p>
                <p><strong>Data:</strong> <?php echo esc_html(date('Y-m-d H:i:s')); ?></p>
            </div>

            <?php self::check_files(); ?>
            <?php self::check_experiences(); ?>
            <?php self::test_slot_creation(); ?>
            <?php self::check_debug_log(); ?>
            <?php self::show_recommendations(); ?>

            <div class="fp-diag-box">
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=fp_exp_dashboard')); ?>">← Torna a FP Experiences</a></p>
            </div>
        </div>
        <?php
    }

    private static function check_files(): void
    {
        ?>
        <div class="fp-diag-box">
            <h2>1️⃣ Verifica File con Fix</h2>
            <table class="fp-diag-table">
                <tr><th>File</th><th>Status</th><th>Data Modifica</th></tr>
                <?php
                $files = [
                    'src/Booking/Slots.php' => 'if ($capacity_total === 0)',
                    'src/Booking/Checkout.php' => 'Skip slot validation for gift',
                ];

                foreach ($files as $file => $search) {
                    $path = FP_EXP_PLUGIN_DIR . $file;
                    $exists = file_exists($path);
                    $has_fix = false;
                    $date = '';

                    if ($exists) {
                        $content = file_get_contents($path);
                        $has_fix = strpos($content, $search) !== false;
                        $date = date('Y-m-d H:i', filemtime($path));
                    }

                    $status = $has_fix ? '✅ FIX PRESENTE' : '❌ FIX ASSENTE';
                    $class = $has_fix ? 'fp-status-ok' : 'fp-status-error';

                    echo "<tr>";
                    echo "<td><code>" . esc_html($file) . "</code></td>";
                    echo "<td class='$class'>" . esc_html($status) . "</td>";
                    echo "<td>" . esc_html($date) . "</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </div>
        <?php
    }

    private static function check_experiences(): void
    {
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        ?>
        <div class="fp-diag-box">
            <h2>2️⃣ Configurazione Esperienze</h2>
            <p><strong>Totale:</strong> <?php echo count($experiences); ?></p>

            <?php if (!empty($experiences)): ?>
            <table class="fp-diag-table">
                <tr><th>ID</th><th>Titolo</th><th>slot_capacity</th><th>Buffer</th><th>Status</th></tr>
                <?php
                foreach ($experiences as $exp):
                    $avail = get_post_meta($exp->ID, '_fp_exp_availability', true);
                    $cap = is_array($avail) && isset($avail['slot_capacity']) ? $avail['slot_capacity'] : 'N/A';
                    $buf_b = is_array($avail) && isset($avail['buffer_before_minutes']) ? $avail['buffer_before_minutes'] : 0;
                    $buf_a = is_array($avail) && isset($avail['buffer_after_minutes']) ? $avail['buffer_after_minutes'] : 0;

                    $status = ($cap !== 'N/A' && $cap > 0) ? '✅ OK' : '❌ MANCA CAPACITY';
                    $class = ($cap !== 'N/A' && $cap > 0) ? 'fp-status-ok' : 'fp-status-error';
                    ?>
                    <tr>
                        <td><?php echo $exp->ID; ?></td>
                        <td><?php echo esc_html($exp->post_title); ?></td>
                        <td><strong><?php echo esc_html((string)$cap); ?></strong></td>
                        <td><?php echo esc_html("$buf_b / $buf_a"); ?></td>
                        <td class="<?php echo $class; ?>"><?php echo esc_html($status); ?></td>
                    </tr>

                    <?php if ($exp === $experiences[0]): ?>
                    <tr>
                        <td colspan="5">
                            <details>
                                <summary><strong>Mostra availability completo</strong></summary>
                                <pre class="fp-diag-pre"><?php echo esc_html(print_r($avail, true)); ?></pre>
                            </details>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function test_slot_creation(): void
    {
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'post_status' => 'publish',
            'posts_per_page' => 1,
        ]);

        if (empty($experiences)) {
            return;
        }

        $exp = $experiences[0];
        $exp_id = $exp->ID;

        $test_start = gmdate('Y-m-d H:i:s', strtotime('+15 days 12:00'));
        $test_end = gmdate('Y-m-d H:i:s', strtotime('+15 days 14:00'));

        ?>
        <div class="fp-diag-box">
            <h2>3️⃣ Test Creazione Slot</h2>
            <p><strong>Esperienza test:</strong> <?php echo esc_html($exp->post_title); ?> (ID: <?php echo $exp_id; ?>)</p>
            <p><strong>Start:</strong> <code><?php echo esc_html($test_start); ?></code></p>
            <p><strong>End:</strong> <code><?php echo esc_html($test_end); ?></code></p>

            <?php
            try {
                $slot_result = Slots::ensure_slot_for_occurrence($exp_id, $test_start, $test_end);

                // Handle WP_Error
                if (is_wp_error($slot_result)) {
                    echo "<div class='fp-diag-box fp-diag-error'>";
                    echo "<p class='fp-status-error'>❌ <strong>FALLITO! WP_Error: " . esc_html($slot_result->get_error_message()) . "</strong></p>";
                    echo "<p><strong>Error Code:</strong> " . esc_html($slot_result->get_error_code()) . "</p>";
                    $error_data = $slot_result->get_error_data();
                    if ($error_data) {
                        echo "<p><strong>Error Data:</strong></p>";
                        echo "<pre class='fp-diag-pre'>" . esc_html(print_r($error_data, true)) . "</pre>";
                    }
                    echo "</div>";
                } elseif ($slot_result > 0) {
                    echo "<div class='fp-diag-box fp-diag-success'>";
                    echo "<p class='fp-status-ok'>✅ <strong>SUCCESSO! Slot creato/trovato: ID $slot_result</strong></p>";
                    $slot = Slots::get_slot($slot_result);
                    if ($slot) {
                        echo "<pre class='fp-diag-pre'>" . esc_html(print_r($slot, true)) . "</pre>";
                    }
                    echo "</div>";
                } else {
                    echo "<div class='fp-diag-box fp-diag-error'>";
                    echo "<p class='fp-status-error'>❌ <strong>FALLITO! ensure_slot_for_occurrence() ha ritornato 0</strong></p>";
                    echo "<p>Questo è il problema che causa l'errore checkout!</p>";
                    echo "</div>";
                }
            } catch (Exception $e) {
                echo "<div class='fp-diag-box fp-diag-error'>";
                echo "<p class='fp-status-error'>❌ Exception: " . esc_html($e->getMessage()) . "</p>";
                echo "</div>";
            }
            ?>
        </div>
        <?php
    }

    private static function check_debug_log(): void
    {
        $log_file = WP_CONTENT_DIR . '/debug.log';

        ?>
        <div class="fp-diag-box">
            <h2>4️⃣ Debug Log</h2>
            <?php
            if (!file_exists($log_file)) {
                echo "<p class='fp-status-warning'>⚠️ File debug.log non trovato</p>";
                echo "<p>Attiva WP_DEBUG in wp-config.php</p>";
                return;
            }

            if (!is_readable($log_file)) {
                echo "<p class='fp-status-error'>❌ File non leggibile</p>";
                return;
            }

            $filesize = filesize($log_file);
            echo "<p>✅ File trovato: " . number_format($filesize) . " bytes</p>";

            $read_size = min(50000, $filesize);
            $handle = fopen($log_file, 'r');

            if ($handle) {
                fseek($handle, -$read_size, SEEK_END);
                $content = fread($handle, $read_size);
                fclose($handle);

                $lines = explode("\n", $content);
                $fp_lines = array_filter($lines, function($line) {
                    return stripos($line, '[FP-EXP') !== false;
                });

                if (!empty($fp_lines)) {
                    echo "<h3>📋 Ultimi Log [FP-EXP]:</h3>";
                    echo "<pre class='fp-diag-pre' style='max-height: 400px; overflow-y: auto;'>";
                    foreach (array_slice($fp_lines, -30) as $line) {
                        echo esc_html($line) . "\n";
                    }
                    echo "</pre>";
                } else {
                    echo "<p class='fp-status-warning'>⚠️ Nessun log [FP-EXP] trovato. WP_DEBUG potrebbe essere disattivo.</p>";
                }
            }
            ?>
        </div>
        <?php
    }

    private static function show_recommendations(): void
    {
        ?>
        <div class="fp-diag-box fp-diag-warning">
            <h2>💡 Raccomandazioni</h2>
            <ol>
                <li><strong>Se fix ASSENTI:</strong> Carica file aggiornati via FTP</li>
                <li><strong>Se capacity = 0:</strong> Modifica esperienza e imposta capacity > 0</li>
                <li><strong>Svuota cache:</strong> Plugin + OpCache + Browser</li>
                <li><strong>Controlla log:</strong> Cerca cause esatte del fallimento</li>
            </ol>
        </div>
        <?php
    }
}

