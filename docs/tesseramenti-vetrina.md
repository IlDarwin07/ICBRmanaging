# Le formule di tesseramento nella vetrina

La sezione pubblica presenta sei formule, con introduzione, spiegazione, quota di riferimento e richiesta informazioni dedicata. Non vende tessere o biglietti e non effettua prenotazioni.

## Quote di riferimento e provenienza

Le quote iniziali riprendono il listino 2026/27 già presente in `database/schema.sql` e nella migrazione 007: Fedelissimo 95 €, Sostenitore 55 €, Fuori sede 50 €, Family 50 €, Junior 25 € e Quota Plus +35 €. Non sono prezzi ricavati dal sito di Inter o un listino letto in tempo reale dal database.

La pagina distingue le cinque formule di adesione dall’upgrade Plus, aggiuntivo alla tessera Senior. I cinque rinnovi agevolati entro il 30 giugno 2026 sono riportati soltanto nella domanda dedicata come importi storici scaduti: 90, 50, 45, 45 e 25 € rispettivamente. Non sono pubblicizzati come offerte ancora disponibili.

I testi non fissano una soglia anagrafica Junior, un numero di familiari coperti da Family, requisiti geografici Fuori sede o servizi esclusivi locali: non sono documentati nelle informazioni disponibili. La pagina chiede esplicitamente di verificarli con il direttivo prima dell’adesione e del pagamento. Aggiornare queste spiegazioni nell’editor quando il club conferma le condizioni.

## Informazioni ufficiali

La [campagna ufficiale Inter Club 2026/27](https://www.inter.it/it/notizie/inter-club-campagna-tesseramenti-2026-2027) conferma Welcome Kit Senior e Junior fino a esaurimento scorte, agevolazioni e prelazioni per partite selezionate, iniziative per i soci e una fase di vendita dedicata ai soci Plus per le gare casalinghe. Nella vetrina queste informazioni hanno un collegamento diretto alla pagina ufficiale e non vengono trasformate in garanzie di biglietti o posti.

La voce dei testi si ispira ai post pubblici del [profilo Instagram del club](https://www.instagram.com/inter.club.brindisi.jz/), senza usare Instagram come fonte per importi o condizioni che non vi sono leggibili. Per le future stagioni, aggiornare anche i riferimenti alla campagna ufficiale prima di riproporre i vantaggi come attuali.

## Editor, compatibilità e verifiche

Da **Sito vetrina** si possono modificare nome, presentazione, quota e spiegazione di ogni formula, oltre alla nota comune sulle quote. I tre nomi e testi già esistenti conservano le stesse chiavi: i contenuti personalizzati non vengono sovrascritti.

I nuovi campi usano valori iniziali versionati se il file privato dei contenuti non li contiene ancora. Tutti i valori sono mostrati come testo con escape HTML, mai eseguiti come codice. La vetrina continua a funzionare senza interrogare il database; questa aggiunta non richiede migrazioni SQL.

I pulsanti selezionano l’argomento Tesseramento e preparano un messaggio per la formula scelta. Cambiare formula aggiorna un messaggio automatico ancora intatto, ma non cancella un testo già personalizzato dal visitatore. Le domande frequenti utilizzano elementi HTML `details`, accessibili anche senza JavaScript.

Test ripetibile senza database:

```bash
php tests/membership_content_test.php
```

Controlla tutte le formule, i campi necessari, la compatibilità con contenuti salvati in precedenza e la coerenza delle quote iniziali con il listino SQL. I successivi aggiornamenti di quote nel CMS non aggiornano automaticamente il listino gestionale: mantenere coerenti i due archivi quando il club cambia gli importi.
