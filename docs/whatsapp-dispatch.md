# Servizio di invio WhatsApp

ICBRWhatsAppDispatch è un servizio interno, non un endpoint pubblico e non un cron. Il costruttore riceve PDO e l’ID dell’operatore ricavato dalla sessione autenticata, mai dal form. I gestori HTTP devono chiamare require_login e verify_csrf_token prima delle scritture. Il servizio rilegge account attivo e permesso whatsapp.send dal database; gli admin sono ammessi, gli account con ambito comuni sono esclusi. Il catalogo di assegnazione del permesso e i pulsanti web restano da integrare.

prepare(stagione, modello, array di ID tesseramento interi, filtro) crea un’anteprima persistente valida dieci minuti. Non invia. Restituisce ID lotto, token in chiaro da custodire nella sessione e anteprime; nel database salva solo l’hash del token. Massimo 500 selezionati e un destinatario per numero. Consensi mancanti o numeri condivisi bloccano la preparazione: l’interfaccia deve mostrarli e far correggere la selezione. Il filtro residuo comprende da_pagare e acconto, non anomalie di rimborso.

confirm(id, token) controlla proprietario, scadenza, modello, configurazione, consenso, telefono e contenuto personalizzato. Non invia. Un pagamento che cambia l’importo mostrato rende necessaria una nuova anteprima. La seconda conferma dello stesso lotto non ripete la transizione.

sendOne(id, token) acquisisce al massimo un messaggio già confermato con UPDATE condizionale e commit prima della rete. Ricontrolla l’idoneità subito prima del claim. Invia esclusivamente tramite il trasporto API se ICBR_WA_SEND_ENABLED=1 e token e cURL sono disponibili. Nessun ciclo in background, retry automatico o attivazione delle variabili server è introdotto da questo commit. Tutti i passi devono terminare entro la finestra di dieci minuti; i rimanenti richiedono una nuova anteprima.

Il risultato accettato non prova consegna o lettura. Timeout ed eccezioni di trasporto diventano incerto. Un crash dopo il claim può lasciare in_corso: non rimetterlo automaticamente in coda. Se il salvataggio dell’esito fallisce, verificare prima di ogni ulteriore azione. La verifica del consenso è immediatamente precedente al claim; una revoca arrivata dopo l’inizio della richiesta non può annullare una chiamata già partita.

I test usano un trasporto simulato consentito solo in CLI con ICBR_WA_DB_TEST=1; non usano credenziali o rete Meta. Restano da collaudare concorrenza con connessioni multiple, gestori HTTP/CSRF, webhook e configurazione Meta reale. Il servizio non aggiorna lo storico assistito e non è ancora collegato ai pulsanti del gestionale.
