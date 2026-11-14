# Database WhatsApp API

La migrazione 018_whatsapp_api.sql aggiunge strutture separate dallo storico assistito. Non modifica i soci e non concede consensi automaticamente. Nessun invio viene effettuato dalla migrazione.

## Installazione attuale del branch

Su database esistente: backup e applicazione della migrazione 018 dopo le migrazioni pregresse necessarie al gestionale.
Su nuova installazione di questo branch: importare database/schema.sql e poi database/migrations/018_whatsapp_api.sql. Lo schema principale non incorpora ancora queste tabelle. La 018 include anche impostazioni senza inserire valori o credenziali.

## Contratto applicativo da implementare

- Il consenso è riferito al socio e al numero normalizzato: un cambio di numero richiede una nuova verifica. Salvare stato corrente e relativo evento nella stessa transazione. Non desumere il consenso da un numero o da un tesseramento.
- I modelli sono inattivi per default. La presenza nel database non dimostra l’approvazione Meta.
- Un lotto è legato a operatore e stagione, con snapshot del modello e token conservato solo come hash. Il servizio deve verificare scadenza, permessi e corrispondenza dell’anteprima prima della conferma.
- Un numero compare al massimo una volta per lotto: in caso di numeri condivisi fra familiari, mostrare il conflitto e chiedere una selezione esplicita.
- Il servizio deve acquisire il messaggio con UPDATE condizionale da preparato a in_corso e commit prima della richiesta API. In caso di crash dopo il claim non riattivarlo automaticamente: esito potenzialmente incerto.
- I vincoli impediscono duplicati nello stesso lotto, non invii ripetuti in lotti distinti.
- wa_webhook_eventi conserva metadati minimi per deduplicazione, non testi delle chat né payload integrali. Verificare firma, account e numero prima di inserirli.
- Definire una politica di conservazione per telefoni, anteprime e payload; non conservare token in queste tabelle.

I test CLI database rifiutano di partire senza ICBR_WA_DB_TEST=1 e se icbr_gestionale esiste già. Usano esclusivamente il servizio MySQL temporaneo della pipeline; non eseguirli sul server del club. Il test dei claim è sequenziale, non una prova di concorrenza con connessioni multiple. Il flusso applicativo completo resta da implementare.
