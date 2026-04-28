<?php

$viewDir = __DIR__ . '/../resources/views';
$iterator = new DirectoryIterator($viewDir);

$replacements = [
    'https:\/\/gwh-installationstechnik.at\/wp-json\/' => "{{ url('/wp-json/') }}/",
    'https:\/\/gwh-installationstechnik.at\/wp-admin\/admin-ajax.php' => "{{ url('/wp-admin/admin-ajax.php') }}",
    'https://gwh-installationstechnik.at/wp-json/complianz/v1/' => "{{ url('/wp-json/complianz/v1/') }}/",
    'https://gwh-installationstechnik.at/wp-content/uploads/complianz/css/banner-{banner_id}-{type}.css?v=30' => "{{ url('/wp-content/uploads/complianz/css/banner-{banner_id}-{type}.css') }}?v=30",
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
