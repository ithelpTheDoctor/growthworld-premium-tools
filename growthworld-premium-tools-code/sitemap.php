<?php
header("Content-Type: application/xml");

$sitemapFile = __DIR__ . '/sitemap.xml';
$txtFile = __DIR__ . '/sitemap.txt';

if (!file_exists($txtFile)) {
    http_response_code(404);
    echo "<!-- sitemap.txt not found -->";
    exit;
}

$urlsFromTxt = array_unique(array_filter(array_map('trim', file($txtFile))));
$today = date("Y-m-d");
$now = new DateTimeImmutable();

// Frequency to days mapping
$freqToDays = [
    'always'   => 0,
    'hourly'   => 1 / 24,
    'daily'    => 1,
    'weekly'   => 7,
    'monthly'  => 30,
    'yearly'   => 365,
    'never'    => INF,
];

// === Load or create XML
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;

if (file_exists($sitemapFile)) {
    $dom->load($sitemapFile);
    $urlset = $dom->getElementsByTagName("urlset")->item(0);
    if (!$urlset) {
        $urlset = $dom->createElement("urlset");
        $dom->appendChild($urlset);
    }
} else {
    $urlset = $dom->createElement("urlset");
    $dom->appendChild($urlset);
    $urlset->setAttribute("xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9");
}

$existingUrlsMap = [];
foreach ($urlset->getElementsByTagName("url") as $urlElement) {
    $loc = $urlElement->getElementsByTagName("loc")->item(0);
    if (!$loc) continue;
    $url = trim($loc->nodeValue);
    $existingUrlsMap[$url] = $urlElement;

    // Handle lastmod intelligently
    $lastmodNode = $urlElement->getElementsByTagName("lastmod")->item(0);
    $changefreqNode = $urlElement->getElementsByTagName("changefreq")->item(0);
    $changefreq = $changefreqNode ? strtolower($changefreqNode->nodeValue) : 'monthly';
    $maxAgeDays = $freqToDays[$changefreq] ?? 30;

    if ($lastmodNode) {
        $lastmodDate = DateTimeImmutable::createFromFormat('Y-m-d', $lastmodNode->nodeValue);
        if ($lastmodDate !== false) {
            $diffDays = $now->diff($lastmodDate)->days;
            if ($diffDays >= $maxAgeDays) {
                $lastmodNode->nodeValue = $today;
            }
        } else {
            $lastmodNode->nodeValue = $today;
        }
    } else {
        $newLastmod = $dom->createElement("lastmod", $today);
        $urlElement->appendChild($newLastmod);
    }
}

// === Add new URLs with fresh data
foreach ($urlsFromTxt as $url) {
    if (!isset($existingUrlsMap[$url])) {
        $urlElement = $dom->createElement("url");

        $loc = $dom->createElement("loc", htmlspecialchars($url));
        $lastmod = $dom->createElement("lastmod", $today);
        $changefreq = $dom->createElement("changefreq", "monthly");
        $priority = $dom->createElement("priority", "0.8");

        $urlElement->appendChild($loc);
        $urlElement->appendChild($lastmod);
        $urlElement->appendChild($changefreq);
        $urlElement->appendChild($priority);

        $urlset->appendChild($urlElement);
    }
}

// === Save and return XML
$dom->save($sitemapFile);
echo $dom->saveXML();
