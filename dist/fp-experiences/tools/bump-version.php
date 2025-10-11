<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);

function locatePluginFile(string $rootDir): string
{
    $candidates = glob($rootDir . '/*.php');

    foreach ($candidates as $candidate) {
        $handle = fopen($candidate, 'rb');
        if ($handle === false) {
            continue;
        }

        $buffer = '';
        $linesRead = 0;
        while (! feof($handle) && $linesRead < 20) {
            $buffer .= (string) fgets($handle);
            $linesRead++;
        }
        fclose($handle);

        if (preg_match('/^\s*\*\s*Plugin Name\s*:/mi', $buffer) && preg_match('/^\s*\*\s*Version\s*:/mi', $buffer)) {
            return $candidate;
        }
    }

    throw new RuntimeException('Unable to locate plugin main file.');
}

function parseOptions(): array
{
    $options = getopt('', ['major', 'minor', 'patch', 'set:']);

    $set = $options['set'] ?? null;
    $bumpFlags = array_filter([
        'major' => array_key_exists('major', $options),
        'minor' => array_key_exists('minor', $options),
        'patch' => array_key_exists('patch', $options),
    ]);

    if ($set !== null && ! empty($bumpFlags)) {
        throw new InvalidArgumentException('Use either --set or one bump option.');
    }

    if ($set !== null) {
        if (! preg_match('/^\d+\.\d+\.\d+$/', $set)) {
            throw new InvalidArgumentException('Invalid version supplied via --set.');
        }

        return ['mode' => 'set', 'version' => $set];
    }

    if (empty($bumpFlags)) {
        $bump = 'patch';
    } elseif (count($bumpFlags) > 1) {
        throw new InvalidArgumentException('Only one of --major, --minor, or --patch can be used.');
    } else {
        $bump = array_key_first($bumpFlags);
    }

    return ['mode' => 'bump', 'bump' => $bump];
}

function bumpVersion(string $version, string $type): string
{
    [$major, $minor, $patch] = array_map('intval', explode('.', $version));

    switch ($type) {
        case 'major':
            $major++;
            $minor = 0;
            $patch = 0;
            break;
        case 'minor':
            $minor++;
            $patch = 0;
            break;
        case 'patch':
            $patch++;
            break;
        default:
            throw new InvalidArgumentException('Unsupported bump type: ' . $type);
    }

    return sprintf('%d.%d.%d', $major, $minor, $patch);
}

try {
    $pluginFile = locatePluginFile($rootDir);
    $options = parseOptions();
    $contents = file_get_contents($pluginFile);

    if ($contents === false) {
        throw new RuntimeException('Unable to read plugin file.');
    }

    if (! preg_match('/^\s*\*\s*Version\s*:\s*(\S+)/mi', $contents, $matches)) {
        throw new RuntimeException('Unable to detect current version in plugin header.');
    }

    $currentVersion = $matches[1];

    if (! preg_match('/^\d+\.\d+\.\d+$/', $currentVersion)) {
        throw new RuntimeException('Current version is not in semver format.');
    }

    if ($options['mode'] === 'set') {
        $newVersion = $options['version'];
    } else {
        $newVersion = bumpVersion($currentVersion, $options['bump']);
    }

    if ($newVersion === $currentVersion) {
        fwrite(STDOUT, $newVersion . PHP_EOL);
        exit(0);
    }

    $updated = $contents;
    $updated = preg_replace_callback(
        '/^(\s*\*\s*Version\s*:\s*)(\S+)(.*)$/mi',
        static function (array $m) use ($newVersion): string {
            return $m[1] . $newVersion . $m[3];
        },
        $updated,
        1,
        $count
    );

    if ($count !== 1) {
        throw new RuntimeException('Failed to update version in plugin header.');
    }

    $constCount = 0;
    $updated = preg_replace_callback(
        '/(define\(\s*[\'"]FP_EXP_VERSION[\'"]\s*,\s*[\'"])([^\'"]+)([\'"]\s*\);)/',
        static function (array $m) use ($newVersion): string {
            return $m[1] . $newVersion . $m[3];
        },
        $updated,
        1,
        $constCount
    );

    if ($updated === null) {
        throw new RuntimeException('Failed to process FP_EXP_VERSION constant.');
    }

    if ($constCount !== 1) {
        throw new RuntimeException('Unable to update FP_EXP_VERSION constant.');
    }

    if (file_put_contents($pluginFile, $updated) === false) {
        throw new RuntimeException('Failed to write updated plugin file.');
    }

    fwrite(STDOUT, $newVersion . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
