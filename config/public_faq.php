<?php
/**
 * Sintesi verificate il 25/10/2026, riscritte in tono colloquiale il 13/11/2026
 * senza cambiare i fatti. Regole ICBR e informazioni FC Internazionale restano separate.
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
                ['id' => 'faq-benefit', 'question' => 'Cosa ci guadagno a fare la tessera Inter Club?', 'kind' => 'official',
                    'answer' => 'Ti arriva il Welcome Kit e puoi partecipare a eventi e concorsi riservati ai soci. In più hai promozioni e prelazioni su alcune partite: ogni iniziativa ha le sue condizioni, quindi conviene leggerle volta per volta.',
                    'sources' => [$campaign]],
                ['id' => 'faq-kit-senior', 'question' => 'Cosa c’è nel Welcome Kit Senior 2026/27?', 'kind' => 'official',
                    'answer' => 'Quest’anno trovi un cuscino da viaggio, una mascherina per dormire, una cover impermeabile per il telefono e un beauty case. Vale fino a esaurimento scorte, quindi chi si tessera prima è più tranquillo.',
                    'sources' => [$campaign]],
                ['id' => 'faq-kit-junior', 'question' => 'E nel Welcome Kit Junior?', 'kind' => 'official',
                    'answer' => 'Per i più piccoli ci sono una sport bag e un portapranzo. Anche qui, fino a esaurimento scorte.',
                    'sources' => [$campaign]],
                ['id' => 'faq-esperienze', 'question' => 'Come si partecipa alle attività del giorno partita a San Siro?', 'kind' => 'official',
                    'answer' => 'Serve un biglietto in un settore che non sia il Secondo o il Terzo Anello Verde. Poi fai sapere al club che ti interessa: non si entra in automatico, va segnalato.',
                    'sources' => [$club]],
            ],
        ],
        [
            'id' => 'faq-stadio', 'title' => 'Plus, biglietti e San Siro',
            'items' => [
                ['id' => 'faq-plus', 'question' => 'Cosa cambia con l’upgrade Inter Club Plus?', 'kind' => 'official',
                    'answer' => 'Puoi comprare in anticipo i biglietti di tutte le partite in casa di Campionato e Champions League. Per averlo devi aderire anche al programma Loyalty INTERISTA e accettarne le condizioni.',
                    'sources' => [$club]],
                ['id' => 'faq-posto', 'question' => 'Se sono socio ho il posto allo stadio garantito?', 'kind' => 'official',
                    'answer' => 'Purtroppo no. La tessera ti dà agevolazioni e fasi di vendita dedicate, ma il biglietto va comprato e il posto non è assicurato.',
                    'sources' => [$club, $campaign]],
                ['id' => 'faq-due-plus', 'question' => 'Quota Inter Club Plus e biglietto PLUS sono la stessa cosa?', 'kind' => 'official',
                    'answer' => 'No, hanno solo lo stesso nome. L’upgrade Plus riguarda i vantaggi della tessera. Il biglietto PLUS invece è una tariffa che ti permette di cambiare una volta chi usa il biglietto, salvo restrizioni.',
                    'sources' => [$club, $faq]],
                ['id' => 'faq-acquisto', 'question' => 'Dove compro i biglietti ufficiali?', 'kind' => 'official',
                    'answer' => 'Nella sezione Biglietti di inter.it. Se la partita non è ancora in vendita, premi “Avvisami quando in vendita” e ti arriva la notifica.',
                    'sources' => [$faq]],
                ['id' => 'faq-siamo-noi', 'question' => 'La Siamo Noi e la tessera Inter Club sono la stessa cosa?', 'kind' => 'official',
                    'answer' => 'No, sono due tessere diverse, anche se puoi collegarle entrambe a MyInter. La Siamo Noi si compra sul suo portale, all’Inter Store Castello o nei punti Vivaticket abilitati.',
                    'sources' => [$faq]],
                ['id' => 'faq-cambio', 'question' => 'Non posso più andare: posso dare il biglietto a un amico?', 'kind' => 'official',
                    'answer' => 'Di solito sì, con il cambio utilizzatore online, che si apre circa 24 ore prima della partita. Si fa una volta sola, può avere un costo e in alcuni settori non è permesso.',
                    'sources' => [$faq]],
                ['id' => 'faq-rimborso', 'question' => 'Posso annullare l’acquisto o cambiare posto?', 'kind' => 'official',
                    'answer' => 'No. Una volta comprato il biglietto non ci sono rimborsi, e non si può cambiare né settore né posto.',
                    'sources' => [$faq]],
                ['id' => 'faq-bambini', 'question' => 'Come entra allo stadio un bambino sotto i cinque anni?', 'kind' => 'official',
                    'answer' => 'Fino ai 4 anni compiuti serve il biglietto Under 5, che si compra il giorno della partita agli sportelli indicati. Porta un documento del bambino; deve accompagnarlo un adulto con biglietto valido e non ha un posto assegnato.',
                    'sources' => [$faq]],
            ],
        ],
        [
            'id' => 'faq-myinter', 'title' => 'Tessera e MyInter',
            'items' => [
                ['id' => 'faq-associa', 'question' => 'Come collego la tessera Inter Club a MyInter?', 'kind' => 'official',
                    'answer' => 'Su MyInter apri MyCards, premi “Aggiungi tessera” e inserisci data di nascita e numero della tessera: devono corrispondere a quelli del profilo. Per i ragazzi sotto i 14 anni lo fa il genitore o il tutore.',
                    'sources' => [$faq]],
                ['id' => 'faq-sconti', 'question' => 'A cosa serve collegarla a MyInter?', 'kind' => 'official',
                    'answer' => 'Agli sconti riservati ai soci sull’Inter Online Store: senza collegamento non li vedi.',
                    'sources' => [$faq]],
                ['id' => 'faq-password', 'question' => 'Ho dimenticato la password di MyInter. Come faccio?', 'kind' => 'official',
                    'answer' => 'Vai su inter.it, premi LOGIN e poi “Hai dimenticato la password?”. Scrivi l’email dell’account e segui il link che ti arriva.',
                    'sources' => [$faq]],
            ],
        ],
        [
            'id' => 'faq-icbr', 'title' => 'Il nostro club',
            'items' => [
                ['id' => 'faq-online', 'question' => 'Posso fare la tessera online?', 'kind' => 'local',
                    'answer' => 'Da qui puoi chiederla, ma non pagarla. Ci scrivi dal modulo, ti ricontattiamo e chiudiamo adesione e pagamento insieme, in sede.',
                    'sources' => []],
                ['id' => 'faq-fuori-sede', 'question' => 'Non abito a Brindisi: quale tessera fa per me?', 'kind' => 'local',
                    'answer' => 'La Fuori sede, se sei residente fuori dal Comune di Brindisi, anche in un paese qui vicino. Conta la residenza, non quanti chilometri fai per arrivare.',
                    'sources' => []],
                ['id' => 'faq-prenota', 'question' => 'Come mi prenoto per vedere la partita in sede?', 'kind' => 'local',
                    'answer' => 'Dall’area soci. Prima il direttivo ti manda via email l’accesso; poi entri, scegli la partita e premi “Ci sarò”. Se la sala è già piena finisci in lista d’attesa e, appena qualcuno rinuncia, il posto è tuo. La sedia te la assegniamo noi e la vedi nel tuo profilo.',
                    'sources' => []],
                ['id' => 'faq-visita', 'question' => 'Quando posso passare a trovarvi?', 'kind' => 'local',
                    'answer' => 'Scrivici scegliendo “Visita alla sede” e mettiamoci d’accordo sul giorno, così trovi qualcuno ad aprirti.',
                    'sources' => []],
            ],
        ],
    ],
];
