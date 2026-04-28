<?php

$viewDir = __DIR__ . '/../resources/views';
$iterator = new DirectoryIterator($viewDir);

$replacements = [
    'https:\/\/gwh-installationstechnik.at' => "{{ str_replace('/', '\\\\/', url('')) }}",
    'https%3A%2F%2Fgwh-installationstechnik.at' => "{{ urlencode(url('')) }}",
    'https://gwh-installationstechnik.at' => "{{ url('') }}",
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
