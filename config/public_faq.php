<?php
/**
 * Sintesi verificate il 25/10/2026.
 * Regole ICBR e informazioni FC Internazionale restano separate.
 */
$club = ['label' => 'Inter.it · Inter Club', 'url' => 'https://www.inter.it/it/inter-club'];
$campaign = ['label' => 'Inter.it · Tesseramento 2026/27', 'url' => 'https://www.inter.it/it/notizie/inter-club-campagna-tesseramenti-2026-2027'];
$faq = ['label' => 'Inter.it · FAQ ufficiali', 'url' => 'https://www.inter.it/it/faq'];
return [
    'verified_on' => '2026-10-25',
    'groups' => [
        [
            'id' => 'faq-soci', 'title' => 'Vantaggi e Welcome Kit',
            'items' => [
                ['id' => 'faq-benefit', 'question' => 'Cosa offre il tesseramento Inter Club?', 'kind' => 'official',
                    'answer' => 'Welcome Kit, eventi e concorsi riservati, promozioni e prelazioni su partite selezionate, secondo le condizioni di ogni iniziativa.',
                    'sources' => [$campaign]],
                ['id' => 'faq-kit-senior', 'question' => 'Cosa contiene il Welcome Kit Senior 2026/27?', 'kind' => 'official',
                    'answer' => 'Cuscino da viaggio, mascherina da notte, cover waterproof per cellulare e beauty case, fino a esaurimento scorte.',
                    'sources' => [$campaign]],
                ['id' => 'faq-kit-junior', 'question' => 'Cosa contiene il Welcome Kit Junior 2026/27?', 'kind' => 'official',
                    'answer' => 'Sport bag e lunchbox, fino a esaurimento scorte.',
                    'sources' => [$campaign]],
                ['id' => 'faq-esperienze', 'question' => 'Come si partecipa alle attività del giorno partita?', 'kind' => 'official',
                    'answer' => 'Acquista un biglietto in un settore diverso dal Secondo o Terzo Anello Verde e segnala il tuo interesse al club: la partecipazione non è automatica.',
                    'sources' => [$club]],
            ],
        ],
        [
            'id' => 'faq-stadio', 'title' => 'Plus, biglietti e San Siro',
            'items' => [
                ['id' => 'faq-plus', 'question' => 'Cosa cambia con l’upgrade Inter Club Plus?', 'kind' => 'official',
                    'answer' => 'Vendita anticipata per tutte le gare casalinghe di Campionato e Champions League, legata all’adesione al programma Loyalty INTERISTA e ai relativi termini.',
                    'sources' => [$club]],
                ['id' => 'faq-posto', 'question' => 'Essere socio significa avere un posto garantito allo stadio?', 'kind' => 'official',
                    'answer' => 'No: la tessera dà accesso ad agevolazioni e fasi di vendita dedicate, non a un biglietto incluso o a un posto garantito.',
                    'sources' => [$club, $campaign]],
                ['id' => 'faq-due-plus', 'question' => 'Quota Inter Club Plus e biglietto PLUS sono la stessa cosa?', 'kind' => 'official',
                    'answer' => 'No: l’upgrade Plus riguarda i benefit della tessera; PLUS è anche una tariffa di biglietto che include un solo cambio utilizzatore, salvo restrizioni.',
                    'sources' => [$club, $faq]],
                ['id' => 'faq-acquisto', 'question' => 'Dove si acquistano i biglietti ufficiali?', 'kind' => 'official',
                    'answer' => 'Nella sezione Biglietti di inter.it; per le gare non ancora in vendita usa “Avvisami quando in vendita”.',
                    'sources' => [$faq]],
                ['id' => 'faq-siamo-noi', 'question' => 'La Siamo Noi è la stessa tessera di Inter Club?', 'kind' => 'official',
                    'answer' => 'No, sono tessere distinte (entrambe associabili a MyInter). La Siamo Noi si acquista sul portale dedicato, all’Inter Store Castello o nei punti Vivaticket abilitati.',
                    'sources' => [$faq]],
                ['id' => 'faq-cambio', 'question' => 'Se non posso andare, posso cedere il biglietto?', 'kind' => 'official',
                    'answer' => 'Di norma sì, con il cambio utilizzatore online da circa 24 ore prima della gara, una sola volta e con possibili costi; alcuni settori non sono cedibili.',
                    'sources' => [$faq]],
                ['id' => 'faq-rimborso', 'question' => 'Posso annullare l’acquisto o cambiare posto?', 'kind' => 'official',
                    'answer' => 'No: dopo l’acquisto non sono previsti rimborsi né cambi di settore o posto.',
                    'sources' => [$faq]],
                ['id' => 'faq-bambini', 'question' => 'Come funziona l’ingresso dei bambini sotto i cinque anni?', 'kind' => 'official',
                    'answer' => 'Fino a 4 anni compiuti serve il biglietto Under 5, acquistabile il giorno della gara agli sportelli indicati, con documento del bambino e un adulto con biglietto valido; senza posto assegnato.',
                    'sources' => [$faq]],
            ],
        ],
        [
            'id' => 'faq-myinter', 'title' => 'Tessera e MyInter',
            'items' => [
                ['id' => 'faq-associa', 'question' => 'Come associo la tessera Inter Club a MyInter?', 'kind' => 'official',
                    'answer' => 'In MyInter apri MyCards, scegli “Aggiungi tessera” e inserisci data di nascita e numero tessera, coerenti con il profilo. Per gli under 14 procede il tutore.',
                    'sources' => [$faq]],
                ['id' => 'faq-sconti', 'question' => 'Perché associare la tessera a MyInter?', 'kind' => 'official',
                    'answer' => 'Per ottenere gli sconti riservati sull’Inter Online Store.',
                    'sources' => [$faq]],
                ['id' => 'faq-password', 'question' => 'Ho dimenticato la password MyInter: come la recupero?', 'kind' => 'official',
                    'answer' => 'Su inter.it apri LOGIN, scegli “Hai dimenticato la password?” e inserisci l’email dell’account.',
                    'sources' => [$faq]],
            ],
        ],
        [
            'id' => 'faq-icbr', 'title' => 'Inter Club Brindisi',
            'items' => [
                ['id' => 'faq-online', 'question' => 'Posso tesserarmi online?', 'kind' => 'local',
                    'answer' => 'No: dal modulo richiedi la tessera, poi adesione e pagamento si completano con il direttivo.',
                    'sources' => []],
                ['id' => 'faq-visita', 'question' => 'Quando posso passare in sede?', 'kind' => 'local',
                    'answer' => 'Scrivici scegliendo “Visita alla sede”: concordiamo il giorno e ti accogliamo noi.',
                    'sources' => []],
            ],
        ],
    ],
];
