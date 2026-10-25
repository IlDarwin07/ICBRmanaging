<?php
require_once __DIR__.'/../includes/site_content.php';
$defaults = require __DIR__.'/../config/site_defaults.php';
$old = [
    'hero_testo' => 'La partita sullo schermo, gli amici accanto. A Brindisi la viviamo così. Se ti va di esserci, c’è posto anche per te.',
    'hero_pulsante' => 'Vieni a conoscerci',
    'tesseramento_titolo' => "Nuova stagione.\nStessa voglia di esserci.",
    'tessera_1_testo' => 'Il club è già un po’ casa tua? Scrivici e scopri come vivere insieme la nuova stagione.',
    'tessera_2_testo' => 'Hai voglia di far parte del gruppo e dare il tuo sostegno? Ti raccontiamo come funziona.',
    'sede_titolo' => "Qui ci sentiamo\na casa.",
    'sede_testo' => 'Siamo in via San Benedetto, nel centro di Brindisi. Vuoi passare a salutarci? Scrivici prima, così ci mettiamo d’accordo.',
    'contatti_titolo' => "Due chiacchiere,\npoi si parte.",
    'contatti_testo' => 'Tesseramento, rinnovo o semplicemente voglia di conoscerci? Scrivici qui. Ti risponde il direttivo, non un bot.',
];
foreach ($old as $key => $value) {
    if (site_upgrade_content([$key=>$value])[$key] !== $defaults[$key]) throw new RuntimeException("Aggiornamento mancante: $key");
    if (site_upgrade_content([$key=>str_replace("\n", "\r\n", $value)])[$key] !== $defaults[$key]) throw new RuntimeException("Aggiornamento Windows mancante: $key");
    if (site_upgrade_content([$key=>'Testo personalizzato'])[$key] !== 'Testo personalizzato') throw new RuntimeException("Personalizzazione persa: $key");
}
$home = file_get_contents(__DIR__.'/../public/index.php');
if (str_contains($home,'Parliamone insieme') || str_contains($home,'SCRIVICI, SIAMO QUI')) throw new RuntimeException('CTA precedente ancora presente.');
if (substr_count($home,'Contatta il direttivo') < 3) throw new RuntimeException('CTA incompleta.');
if (!str_contains($defaults['contatti_titolo'],'famiglia nerazzurra')) throw new RuntimeException('Titolo marketing mancante.');
echo "OK copy marketing: aggiornamento default, personalizzazioni preservate e CTA coerenti.\n";
