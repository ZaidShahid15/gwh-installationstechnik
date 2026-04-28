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

    $bladePlaceholders = [];
    $updated = protectBladeExpressions($original, $bladePlaceholders);
    $updated = cleanupSuspiciousMarkup($updated);
    $updated = restoreBladeExpressions($updated, $bladePlaceholders);
    $updated = repairMojibake($updated);
    $updated = normalizeWhitespace($updated);

    if ($updated !== $original) {
        file_put_contents($path, $updated);
        $changedFiles[] = $path;
    }
}

echo 'Updated ' . count($changedFiles) . " files.\n";
foreach ($changedFiles as $path) {
    echo $path . "\n";
}

function protectBladeExpressions(string $html, array &$placeholders): string
{
    $placeholders = [];
    $counter = 0;

    return preg_replace_callback('/(\{\{.*?\}\}|\{!!.*?!!\})/s', function (array $matches) use (&$placeholders, &$counter): string {
        $token = "__BLADE_PLACEHOLDER_{$counter}__";
        $placeholders[$token] = $matches[0];
        $counter++;
        return $token;
    }, $html) ?? $html;
}

function restoreBladeExpressions(string $html, array $placeholders): string
{
    return strtr($html, $placeholders);
}

function cleanupSuspiciousMarkup(string $html): string
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML(
        '<!DOCTYPE html><html><body>' . $html . '</body></html>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    if (!$loaded) {
        return $html;
    }

    $xpath = new DOMXPath($dom);
    while (true) {
        $container = firstContaminatedContainer($xpath);
        if (!$container instanceof DOMElement) {
            break;
        }

        $replacementNodes = collectMeaningfulNodes($xpath, $container);
        if ($replacementNodes === []) {
            break;
        }

        removeChildren($container);
        foreach ($replacementNodes as $replacementNode) {
            $container->appendChild($replacementNode);
        }
    }

    removeResidualSuspiciousNodes($xpath);

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body instanceof DOMElement) {
        return $html;
    }

    $output = '';
    foreach ($body->childNodes as $child) {
        $output .= $dom->saveHTML($child);
    }

    return $output;
}

function containerHasSuspiciousDescendant(DOMXPath $xpath, DOMElement $container): bool
{
    $nodes = $xpath->query(
        './/*[@data-testid or @data-message-author-role or contains(@class, "AIPRM__conversation__response") or contains(@class, "text-token-text-primary") or contains(@class, "agent-turn") or contains(@class, "text-message") or contains(@class, "markdown") or contains(@class, "react-scroll-to-bottom--css") or contains(@class, "gizmo-bot-avatar")]',
        $container
    );

    return $nodes instanceof DOMNodeList && $nodes->length > 0;
}

function firstContaminatedContainer(DOMXPath $xpath): ?DOMElement
{
    $containers = $xpath->query(
        '//*[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-container ") or contains(concat(" ", normalize-space(@class), " "), " elementor-tab-content ")]'
    );

    if (!$containers instanceof DOMNodeList) {
        return null;
    }

    foreach ($containers as $container) {
        if ($container instanceof DOMElement && containerHasSuspiciousDescendant($xpath, $container)) {
            return $container;
        }
    }

    return null;
}

function collectMeaningfulNodes(DOMXPath $xpath, DOMElement $container): array
{
    $allowedNames = ['p', 'ul', 'ol', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    $nodes = $xpath->query('.//*', $container);

    if (!$nodes instanceof DOMNodeList) {
        return [];
    }

    $selected = [];
    foreach ($nodes as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        if (!in_array(strtolower($node->tagName), $allowedNames, true)) {
            continue;
        }

        if (trim(preg_replace('/\s+/u', ' ', $node->textContent)) === '') {
            continue;
        }

        $hasAllowedAncestor = false;
        for ($parent = $node->parentNode; $parent instanceof DOMElement && $parent !== $container; $parent = $parent->parentNode) {
            if (in_array(strtolower($parent->tagName), $allowedNames, true)) {
                $hasAllowedAncestor = true;
                break;
            }
        }

        if (!$hasAllowedAncestor) {
            $selected[] = $node->cloneNode(true);
        }
    }

    return $selected;
}

function removeChildren(DOMElement $element): void
{
    while ($element->firstChild) {
        $element->removeChild($element->firstChild);
    }
}

function removeResidualSuspiciousNodes(DOMXPath $xpath): void
{
    $query = '//*[contains(@class, "AIPRM__conversation__response") or contains(@class, "text-token-text-primary") or contains(@class, "agent-turn") or contains(@class, "text-message") or contains(@class, "react-scroll-to-bottom--css") or contains(@class, "gizmo-bot-avatar") or @data-testid or @data-message-author-role]';
    $nodes = $xpath->query($query);

    if (!$nodes instanceof DOMNodeList) {
        return;
    }

    $queue = [];
    foreach ($nodes as $node) {
        $queue[] = $node;
    }

    foreach ($queue as $node) {
        if (!$node instanceof DOMElement || !$node->parentNode instanceof DOMNode) {
            continue;
        }

        $text = trim(html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '' || $node->tagName === 'article') {
            $node->parentNode->removeChild($node);
        }
    }
}

function repairMojibake(string $content): string
{
    $replacements = [
        'ÃƒÆ’–l-Heizungen' => 'Öl-Heizungen',
        'ÃƒÆ’–lheizung' => 'Ölheizung',
        'ÃƒÆ’–l-Heizung' => 'Öl-Heizung',
        'Wärmepumpen-ÃƒÆ’–lheizung' => 'Wärmepumpen-Ölheizung',
        'ÃƒÆ’Ã†â€™â€“l-Heizungen' => 'Öl-Heizungen',
        'ÃƒÆ’Ã†â€™â€“lheizung' => 'Ölheizung',
        'ÃƒÆ’Ã†â€™â€“l-Heizung' => 'Öl-Heizung',
        'Wärmepumpen-ÃƒÆ’Ã†â€™â€“lheizung' => 'Wärmepumpen-Ölheizung',
        'COÃ¢â€šâ€š' => 'CO2',
        'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¼' => 'Ã¼',
        'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¶' => 'Ã¶',
        'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¤' => 'Ã¤',
        'ÃƒÆ’Ã†â€™Ãƒâ€¦Ã¢â‚¬Å“' => 'Ãœ',
        'ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ' => 'Ã–',
        'ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾' => 'Ã„',
        'ÃƒÆ’Ã†â€™Ãƒâ€¦Ã‚Â¸' => 'ÃŸ',
        'ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ' => ' ',
        'ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ' => 'â€“',
        'ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“' => 'â€œ',
        'ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â' => 'â€',
        'ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ' => ' ',
        'ÃƒÆ’Ã‚Â¼' => 'Ã¼',
        'ÃƒÆ’Ã‚Â¶' => 'Ã¶',
        'ÃƒÆ’Ã‚Â¤' => 'Ã¤',
        'ÃƒÆ’Ã…â€œ' => 'Ãœ',
        'ÃƒÆ’Ã¢â‚¬â€œ' => 'Ã–',
        'ÃƒÆ’Ã¢â‚¬Å¾' => 'Ã„',
        'ÃƒÆ’Ã…Â¸' => 'ÃŸ',
        'Ãƒâ€šÃ‚Â ' => ' ',
        'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“' => 'â€“',
        'ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ' => 'â€“',
        'Ã¢â‚¬â€œ' => 'â€“',
        'Ã¢â‚¬â€' => 'â€”',
        'Ã¢â‚¬Å¾' => 'â€ž',
        'Ã¢â‚¬Å“' => 'â€œ',
        'Ã¢â‚¬Â' => 'â€',
        'Ã¢â‚¬â„¢' => 'â€™',
        'Ã¢â‚¬Ëœ' => 'â€˜',
        'Ã¢â‚¬Â¦' => 'â€¦',
        'Ã‚Â©' => 'Â©',
        'Ã‚Â®' => 'Â®',
        'Ã‚Â°' => 'Â°',
        'ÃƒÂ¼' => 'Ã¼',
        'ÃƒÂ¶' => 'Ã¶',
        'ÃƒÂ¤' => 'Ã¤',
        'ÃƒÅ“' => 'Ãœ',
        'Ãƒâ€“' => 'Ã–',
        'Ãƒâ€ž' => 'Ã„',
        'ÃƒÅ¸' => 'ÃŸ',
        'Ãƒ ' => 'Ã ',
    ];

    $content = strtr($content, $replacements);

    $content = preg_replace('/ÃƒÆ’(?:Ã†â€™)?–l-Heizungen/u', 'Öl-Heizungen', $content) ?? $content;
    $content = preg_replace('/ÃƒÆ’(?:Ã†â€™)?–lheizung/u', 'Ölheizung', $content) ?? $content;
    $content = preg_replace('/ÃƒÆ’(?:Ã†â€™)?–l-Heizung/u', 'Öl-Heizung', $content) ?? $content;
    $content = preg_replace('/Wärmepumpen-ÃƒÆ’(?:Ã†â€™)?–lheizung/u', 'Wärmepumpen-Ölheizung', $content) ?? $content;
    $content = preg_replace('/fÃ¼r/u', 'für', $content) ?? $content;
    $content = preg_replace('/grÃ¼ndliche/u', 'gründliche', $content) ?? $content;
    $content = preg_replace('/mÃ¶glich/u', 'möglich', $content) ?? $content;
    $content = preg_replace('/Ã¶sterreichisches/u', 'österreichisches', $content) ?? $content;
    $content = preg_replace('/HÃ¶chste/u', 'Höchste', $content) ?? $content;
    $content = preg_replace('/NiederÃ¶sterreich/u', 'Niederösterreich', $content) ?? $content;
    $content = preg_replace('/\bWÃƒÂ¤rmepumpen-Ã–lheizung\b/u', 'Wärmepumpen-Ölheizung', $content) ?? $content;
    $content = preg_replace('/Installateur bedingten/u', 'installateurbedingten', $content) ?? $content;
    $content = preg_replace('/24x7/u', '24/7', $content) ?? $content;
    $content = preg_replace('/\s+â€“\s+/u', ' – ', $content) ?? $content;
    $content = preg_replace('/\s{2,}/u', ' ', $content) ?? $content;

    return $content;
}

function normalizeWhitespace(string $content): string
{
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $content = preg_replace("/[ \t]+\n/u", "\n", $content) ?? $content;
    $content = preg_replace("/\n{3,}/u", "\n\n", $content) ?? $content;

    return $content;
}
