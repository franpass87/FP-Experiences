<?php
/**
 * Compile .po files to .mo files for FP Experiences translations.
 * 
 * Run from command line: php compile-translations.php
 * Or via WP-CLI: wp eval-file compile-translations.php
 * 
 * @package FP_Experiences
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

$languages_dir = dirname(__DIR__) . '/languages';

// Simple PO to MO compiler
function compile_po_to_mo(string $po_file, string $mo_file): bool {
    $hash = [];
    $po_content = file_get_contents($po_file);
    
    if ($po_content === false) {
        echo "Error: Cannot read {$po_file}\n";
        return false;
    }
    
    // Parse PO file
    preg_match_all('/msgid\s+"(.*)"\s+msgstr\s+"(.*)"/sU', $po_content, $matches, PREG_SET_ORDER);
    
    // Also handle multiline strings
    preg_match_all('/msgid\s+""\s+"(.*)"\s+msgstr\s+""\s+"(.*)"/sU', $po_content, $multiline_matches, PREG_SET_ORDER);
    
    $all_matches = array_merge($matches, $multiline_matches);
    
    foreach ($all_matches as $match) {
        $msgid = stripcslashes($match[1]);
        $msgstr = stripcslashes($match[2]);
        
        if (!empty($msgid) && !empty($msgstr)) {
            $hash[$msgid] = $msgstr;
        }
    }
    
    // Handle plural forms
    preg_match_all('/msgid\s+"(.*)"\s+msgid_plural\s+"(.*)"\s+msgstr\[0\]\s+"(.*)"\s+msgstr\[1\]\s+"(.*)"/sU', $po_content, $plural_matches, PREG_SET_ORDER);
    
    foreach ($plural_matches as $match) {
        $msgid = stripcslashes($match[1]);
        $msgstr_0 = stripcslashes($match[3]);
        $msgstr_1 = stripcslashes($match[4]);
        
        if (!empty($msgid)) {
            // Store plural as null-separated string
            $hash[$msgid] = $msgstr_0 . "\0" . $msgstr_1;
        }
    }
    
    if (empty($hash)) {
        echo "Warning: No translations found in {$po_file}\n";
        return false;
    }
    
    // Generate MO file
    ksort($hash);
    
    $offsets = [];
    $ids = '';
    $strings = '';
    
    foreach ($hash as $id => $str) {
        $offsets[] = [strlen($ids), strlen($id), strlen($strings), strlen($str)];
        $ids .= $id . "\0";
        $strings .= $str . "\0";
    }
    
    $count = count($offsets);
    $header_size = 28;
    $key_start = $header_size + $count * 16;
    $value_start = $key_start + strlen($ids);
    
    $mo = pack('V', 0x950412de); // Magic number
    $mo .= pack('V', 0); // Revision
    $mo .= pack('V', $count); // Number of strings
    $mo .= pack('V', $header_size); // Offset of original strings
    $mo .= pack('V', $header_size + $count * 8); // Offset of translation strings
    $mo .= pack('V', 0); // Size of hashing table
    $mo .= pack('V', $value_start + strlen($strings)); // Offset of hashing table
    
    $keys = [];
    $values = [];
    $key_offset = 0;
    $value_offset = 0;
    
    foreach ($offsets as $offset) {
        $keys[] = pack('VV', $offset[1], $key_start + $key_offset);
        $values[] = pack('VV', $offset[3], $value_start + $value_offset);
        $key_offset += $offset[1] + 1;
        $value_offset += $offset[3] + 1;
    }
    
    $mo .= implode('', $keys);
    $mo .= implode('', $values);
    $mo .= $ids;
    $mo .= $strings;
    
    $result = file_put_contents($mo_file, $mo);
    
    if ($result === false) {
        echo "Error: Cannot write {$mo_file}\n";
        return false;
    }
    
    return true;
}

echo "FP Experiences Translation Compiler\n";
echo "===================================\n\n";

$po_files = glob($languages_dir . '/*.po');

if (empty($po_files)) {
    echo "No .po files found in {$languages_dir}\n";
    exit(1);
}

$success = 0;
$failed = 0;

foreach ($po_files as $po_file) {
    $mo_file = preg_replace('/\.po$/', '.mo', $po_file);
    $basename = basename($po_file);
    
    echo "Compiling {$basename}... ";
    
    if (compile_po_to_mo($po_file, $mo_file)) {
        echo "OK\n";
        $success++;
    } else {
        echo "FAILED\n";
        $failed++;
    }
}

echo "\n";
echo "Done! {$success} compiled, {$failed} failed.\n";

exit($failed > 0 ? 1 : 0);
