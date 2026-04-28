<?php

declare(strict_types=1);

$viewsRoot = dirname(__DIR__) . '/resources/views';

if (!is_dir($viewsRoot)) {
    fwrite(STDERR, "Views directory not found: {$viewsRoot}\n");
    exit(1);
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS)
);

$changedFiles = [];

foreach ($files as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $original = file_get_contents($path);

    if ($original === false) {
        fwrite(STDERR, "Failed to read {$path}\n");
        continue;
    }

    $updated = $original;

    $updated = str_replace(
        '<script async src="https://www.clickcease.com/monitor/stat.js"> </script> ',
        '',
        $updated
    );

    $updated = str_replace(
        '<noscript> <a href="https://www.clickcease.com" rel="nofollow"><img src="https://monitor.clickcease.com/stats/stats.aspx" alt="Clickcease"></a> </noscript> ',
        '',
        $updated
    );

    $updated = preg_replace(
        '/<!-- Google Tag Manager -->\s*<script>.*?googletagmanager\.com\/gtm\.js\?id=.*?<\/script>\s*<!-- End Google Tag Manager -->/su',
        '',
        $updated
    ) ?? $updated;

    $updated = preg_replace(
        '/<!-- Google Tag Manager \(noscript\) -->\s*<noscript><iframe src="https:\/\/www\.googletagmanager\.com\/ns\.html\?id=.*?<\/iframe><\/noscript>\s*<!-- End Google Tag Manager \(noscript\) -->/su',
        '',
        $updated
    ) ?? $updated;

    $updated = preg_replace(
        '/<!-- Statistics script Complianz GDPR\/CCPA -->\s*<script data-category="functional">\s*\(function\(w,d,s,l,i\)\{.*?googletagmanager\.com\/gtm\.js\?id=.*?\}\)\(window,document,\'script\',\'dataLayer\',\'GTM-523C923L\'\);\s*/su',
        '<!-- Statistics script Complianz GDPR/CCPA --> <script data-category="functional"> ',
        $updated
    ) ?? $updated;

    $updated = preg_replace(
        '/"baseUrl":"https:\/\/s\.w\.org\/images\/core\/emoji\/17\.0\.2\/72x72\/","ext":"\.png","svgUrl":"https:\/\/s\.w\.org\/images\/core\/emoji\/17\.0\.2\/svg\/","svgExt":"\.svg","source":\{"concatemoji":"\{\{ asset\("assets\/js\/wp-emoji-release-min-aebb10ac55\.js"\) \}\}"\}/u',
        '"baseUrl":"{{ asset("assets/images/emoji/72x72") }}/","ext":".png","svgUrl":"{{ asset("assets/images/emoji/svg") }}/","svgExt":".svg","source":{"concatemoji":"{{ asset("assets/js/wp-emoji-release-min-aebb10ac55.js") }}"}}',
        $updated
    ) ?? $updated;

    $updated = str_replace(
        '"assets":"https:\/\/gwh-installationstechnik.at\/wp-content\/plugins\/elementor\/assets\/"',
        '"assets":"{{ url(\'/wp-content/plugins/elementor/assets\') }}\/"',
        $updated
    );

    $updated = str_replace(
        '"assets":"https:\/\/gwh-installationstechnik.at\/wp-content\/plugins\/elementor-pro\/assets\/"',
        '"assets":"{{ url(\'/wp-content/plugins/elementor-pro/assets\') }}\/"',
        $updated
    );

    if ($updated !== $original) {
        file_put_contents($path, $updated);
        $changedFiles[] = $path;
    }
}

echo 'Updated ' . count($changedFiles) . " files.\n";
foreach ($changedFiles as $path) {
    echo $path . "\n";
}
