# Le formule di tesseramento nella vetrina

La sezione pubblica presenta sei formule, con introduzione, spiegazione, quota di riferimento e richiesta informazioni dedicata. Non vende tessere o biglietti e non effettua prenotazioni.

## Quote di riferimento e provenienza

Le quote iniziali riprendono il listino 2026/27 già presente in `database/schema.sql` e nella migrazione 007: Fedelissimo 95 €, Sostenitore 55 €, Fuori sede 50 €, Family 50 €, Junior 25 € e Quota Plus +35 €. Non sono prezzi ricavati dal sito di Inter o un listino letto in tempo reale dal database.

La pagina distingue le cinque formule di adesione dall’upgrade Plus, aggiuntivo alla tessera Senior. I cinque rinnovi agevolati entro il 30 giugno 2026 sono riportati soltanto nella domanda dedicata come importi storici scaduti: 90, 50, 45, 45 e 25 € rispettivamente. Non sono pubblicizzati come offerte ancora disponibili.

Il direttivo ha confermato che **Fuori sede è riservato a chi risiede fuori dal Comune di Brindisi**, anche in un comune vicino: non significa fuori provincia o a una distanza minima. Il requisito è riportato nella descrizione e nelle FAQ locali. L'aggiornamento corregge anche i vecchi testi standard eventualmente già salvati dal CMS, senza sovrascrivere le personalizzazioni.

I testi non inventano una soglia anagrafica Junior, un numero di familiari coperti da Family o servizi esclusivi locali: questi dettagli vanno confermati dal direttivo prima dell’adesione e del pagamento.

## Informazioni ufficiali

La [campagna ufficiale Inter Club 2026/27](https://www.inter.it/it/notizie/inter-club-campagna-tesseramenti-2026-2027) conferma Welcome Kit Senior e Junior fino a esaurimento scorte, agevolazioni e prelazioni per partite selezionate, iniziative per i soci e una fase di vendita dedicata ai soci Plus per le gare casalinghe. Nella vetrina queste informazioni hanno un collegamento diretto alla pagina ufficiale e non vengono trasformate in garanzie di biglietti o posti.

La [pagina Inter Club](https://www.inter.it/it/inter-club), verificata il 25 ottobre 2026, specifica le partite casalinghe di Campionato e Champions League e subordina il beneficio Plus alla partecipazione al programma Loyalty INTERISTA e ai relativi termini: la condizione compare sul retro della card e nelle FAQ. Le [FAQ ufficiali Inter](https://www.inter.it/it/faq) completano le risposte su MyInter, tessere e biglietti. Le risposte sono sintetizzate in `config/public_faq.php`, con fonti per domanda e distinzione esplicita rispetto alle regole ICBR.

La voce dei testi si ispira ai post pubblici del [profilo Instagram del club](https://www.instagram.com/inter.club.brindisi.jz/), senza usare Instagram come fonte per importi o condizioni che non vi sono leggibili. Per le future stagioni, aggiornare anche i riferimenti alla campagna ufficiale prima di riproporre i vantaggi come attuali.

## Editor, compatibilità e verifiche

Da **Sito vetrina** si possono modificare nome, presentazione, quota e spiegazione di ogni formula, oltre alla nota comune sulle quote. I tre nomi e testi già esistenti conservano le stesse chiavi: i contenuti personalizzati non vengono sovrascritti.

I nuovi campi usano valori iniziali versionati se il file privato dei contenuti non li contiene ancora. Tutti i valori sono mostrati come testo con escape HTML, mai eseguiti come codice. La vetrina continua a funzionare senza interrogare il database; questa aggiunta non richiede migrazioni SQL.

Le sei card mostrano nome, breve presentazione e quota sul fronte: **Info** gira la card e mostra spiegazione e condizioni sul retro, **Indietro** ripristina il fronte e **Contatta il direttivo** prepara il contatto per quella formula. Non ci sono numerazioni decorative. L'altezza segue il contenuto anche per descrizioni lunghe del CMS; non viene imposto un riquadro con testo tagliato.

La faccia nascosta è esclusa dal focus e dall'albero accessibile; Invio, Spazio e il pulsante Indietro funzionano da tastiera, Escape torna al fronte e la preferenza di movimento ridotto disattiva l'animazione. Senza JavaScript le descrizioni restano leggibili. Cambiare formula aggiorna un messaggio automatico ancora intatto, ma non cancella un testo personalizzato dal visitatore. Le FAQ utilizzano elementi HTML `details` e i collegamenti alle singole risposte aprono l'accordion corrispondente.

Il rimando editoriale alla testata è stato eliminato dal racconto del club. L'utente caricherà in seguito fotografie del club: fino alla sostituzione le foto d'archivio conservano il proprio marchio e credito, che non vengono cancellati o ritagliati.

Test ripetibile senza database:

```bash
php tests/membership_content_test.php
php tests/public_faq_test.php
```

Controlla tutte le formule, i campi necessari, la compatibilità con contenuti salvati in precedenza e la coerenza delle quote iniziali con il listino SQL. I successivi aggiornamenti di quote nel CMS non aggiornano automaticamente il listino gestionale: mantenere coerenti i due archivi quando il club cambia gli importi.
