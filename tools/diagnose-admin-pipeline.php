<?php
declare(strict_types=1);

/**
 * Diagnosi statica pipeline admin FP Experiences.
 *
 * Uso:
 *   php tools/diagnose-admin-pipeline.php
 */

$root = dirname(__DIR__);
$adminDir = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Admin';
$mainCss = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'main.css';
$adminCssFallback = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'admin.css';

if (!is_dir($adminDir)) {
    fwrite(STDERR, "Admin directory non trovata: {$adminDir}\n");
    exit(1);
}

/**
 * @return array<int, string>
 */
function listPhpFiles(string $dir): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    /** @var SplFileInfo $file */
    foreach ($it as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

/**
 * @return array<int, string>
 */
function parseLiteralString(array $tokens, int &$index): ?string
{
    $token = $tokens[$index] ?? null;
    if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
        return null;
    }

    $value = $token[1];
    if (strlen($value) < 2) {
        return null;
    }

    $quote = $value[0];
    if (($quote !== "'" && $quote !== '"') || substr($value, -1) !== $quote) {
        return null;
    }

    return stripcslashes(substr($value, 1, -1));
}

/**
 * @return array<string, array<int, int>>
 */
function detectExecutableCalls(string $file): array
{
    $content = (string) file_get_contents($file);
    $tokens = token_get_all($content);
    $total = count($tokens);

    $found = [
        'admin_enqueue_hook' => [],
        'enqueue_fp_admin_style' => [],
        'enqueue_fp_admin_script' => [],
        'localize_fp_admin' => [],
        'inline_fp_admin' => [],
    ];

    for ($i = 0; $i < $total; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        $function = strtolower($token[1]);
        if (!in_array($function, ['add_action', 'wp_enqueue_style', 'wp_enqueue_script', 'wp_localize_script', 'wp_add_inline_script'], true)) {
            continue;
        }

        $j = $i + 1;
        while ($j < $total) {
            $next = $tokens[$j];
            if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $j++;
                continue;
            }

            if ($next !== '(') {
                $j = $total;
            }
            break;
        }

        if ($j >= $total || $tokens[$j] !== '(') {
            continue;
        }

        $depth = 1;
        $argIndex = 0;
        $argStrings = [];
        $k = $j + 1;

        while ($k < $total && $depth > 0) {
            $current = $tokens[$k];
            if ($current === '(') {
                $depth++;
            } elseif ($current === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            } elseif ($depth === 1 && $current === ',') {
                $argIndex++;
            } elseif ($depth === 1) {
                $literal = parseLiteralString($tokens, $k);
                if ($literal !== null && !isset($argStrings[$argIndex])) {
                    $argStrings[$argIndex] = $literal;
                }
            }
            $k++;
        }

        $line = is_array($token) ? (int) ($token[2] ?? 0) : 0;
        if ($function === 'add_action' && ($argStrings[0] ?? '') === 'admin_enqueue_scripts') {
            $found['admin_enqueue_hook'][] = $line;
        } elseif ($function === 'wp_enqueue_style' && ($argStrings[0] ?? '') === 'fp-exp-admin') {
            $found['enqueue_fp_admin_style'][] = $line;
        } elseif ($function === 'wp_enqueue_script' && ($argStrings[0] ?? '') === 'fp-exp-admin') {
            $found['enqueue_fp_admin_script'][] = $line;
        } elseif ($function === 'wp_localize_script' && ($argStrings[0] ?? '') === 'fp-exp-admin' && ($argStrings[1] ?? '') === 'fpExpAdmin') {
            $found['localize_fp_admin'][] = $line;
        } elseif ($function === 'wp_add_inline_script' && ($argStrings[0] ?? '') === 'fp-exp-admin') {
            $found['inline_fp_admin'][] = $line;
        }
    }

    return $found;
}

/**
 * @return array<string, array<int, string>>
 */
function collectMatches(array $files): array
{
    $results = [
        'admin_enqueue_hook' => [],
        'enqueue_fp_admin_style' => [],
        'enqueue_fp_admin_script' => [],
        'localize_fp_admin' => [],
        'inline_fp_admin' => [],
    ];

    foreach ($files as $file) {
        $found = detectExecutableCalls($file);
        foreach ($found as $key => $isFound) {
            if (!empty($isFound)) {
                $lines = array_values(array_unique(array_filter($isFound)));
                sort($lines);
                $preview = [];
                foreach ($lines as $line) {
                    $preview[] = $line . ': ' . getLineSnippet($file, $line);
                }
                $results[$key][] = $file . ' (linee: ' . implode(', ', $lines) . ')' . "\n  ↳ " . implode("\n  ↳ ", $preview);
            }
        }
    }

    return $results;
}

/**
 * @return array<int, string>
 */
function checkMainCssImports(string $mainCssPath): array
{
    if (!is_file($mainCssPath)) {
        return ["main.css non trovato: {$mainCssPath}"];
    }

    $content = (string) file_get_contents($mainCssPath);
    preg_match_all("#@import\\s+url\\('\\./([^']+)'\\);#", $content, $matches);
    $imports = $matches[1] ?? [];
    $missing = [];
    $baseDir = dirname($mainCssPath);

    foreach ($imports as $import) {
        $path = $baseDir . DIRECTORY_SEPARATOR . $import;
        if (!is_file($path)) {
            $missing[] = "Import mancante: {$import}";
        }
    }

    return $missing;
}

function outSection(string $title): void
{
    echo "\n=== {$title} ===\n";
}

function outList(array $items): void
{
    if (empty($items)) {
        echo "- (none)\n";
        return;
    }
    foreach ($items as $item) {
        echo "- {$item}\n";
    }
}

function getLineSnippet(string $file, int $line): string
{
    if ($line <= 0 || !is_file($file)) {
        return '';
    }

    $lines = @file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines) || !isset($lines[$line - 1])) {
        return '';
    }

    return trim((string) $lines[$line - 1]);
}

$phpFiles = listPhpFiles($adminDir);
$matches = collectMatches($phpFiles);
$missingImports = checkMainCssImports($mainCss);

echo "FP Experiences - Admin pipeline diagnosis\n";
echo "Root: {$root}\n";
echo 'PHP files scanned: ' . count($phpFiles) . "\n";

outSection('Hook admin_enqueue_scripts');
outList($matches['admin_enqueue_hook']);
echo 'Totale: ' . count($matches['admin_enqueue_hook']) . "\n";

outSection("wp_enqueue_style('fp-exp-admin')");
outList($matches['enqueue_fp_admin_style']);
echo 'Totale: ' . count($matches['enqueue_fp_admin_style']) . "\n";

outSection("wp_enqueue_script('fp-exp-admin')");
outList($matches['enqueue_fp_admin_script']);
echo 'Totale: ' . count($matches['enqueue_fp_admin_script']) . "\n";

outSection("wp_localize_script('fp-exp-admin', 'fpExpAdmin')");
outList($matches['localize_fp_admin']);
echo 'Totale: ' . count($matches['localize_fp_admin']) . "\n";

outSection("wp_add_inline_script('fp-exp-admin')");
outList($matches['inline_fp_admin']);
echo 'Totale: ' . count($matches['inline_fp_admin']) . "\n";

outSection('Integrita import CSS main.css');
outList($missingImports);
if (empty($missingImports)) {
    echo "- Tutti gli import di main.css risultano presenti.\n";
}

outSection('Fallback admin.css');
if (is_file($adminCssFallback)) {
    $fallback = (string) file_get_contents($adminCssFallback);
    $isShim = preg_match("/@import\\s+url\\('admin\\/main\\.css'\\);/", $fallback) === 1;
    echo $isShim
        ? "- admin.css e' uno shim verso admin/main.css (OK)\n"
        : "- admin.css NON e' solo shim: verifica divergenze col sistema modulare\n";
} else {
    echo "- admin.css non trovato\n";
}

outSection('Indicatore rischio');
$risk = 0;
$risk += max(0, count($matches['admin_enqueue_hook']) - 1);
$risk += max(0, count($matches['enqueue_fp_admin_style']) - 1);
$risk += max(0, count($matches['enqueue_fp_admin_script']) - 1);
$risk += max(0, count($matches['localize_fp_admin']) - 1);
$risk += count($missingImports) * 2;
echo "- Rischio pipeline (0=ottimo, alto=instabile): {$risk}\n";

echo "\nDone.\n";
