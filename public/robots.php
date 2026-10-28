<?php
require_once __DIR__ . '/../includes/public_layout.php';
header('Content-Type: text/plain; charset=UTF-8');
$base = base_url();
echo "User-agent: *\n";
foreach (['/dashboard.php','/login.php','/soci/','/stagioni/','/tesseramenti/','/tipologie/','/prima_nota/','/importazioni/','/messaggi/','/richieste/','/attivita/','/utenti/','/sito/','/articoli/'] as $path) {
    echo 'Disallow: ' . $base . $path . "\n";
}
$sitemap = site_absolute_url('/sitemap.php');
if ($sitemap !== '') echo "\nSitemap: " . $sitemap . "\n";
