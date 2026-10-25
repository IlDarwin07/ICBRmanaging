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
$legacy = site_upgrade_content([
    'tessera_3_testo' => 'Lontano da Brindisi, ma sempre dei nostri. La distanza non cambia i colori.',
    'tessera_3_dettagli' => 'Vivi lontano da Brindisi e vuoi restare vicino al nostro club? Chiedici della formula Fuori sede: verifichiamo insieme i requisiti e le modalità di partecipazione previste, senza dare per scontati gli stessi servizi delle altre formule.',
]);
foreach (['testo', 'dettagli'] as $field) {
    if ($legacy["tessera_3_$field"] !== $defaults["tessera_3_$field"]) throw new RuntimeException('Testo standard precedente non aggiornato.');
    if (!str_contains($defaults["tessera_3_$field"], 'Comune di Brindisi')) throw new RuntimeException('Requisito territoriale assente.');
}
$custom = ['tessera_3_dettagli' => 'Testo personalizzato del direttivo'];
if (site_upgrade_content($custom) !== $custom) throw new RuntimeException('Personalizzazione sovrascritta.');
echo "OK Fuori sede: Comune di Brindisi, aggiornamento vecchi testi e conservazione personalizzazioni.\n";
$oldMemberships = [
    'tessera_4_testo' => 'Gli stessi colori, in famiglia. La passione nerazzurra si tramanda.',
    'tessera_4_dettagli' => 'Family è la voce del listino dedicata alle adesioni familiari. Prima di iscrivere più persone, verifica con il direttivo abbinamenti e requisiti: la quota indicata non va considerata automaticamente un prezzo per tutto il nucleo familiare.',
    'tessera_5_testo' => 'Piccoli tifosi, grande passione. Il nerazzurro comincia presto.',
    'tessera_5_dettagli' => 'La formula Junior è dedicata ai soci più giovani. Ti aiutiamo a verificare la fascia d’età ammessa per la stagione e ciò che serve per iscrivere un minore: scrivici senza inviare dati o documenti del bambino nel modulo pubblico.',
];
foreach ($oldMemberships as $key => $oldValue) {
        if (site_upgrade_content([$key => $oldValue])[$key] !== $defaults[$key]) throw new RuntimeException('Testo Family/Junior precedente non aggiornato.');
        if (site_upgrade_content([$key => 'Personalizzazione'])[$key] !== 'Personalizzazione') throw new RuntimeException('Personalizzazione Family/Junior persa.');
}
if (!str_contains($defaults['tessera_4_dettagli'], 'Fedelissimo') || !str_contains($defaults['tessera_5_dettagli'], 'meno di 14 anni')) throw new RuntimeException('Requisiti Family/Junior mancanti.');
echo "OK Family con Fedelissimo e Junior under 14, aggiornamento testi e personalizzazioni.\n";
echo "Tutti i controlli sui contenuti sono superati. Nessuna connessione al database.\n";
