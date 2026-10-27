<?php
require_once __DIR__ . '/../includes/public_layout.php';
header('Content-Type: application/xml; charset=UTF-8');
$content = site_store_dir() . '/content.json';
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (['/' => [__DIR__ . '/index.php', __DIR__ . '/../config/site_defaults.php', __DIR__ . '/../config/public_faq.php', $content], '/privacy.php' => [__DIR__ . '/privacy.php']] as $path => $files) {
    $loc = site_absolute_url($path);
    if ($loc === '') continue;
    $mtime = max(array_map(fn($f) => is_file($f) ? filemtime($f) : 0, $files));
    echo '  <url><loc>' . h($loc) . '</loc><lastmod>' . date('Y-m-d', $mtime) . '</lastmod></url>' . "\n";
}
echo "</urlset>\n";
