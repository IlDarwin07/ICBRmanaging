<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/../includes/site_content.php';
$defaults=require __DIR__.'/../config/site_defaults.php';
$types=site_membership_types();
if (count($types)!==6 || count(array_unique(array_column($types,'tipo')))!==6) throw new RuntimeException('Servono sei formule distinte.');
$schema=file_get_contents(__DIR__.'/../database/schema.sql');
foreach ($types as $i=>$type) {
    foreach (['titolo','testo','quota','dettagli'] as $field) {
        $key="tessera_{$i}_{$field}";
        if (!is_string($defaults[$key] ?? null) || trim($defaults[$key])==='') throw new RuntimeException("Campo mancante: $key");
        if (!isset(site_content()[$key])) throw new RuntimeException("Fallback contenuti non disponibile: $key");
    }
    $quota=(float)preg_replace('/[^\d.]/','',$defaults["tessera_{$i}_quota"]);
    $pattern="/\\('".preg_quote($type['tipo'],'/')."',\\s*'[^']*',\\s*".preg_quote(number_format($quota,2,'.',''),'/')."\\s*,/";
    if (!preg_match($pattern,$schema)) throw new RuntimeException("Quota iniziale non coerente con schema: {$type['nome']}");
    if ($type['rinnovo']!==null) {
        $renew=(float)preg_replace('/[^\d.]/','',$type['rinnovo']);
        $pattern="/\\('".preg_quote($type['tipo'].'_rinnovo','/')."',\\s*'[^']*',\\s*".preg_quote(number_format($renew,2,'.',''),'/')."\\s*,/";
        if (!preg_match($pattern,$schema)) throw new RuntimeException("Rinnovo storico non coerente: {$type['nome']}");
    }
    echo "OK {$type['nome']}: campi, fallback, quota e rinnovo storico.\n";
}
if (h('<script>alert(1)</script>')!=='&lt;script&gt;alert(1)&lt;/script&gt;') throw new RuntimeException('Escape HTML non corretto.');
echo "Tutti i controlli sui contenuti sono superati. Nessuna connessione al database.\n";
