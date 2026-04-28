<?php

$viewDir = __DIR__ . '/../resources/views';
$iterator = new DirectoryIterator($viewDir);

$replacements = [
    "{{ str_replace('/', '\\\\/', url('')) }}\\/wp-content\\/plugins\\/elementor\\/assets\\/" => 'https:\\/\\/gwh-installationstechnik.at\\/wp-content\\/plugins\\/elementor\\/assets\\/',
    "{{ str_replace('/', '\\\\/', url('')) }}\\/wp-content\\/plugins\\/elementor-pro\\/assets\\/" => 'https:\\/\\/gwh-installationstechnik.at\\/wp-content\\/plugins\\/elementor-pro\\/assets\\/',
];

$updatedFiles = [];

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $contents = file_get_contents($path);
    $updated = strtr($contents, $replacements);

    if ($updated !== $contents) {
        file_put_contents($path, $updated);
        $updatedFiles[] = $fileInfo->getFilename();
    }
}

echo 'Updated ' . count($updatedFiles) . " Blade files.\n";
foreach ($updatedFiles as $fileName) {
    echo $fileName . "\n";
}
