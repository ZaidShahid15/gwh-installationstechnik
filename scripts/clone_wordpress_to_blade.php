<?php

declare(strict_types=1);

const BASE_URL = 'https://gwh-installationstechnik.at';

$projectRoot = dirname(__DIR__);
$publicDir = $projectRoot . '/public';
$viewsDir = $projectRoot . '/resources/views';
$storageDir = $projectRoot . '/storage/app/site-clone';

$assetDirs = [
    'css' => $publicDir . '/assets/css',
    'js' => $publicDir . '/assets/js',
    'images' => $publicDir . '/assets/images',
    'fonts' => $publicDir . '/assets/fonts',
    'misc' => $publicDir . '/assets/misc',
];

foreach ([$storageDir, ...array_values($assetDirs), $viewsDir . '/layouts', $viewsDir . '/partials'] as $dir) {
    if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
        throw new RuntimeException("Unable to create directory: {$dir}");
    }
}

$pageUrls = fetchPageUrls(BASE_URL . '/page-sitemap.xml');
$pageMap = buildPageMap($pageUrls);
$assetManifest = [];
$downloadedAssets = [];
$pageManifest = [];

foreach ($pageMap as $pageUrl => $pageData) {
    echo "Processing page: {$pageUrl}\n";

    $html = fetchText($pageUrl);
    file_put_contents($storageDir . '/' . $pageData['view'] . '.raw.html', $html);

    $pageAssets = discoverHtmlAssets($html, $pageUrl);
    foreach ($pageAssets as $assetUrl) {
        ensureAssetDownloaded(
            normalizeUrl($assetUrl, $pageUrl),
            $assetDirs,
            $assetManifest,
            $downloadedAssets
        );
    }

    $transformed = transformHtml($html, $pageUrl, $pageMap, $assetManifest);
    file_put_contents($viewsDir . '/' . $pageData['view'] . '.blade.php', $transformed);

    $pageManifest[] = [
        'url' => $pageUrl,
        'route' => $pageData['route'],
        'view' => $pageData['view'],
        'title' => extractTitle($html),
    ];
}

writeSupportViews($viewsDir, $pageManifest);
writeRoutes($projectRoot . '/routes/web.php', $pageManifest);
writeManifest($storageDir . '/manifest.json', $pageManifest, $assetManifest);

echo "Done. Pages: " . count($pageManifest) . ", assets: " . count($assetManifest) . "\n";

function fetchPageUrls(string $sitemapUrl): array
{
    $xml = @simplexml_load_string(fetchText($sitemapUrl));
    if (! $xml) {
        throw new RuntimeException("Unable to parse sitemap: {$sitemapUrl}");
    }

    $urls = [];
    foreach ($xml->url as $urlNode) {
        $loc = trim((string) $urlNode->loc);
        if ($loc !== '') {
            $urls[] = rtrim($loc, '/') . '/';
        }
    }

    return array_values(array_unique($urls));
}

function buildPageMap(array $pageUrls): array
{
    $pageMap = [];

    foreach ($pageUrls as $url) {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $trimmed = trim($path, '/');
        $view = $trimmed === '' ? 'home' : $trimmed;
        $route = $trimmed === '' ? '/' : '/' . $trimmed;

        $pageMap[$url] = [
            'view' => $view,
            'route' => $route,
        ];
    }

    return $pageMap;
}

function fetchText(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status >= 400) {
        throw new RuntimeException("Failed fetching {$url} (HTTP {$status}) {$error}");
    }

    return (string) $body;
}

function fetchBinary(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER => true,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status >= 400) {
        throw new RuntimeException("Failed fetching {$url} (HTTP {$status}) {$error}");
    }

    $headerText = substr((string) $response, 0, $headerSize);
    $body = substr((string) $response, $headerSize);
    $headers = [];

    foreach (preg_split('/\r\n|\r|\n/', $headerText) as $line) {
        if (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
    }

    return [
        'body' => $body,
        'content_type' => $headers['content-type'] ?? '',
    ];
}

function discoverHtmlAssets(string $html, string $pageUrl): array
{
    $assets = [];

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $attributeNames = ['src', 'href', 'poster', 'data-src', 'data-lazy-src', 'data-bg', 'data-background-image'];

    foreach ($xpath->query('//*') as $node) {
        if (! $node instanceof DOMElement) {
            continue;
        }

        foreach ($attributeNames as $attributeName) {
            if (! $node->hasAttribute($attributeName)) {
                continue;
            }

            $value = trim($node->getAttribute($attributeName));
            if (isAssetUrl($value, $pageUrl)) {
                $assets[] = $value;
            }
        }

        foreach (['srcset', 'data-srcset'] as $srcsetAttribute) {
            if (! $node->hasAttribute($srcsetAttribute)) {
                continue;
            }

            foreach (explode(',', $node->getAttribute($srcsetAttribute)) as $srcsetPart) {
                $candidate = trim(explode(' ', trim($srcsetPart))[0] ?? '');
                if (isAssetUrl($candidate, $pageUrl)) {
                    $assets[] = $candidate;
                }
            }
        }

        if ($node->hasAttribute('style')) {
            $assets = array_merge($assets, discoverCssUrls($node->getAttribute('style'), $pageUrl));
        }
    }

    foreach ($xpath->query('//style') as $styleNode) {
        $assets = array_merge($assets, discoverCssUrls($styleNode->textContent, $pageUrl));
    }

    $pattern = '#https?:\\\\?/\\\\?/gwh-installationstechnik\.at[^"\'\s<>()]+#i';
    if (preg_match_all($pattern, $html, $matches)) {
        foreach ($matches[0] as $match) {
            $unescaped = str_replace(['\\/', '\\\\'], ['/', '\\'], $match);
            if (isAssetUrl($unescaped, $pageUrl)) {
                $assets[] = $unescaped;
            }
        }
    }

    return array_values(array_unique(array_map(
        static fn (string $url): string => normalizeUrl($url, $pageUrl),
        $assets
    )));
}

function discoverCssUrls(string $css, string $baseUrl): array
{
    $urls = [];
    if (preg_match_all('/url\((?<quote>[\'"]?)(?<url>[^)\'"]+)\k<quote>\)/i', $css, $matches)) {
        foreach ($matches['url'] as $url) {
            $url = trim($url);
            if ($url === '' || str_starts_with($url, 'data:')) {
                continue;
            }

            $urls[] = normalizeUrl($url, $baseUrl);
        }
    }

    return $urls;
}

function ensureAssetDownloaded(string $url, array $assetDirs, array &$assetManifest, array &$downloadedAssets): void
{
    if (isset($downloadedAssets[$url])) {
        return;
    }

    $downloadedAssets[$url] = true;
    try {
        $binary = fetchBinary($url);
    } catch (RuntimeException $exception) {
        echo "Warning: {$exception->getMessage()}\n";
        return;
    }
    $bucket = determineAssetBucket($url, $binary['content_type']);
    $filename = buildAssetFilename($url, $binary['content_type']);
    $filePath = $assetDirs[$bucket] . '/' . $filename;
    file_put_contents($filePath, $binary['body']);

    $publicPath = "assets/{$bucket}/{$filename}";
    $assetManifest[$url] = [
        'bucket' => $bucket,
        'file' => $filePath,
        'public_path' => $publicPath,
    ];

    if ($bucket === 'css') {
        $css = file_get_contents($filePath);
        $nested = discoverCssUrls($css ?: '', $url);
        foreach ($nested as $nestedUrl) {
            ensureAssetDownloaded($nestedUrl, $assetDirs, $assetManifest, $downloadedAssets);
        }

        $rewrittenCss = transformCss((string) file_get_contents($filePath), $url, $assetManifest);
        file_put_contents($filePath, $rewrittenCss);
    }
}

function determineAssetBucket(string $url, string $contentType): string
{
    $path = strtolower((string) parse_url($url, PHP_URL_PATH));
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return match (true) {
        in_array($extension, ['css'], true) => 'css',
        in_array($extension, ['js', 'mjs'], true) => 'js',
        in_array($extension, ['woff', 'woff2', 'ttf', 'otf', 'eot'], true) => 'fonts',
        in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'avif', 'ico'], true) => 'images',
        str_contains($contentType, 'text/css') => 'css',
        str_contains($contentType, 'javascript') => 'js',
        str_contains($contentType, 'font') => 'fonts',
        str_contains($contentType, 'image/') => 'images',
        default => 'misc',
    };
}

function buildAssetFilename(string $url, string $contentType): string
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    $base = pathinfo($path, PATHINFO_FILENAME);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($ext === '') {
        $ext = match (true) {
            str_contains($contentType, 'text/css') => 'css',
            str_contains($contentType, 'javascript') => 'js',
            str_contains($contentType, 'image/png') => 'png',
            str_contains($contentType, 'image/jpeg') => 'jpg',
            str_contains($contentType, 'image/webp') => 'webp',
            str_contains($contentType, 'font/woff2') => 'woff2',
            str_contains($contentType, 'font/woff') => 'woff',
            default => 'bin',
        };
    }

    $base = sanitizeSegment($base !== '' ? $base : 'asset');
    $hash = substr(sha1($url), 0, 10);

    return "{$base}-{$hash}.{$ext}";
}

function sanitizeSegment(string $value): string
{
    $value = preg_replace('/[^a-zA-Z0-9\-]+/', '-', $value) ?? 'asset';
    $value = trim($value, '-');

    return $value !== '' ? strtolower($value) : 'asset';
}

function transformCss(string $css, string $cssUrl, array $assetManifest): string
{
    foreach ($assetManifest as $originalUrl => $asset) {
        $replacement = '../' . $asset['bucket'] . '/' . basename($asset['public_path']);
        $css = str_replace($originalUrl, $replacement, $css);
        $css = str_replace(escapeSlashes($originalUrl), $replacement, $css);

        $relative = makeRelativeUrl($originalUrl);
        $css = str_replace($relative, $replacement, $css);
    }

    return $css;
}

function transformHtml(string $html, string $pageUrl, array $pageMap, array $assetManifest): string
{
    foreach ($assetManifest as $originalUrl => $asset) {
        $bladeAsset = '{{ asset("' . $asset['public_path'] . '") }}';
        $html = str_replace($originalUrl, $bladeAsset, $html);
        $html = str_replace(escapeSlashes($originalUrl), $bladeAsset, $html);

        $relative = makeRelativeUrl($originalUrl);
        $html = str_replace('"' . $relative . '"', '"' . $bladeAsset . '"', $html);
        $html = str_replace("'" . $relative . "'", "'" . $bladeAsset . "'", $html);
    }

    foreach ($pageMap as $targetUrl => $pageData) {
        $local = $pageData['route'] === '/'
            ? '{{ url("/") }}'
            : '{{ url("' . $pageData['route'] . '") }}';
        $variants = [
            rtrim($targetUrl, '/'),
            rtrim($targetUrl, '/') . '/',
            makeRelativeUrl($targetUrl),
            rtrim(makeRelativeUrl($targetUrl), '/'),
        ];

        foreach (array_unique($variants) as $variant) {
            if ($variant === '') {
                continue;
            }

            $html = str_replace('href="' . $variant . '"', 'href="' . $local . '"', $html);
            $html = str_replace("href='" . $variant . "'", "href='" . $local . "'", $html);
            $html = str_replace('action="' . $variant . '"', 'action="' . $local . '"', $html);
            $html = str_replace("action='" . $variant . "'", "action='" . $local . "'", $html);
        }
    }

    $canonicalTag = '<link rel="canonical" href="' . rtrim($pageUrl, '/') . '" />';
    $html = preg_replace(
        '/<link[^>]+rel=["\']canonical["\'][^>]*>/i',
        $canonicalTag,
        $html,
        1
    ) ?? $html;

    $html = str_replace('@', '@@', $html);

    return $html;
}

function makeRelativeUrl(string $absoluteUrl): string
{
    $parts = parse_url($absoluteUrl);
    $path = $parts['path'] ?? '/';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';

    return $path . $query;
}

function normalizeUrl(string $url, string $baseUrl): string
{
    $url = trim($url);
    if ($url === '') {
        return $baseUrl;
    }

    if (str_starts_with($url, 'data:')) {
        return $url;
    }

    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }

    $baseParts = parse_url($baseUrl);
    if (! $baseParts || ! isset($baseParts['scheme'], $baseParts['host'])) {
        throw new RuntimeException("Cannot resolve relative URL against base: {$baseUrl}");
    }

    $origin = $baseParts['scheme'] . '://' . $baseParts['host'];
    if (str_starts_with($url, '/')) {
        return $origin . $url;
    }

    $basePath = $baseParts['path'] ?? '/';
    $directory = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';

    return $origin . $directory . $url;
}

function escapeSlashes(string $value): string
{
    return str_replace('/', '\\/', $value);
}

function isAssetUrl(string $url, string $pageUrl): bool
{
    if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:') || str_starts_with($url, '#')) {
        return false;
    }

    $normalized = normalizeUrl($url, $pageUrl);
    $host = parse_url($normalized, PHP_URL_HOST);
    if ($host !== parse_url(BASE_URL, PHP_URL_HOST)) {
        return false;
    }

    $path = strtolower((string) parse_url($normalized, PHP_URL_PATH));

    if (str_contains($path, '/feed') || str_contains($path, '/wp-json/')) {
        return false;
    }

    if (preg_match('/\.(css|js|png|jpe?g|gif|svg|webp|avif|woff2?|ttf|otf|eot|ico)(?:$|\?)/i', $normalized)) {
        return true;
    }

    return str_contains($path, '/wp-content/') || str_contains($path, '/wp-includes/');
}

function extractTitle(string $html): string
{
    if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
        return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
    }

    return '';
}

function writeSupportViews(string $viewsDir, array $pageManifest): void
{
    $layout = <<<'BLADE'
<!doctype html>
<html lang="en">
<head>
    @yield('head')
</head>
<body>
    @include('partials.header')
    @yield('content')
    @include('partials.footer')
</body>
</html>
BLADE;

    $header = <<<'BLADE'
{{-- Shared header extraction intentionally deferred to preserve pixel accuracy. --}}
BLADE;

    $footer = <<<'BLADE'
{{-- Shared footer extraction intentionally deferred to preserve pixel accuracy. --}}
BLADE;

    $sitemapItems = [];
    foreach ($pageManifest as $page) {
        $loc = $page['route'] === '/' ? "{{ url('/') }}" : "{{ url('{$page['route']}') }}";
        $sitemapItems[] = "    <url>\n        <loc>{$loc}</loc>\n    </url>";
    }

    $sitemap = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $sitemap .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    $sitemap .= implode("\n", $sitemapItems);
    $sitemap .= "\n</urlset>\n";

    file_put_contents($viewsDir . '/layouts/app.blade.php', $layout);
    file_put_contents($viewsDir . '/partials/header.blade.php', $header);
    file_put_contents($viewsDir . '/partials/footer.blade.php', $footer);
    file_put_contents($viewsDir . '/sitemap-xml.blade.php', $sitemap);
}

function writeRoutes(string $routeFile, array $pageManifest): void
{
    $lines = [
        '<?php',
        '',
        'use Illuminate\Support\Facades\Route;',
        '',
    ];

    foreach ($pageManifest as $page) {
        $path = $page['route'] === '/' ? '/' : $page['route'];
        $name = $page['view'];
        $lines[] = "Route::view('{$path}', '{$page['view']}')->name('{$name}');";
    }

    $lines[] = '';
    $lines[] = "Route::get('/sitemap.xml', function () {";
    $lines[] = "    return response()->view('sitemap-xml')->header('Content-Type', 'application/xml');";
    $lines[] = '});';
    $lines[] = '';

    file_put_contents($routeFile, implode("\n", $lines));
}

function writeManifest(string $manifestFile, array $pages, array $assets): void
{
    file_put_contents($manifestFile, json_encode([
        'generated_at' => date(DATE_ATOM),
        'pages' => $pages,
        'assets' => $assets,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
