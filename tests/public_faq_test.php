<?php
if (PHP_SAPI !== 'cli') exit;
$faq = require __DIR__ . '/../config/public_faq.php';
$ids = [];
$official = $local = 0;
foreach ($faq['groups'] as $group) {
    foreach ($group['items'] as $item) {
        if (!preg_match('/\Afaq-[a-z-]+\z/', $item['id']) || isset($ids[$item['id']])) throw new RuntimeException('ID FAQ mancante o duplicato.');
        $ids[$item['id']] = true;
        if (trim($item['question']) === '' || trim($item['answer']) === '') throw new RuntimeException('FAQ vuota.');
        if ($item['kind'] === 'official') {
            $official++;
            if (!$item['sources']) throw new RuntimeException('Risposta ufficiale senza fonte.');
            foreach ($item['sources'] as $source) {
                $url = parse_url($source['url']);
                if (($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== 'www.inter.it') throw new RuntimeException('Fonte non ufficiale.');
            }
        } elseif ($item['kind'] === 'local') {
            $local++;
            if ($item['sources']) throw new RuntimeException('Regola locale attribuita a una fonte esterna.');
        } else throw new RuntimeException('Tipo di risposta sconosciuto.');
        if ($item['id'] === 'faq-plus' && !str_contains($item['answer'], 'INTERISTA')) throw new RuntimeException('Condizione Loyalty assente.');
        if ($item['id'] === 'faq-fuori-sede' && !str_contains($item['answer'], 'Comune di Brindisi')) throw new RuntimeException('Requisito locale errato.');
    }
}
if ($official < 15 || $local < 3) throw new RuntimeException('Copertura insufficiente.');
echo "OK: $official FAQ ufficiali con fonti inter.it e $local FAQ locali distinte; ID univoci e condizione Loyalty verificati.\n";
