# WhatsApp: invio collettivo su conferma

## Stato di questo commit

Fondazione soltanto: builder del payload template e test CLI. Nessun collegamento Meta attivo, endpoint di invio, modifica al database o invio reale. Test da eseguire: `php tests/whatsapp_payload_test.php`. Il builder non verifica approvazione Meta, consenso, esistenza del numero o corrispondenza dei parametri al modello: questi controlli restano obbligatori prima del trasporto. Supporto iniziale limitato a template con parametri testuali posizionali nel body; header, pulsanti e parametri nominati non supportati.

## Requisiti concordati

- Solo ruolo tecnico admin configura numero, account e credenziali Meta. Non basta un permesso generico di scrittura WhatsApp.
- Permesso separato whatsapp.send assegnabile dall'admin al direttivo.
- Nessun cron di promemoria, trigger sui pagamenti o invio al salvataggio della configurazione.
- Stagione esplicita, filtri pagamento e selezione manuale; tutti con residuo include chi non ha pagato e chi ha versato un acconto.
- Anteprima individuale e conferma POST con CSRF e token monouso vincolato a operatore, destinatari e modello. Una coda puo processare soltanto un lotto gia confermato.
- Ricalcolo di permessi, consenso e idoneita prima della conferma. Esclusioni motivate e visibili.
- Messaggi individuali dal numero della segreteria; nessuna creazione di gruppi.
- Chiave idempotente nel database per ogni messaggio del lotto. Timeout ambiguo da verificare, non da ritentare automaticamente.
- Distinguere preparato, richiesta in corso, accettato da Meta, consegnato, letto, fallito ed esito incerto. La consegna richiede webhook verificato.
- Nessun token nel repository, nei log o nell'HTML. Segreti fuori dalla directory pubblica; impostazioni visibili soltanto all'admin.
- Consenso documentato e revocabile; numero presente in anagrafica non equivale a consenso.

## Prossimi blocchi prima di un rilascio utilizzabile

1. Migrazione database per configurazione non segreta, consensi, modelli Meta, lotti e tentativi; aggiornare anche schema.sql.
2. Impostazioni admin e permesso whatsapp.send nel catalogo e nelle route.
3. Selezione con stagione esplicita e tutti con residuo, limite risultati dichiarato e anteprima congelata.
4. Conferma idempotente e trasporto HTTPS con timeout, gestione errori e nessuna credenziale esposta.
5. Webhook con verifica firma e aggiornamenti di stato resistenti a duplicati e ordine degli eventi.
6. Test accessi, CSRF, duplicati, revoche consenso e guasti di rete; collaudo Meta con destinatari di prova prima del merge.

Non applicare migrazioni in produzione e non inviare messaggi di prova senza un'approvazione separata.
