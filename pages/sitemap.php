<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/site.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex');

$root = dirname(__DIR__);
$lastmod = static function (string $slug) use ($root): string {
    $files = [$root . '/backend/site.php', $root . '/pages/' . $slug . '.php'];
    $times = array_map(static fn(string $f): int => is_file($f) ? (int) filemtime($f) : 0, $files);
    return gmdate('Y-m-d', max($times));
};

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">', "\n";
foreach (ait_pages() as $slug => $meta) {
    echo "  <url>\n";
    echo '    <loc>', htmlspecialchars(ait_public_canonical_url($slug), ENT_XML1), "</loc>\n";
    echo '    <lastmod>', $lastmod($slug), "</lastmod>\n";
    echo '    <changefreq>', $meta['changefreq'], "</changefreq>\n";
    echo '    <priority>', $meta['priority'], "</priority>\n";
    if (in_array($slug, ['home', 'about'], true)) {
        echo '    <image:image><image:loc>', htmlspecialchars(ait_site_url() . '/assets/images/campus/main-gate-1536.webp', ENT_XML1), "</image:loc></image:image>\n";
    }
    echo "  </url>\n";
}
echo "</urlset>\n";
