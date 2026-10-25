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
            'id' => 'faq-soci', 'title' => 'Essere socio, davvero.',
            'intro' => 'Kit, opportunità e momenti da vivere insieme.',
            'items' => [
                ['id' => 'faq-benefit', 'question' => 'Cosa offre il tesseramento Inter Club?', 'kind' => 'official',
                    'answer' => 'La campagna 2026/27 prevede iniziative ed eventi dedicati, concorsi, promozioni, agevolazioni e prelazioni per partite selezionate, secondo disponibilità e condizioni delle singole iniziative.',
                    'sources' => [$campaign]],
                ['id' => 'faq-kit-senior', 'question' => 'Cosa contiene il Welcome Kit Senior 2026/27?', 'kind' => 'official',
                    'answer' => 'Il kit Senior comprende un cuscino da viaggio, una mascherina da notte, una cover per cellulare waterproof e un beauty case, fino a esaurimento scorte.',
                    'sources' => [$campaign]],
                ['id' => 'faq-kit-junior', 'question' => 'E nel Welcome Kit Junior cosa c’è?', 'kind' => 'official',
                    'answer' => 'Per i soci Junior il kit 2026/27 comprende una sport bag e una lunchbox, fino a esaurimento scorte.',
                    'sources' => [$campaign]],
                ['id' => 'faq-kit-scorte', 'question' => 'Il Welcome Kit è sempre disponibile?', 'kind' => 'official',
                    'answer' => 'No: la campagna ufficiale indica espressamente “fino a esaurimento scorte”, quindi il kit non va considerato disponibile senza limiti.',
                    'sources' => [$campaign]],
                ['id' => 'faq-esperienze', 'question' => 'Come si partecipa alle attività del giorno partita?', 'kind' => 'official',
                    'answer' => 'Inter invita i soci interessati ad acquistare un biglietto in un settore diverso dal Secondo o Terzo Anello Verde e a contattare il proprio Inter Club per manifestare interesse alle esperienze disponibili, senza partecipazione automatica.',
                    'sources' => [$club]],
            ],
        ],
        [
            'id' => 'faq-stadio', 'title' => 'Plus, biglietti e San Siro.',
            'intro' => 'Prima di partire, facciamo chiarezza.',
            'items' => [
                ['id' => 'faq-plus', 'question' => 'Cosa cambia con l’upgrade Inter Club Plus?', 'kind' => 'official',
                    'answer' => 'La pagina ufficiale Inter Club indica una vendita anticipata dedicata ai soci Plus per tutte le partite casalinghe di Campionato e Champions League, con beneficio subordinato alla partecipazione al programma Loyalty INTERISTA e ai relativi termini e condizioni.',
                    'sources' => [$club]],
                ['id' => 'faq-posto', 'question' => 'Essere socio significa avere un posto garantito allo stadio?', 'kind' => 'official',
                    'answer' => 'I benefit ufficiali descrivono agevolazioni e fasi di vendita, non un biglietto compreso nella quota o un posto garantito: prima di organizzarti, verifica le condizioni e le disponibilità della partita.',
                    'sources' => [$club, $campaign]],
                ['id' => 'faq-due-plus', 'question' => 'Quota Inter Club Plus e biglietto PLUS sono la stessa cosa?', 'kind' => 'official',
                    'answer' => 'No: l’upgrade Inter Club Plus riguarda i benefit del tesseramento, mentre PLUS è anche il nome di una tariffa di biglietto che include un cambio utilizzatore, consentito una sola volta e salvo restrizioni della Società o delle autorità.',
                    'sources' => [$club, $faq]],
                ['id' => 'faq-acquisto', 'question' => 'Dove si acquistano i biglietti ufficiali?', 'kind' => 'official',
                    'answer' => 'Inter indica la sezione Biglietti del proprio sito e, per le gare non ancora in vendita, il pulsante “Avvisami quando in vendita” per ricevere una notifica via email.',
                    'sources' => [$faq]],
                ['id' => 'faq-siamo-noi', 'question' => 'La Siamo Noi è la stessa tessera di Inter Club?', 'kind' => 'official',
                    'answer' => 'No: sono tessere distinte, entrambe associabili a MyInter, e per acquistare o rinnovare la Siamo Noi le FAQ Inter rimandano al portale dedicato, all’Inter Store Castello o ai punti Vivaticket abilitati.',
                    'sources' => [$faq]],
                ['id' => 'faq-cambio', 'question' => 'Se non posso andare, posso cedere il biglietto?', 'kind' => 'official',
                    'answer' => 'Il cambio utilizzatore è generalmente disponibile online da circa 24 ore prima della partita, una sola volta e con eventuali costi e restrizioni: alcuni settori e titoli non sono cedibili, quindi controlla sempre le regole della tua gara.',
                    'sources' => [$faq]],
                ['id' => 'faq-rimborso', 'question' => 'Posso annullare l’acquisto o cambiare posto?', 'kind' => 'official',
                    'answer' => 'Le FAQ Inter non prevedono annullamento e rimborso dell’acquisto né cambio di settore o posto dopo l’acquisto; l’eventuale cambio utilizzatore segue una procedura separata con le proprie condizioni.',
                    'sources' => [$faq]],
                ['id' => 'faq-bambini', 'question' => 'Come funziona l’ingresso dei bambini sotto i cinque anni?', 'kind' => 'official',
                    'answer' => 'Per i bambini fino a 4 anni compiuti Inter prevede uno speciale biglietto Under 5 acquistabile il giorno partita agli sportelli indicati, con documento del bambino, accompagnamento di un adulto con titolo valido e senza posto assegnato; costi e limitazioni vanno verificati nelle FAQ ufficiali.',
                    'sources' => [$faq]],
            ],
        ],
        [
            'id' => 'faq-myinter', 'title' => 'La tessera, anche su MyInter.',
            'intro' => 'Il tuo profilo Inter, distinto dall’area riservata ICBR.',
            'items' => [
                ['id' => 'faq-associa', 'question' => 'Come associo la tessera Inter Club a MyInter?', 'kind' => 'official',
                    'answer' => 'Accedi a MyInter, apri MyCards, scegli “Aggiungi tessera” e inserisci data di nascita e numero della tessera: i dati devono corrispondere a quelli del profilo, con le indicazioni specifiche del tutore per i minori di 14 anni.',
                    'sources' => [$faq]],
                ['id' => 'faq-sconti', 'question' => 'Perché conviene associare la tessera al profilo?', 'kind' => 'official',
                    'answer' => 'Le FAQ Inter indicano che associare la tessera Inter Club nella sezione MyCards permette di usufruire degli sconti dedicati sull’Inter Online Store.',
                    'sources' => [$faq]],
                ['id' => 'faq-password', 'question' => 'Ho dimenticato la password MyInter: come la recupero?', 'kind' => 'official',
                    'answer' => 'Sul sito Inter apri LOGIN, seleziona “Hai dimenticato la password?” e inserisci l’email del tuo account per ricevere il collegamento con cui impostare una nuova password.',
                    'sources' => [$faq]],
            ],
        ],
        [
            'id' => 'faq-icbr', 'title' => 'Qui, a Brindisi.',
            'intro' => 'Indicazioni del nostro club, non regole nazionali Inter.',
            'items' => [
                ['id' => 'faq-fuori-sede', 'question' => 'Chi può scegliere la formula Fuori sede?', 'kind' => 'local',
                    'answer' => 'Chi risiede fuori dal Comune di Brindisi, anche in un comune vicino: il requisito è la residenza fuori dal territorio comunale, non la distanza dalla sede.',
                    'sources' => []],
                ['id' => 'faq-online', 'question' => 'Posso completare il tesseramento da questo sito?', 'kind' => 'local',
                    'answer' => 'Da qui puoi chiedere informazioni e lasciare un recapito; adesione, verifica dei dati e pagamento si concordano con il direttivo, perché questo sito non incassa pagamenti.',
                    'sources' => []],
                ['id' => 'faq-visita', 'question' => 'Quando posso passare in sede?', 'kind' => 'local',
                    'answer' => 'Scrivici dal modulo scegliendo “Visita alla sede”: ci mettiamo d’accordo prima di vederci, così trovi qualcuno ad accoglierti.',
                    'sources' => []],
            ],
        ],
    ],
];
