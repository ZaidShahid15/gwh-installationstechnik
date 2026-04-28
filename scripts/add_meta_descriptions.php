<?php

declare(strict_types=1);

$viewsRoot = dirname(__DIR__) . '/resources/views';

if (!is_dir($viewsRoot)) {
    fwrite(STDERR, "Views directory not found: {$viewsRoot}\n");
    exit(1);
}

$files = glob($viewsRoot . '/*.blade.php');
$changed = [];

foreach ($files as $path) {
    if (basename($path) === 'sitemap-xml.blade.php') {
        continue;
    }

    $original = file_get_contents($path);
    if ($original === false) {
        fwrite(STDERR, "Failed to read {$path}\n");
        continue;
    }

    if (preg_match('/<meta[^>]+name="description"/i', $original)) {
        continue;
    }

    if (!preg_match('/(<meta[^>]+property="og:description"[^>]+content="([^"]*)"[^>]*>)/i', $original, $matches)) {
        continue;
    }

    $ogTag = $matches[1];
    $description = $matches[2];
    $metaTag = '<meta name="description" content="' . $description . '"> ';

    $updated = str_replace($ogTag, $metaTag . $ogTag, $original);

    if ($updated !== $original) {
        file_put_contents($path, $updated);
        $changed[] = basename($path);
    }
}

echo 'Updated ' . count($changed) . " files.\n";
foreach ($changed as $file) {
    echo $file . PHP_EOL;
}
