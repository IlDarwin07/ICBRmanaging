# Direttivo, permessi e registro attività

## Account personali

Da **Direttivo**, un amministratore può creare o aggiornare gli account dei membri. Ogni persona ha username, password, etichetta libera del ruolo e una selezione di permessi indipendente dall’etichetta.

- **Etichetta**: ad esempio Vicepresidente, Tesoriere o Responsabile trasferte. Non concede autorizzazioni automaticamente.
- **Amministratore completo**: accesso a tutti i moduli, compresa la gestione degli account. Questa autorizzazione non è delegabile tramite una semplice casella di modulo.
- **Account personalizzato**: consultazione, modifica, eliminazione ed esportazione sono separati dove previsti dal gestionale. Non vengono aggiunte operazioni che il modulo non possiede, come l’eliminazione fisica dei soci.
- **Disattivazione**: impedisce nuovi accessi e invalida le sessioni precedenti alla richiesta successiva. Gli account non vengono cancellati fisicamente.
- **Password**: almeno 12 caratteri, massimo 72 byte; memorizzata soltanto come hash. Il campo vuoto su un account esistente mantiene la password precedente.

Il salvataggio di un account incrementa la versione di sessione, anche quando si cambia soltanto il nome del ruolo. La persona deve accedere di nuovo dagli altri dispositivi; l’amministratore che aggiorna il proprio account conserva la sessione corrente. Non si può disattivare il proprio account o rimuovere l’ultimo amministratore attivo.

## Permessi effettivi

I controlli sono applicati lato server, anche su URL diretti, POST e download CSV. Le voci e i pulsanti non consentiti vengono nascosti per rendere coerente l’interfaccia, ma non sono il meccanismo di protezione.

- **Consultazione**: viene aggiunta automaticamente quando si concede una modifica, eliminazione o esportazione dello stesso modulo.
- **Pagamenti**: abilitarne la gestione consente anche di consultare i tesseramenti. Per creare un movimento collegato serve anche `prima_nota.write`; per eliminare un pagamento con movimento collegato serve anche `prima_nota.delete`.
- **Richieste dal sito**: l’eliminazione di una richiesta che ha risposte WhatsApp collegate richiede anche `whatsapp.delete`. La lettura della richiesta comprende le risposte già collegate, come in una conversazione.
- **WhatsApp**: la preparazione di una risposta agganciata a una richiesta richiede anche `richieste.view`. L’apertura di WhatsApp resta un’azione manuale esterna: non certifica un invio.
- **Importazioni**: autorizzazione ampia, perché l’import può creare o aggiornare soci e tesseramenti. Non assegnarla a chi deve soltanto consultare.

- **Sito vetrina**: `sito.write` permette di aggiornare immediatamente i contenuti pubblici, fotografie e social inclusi.
- **Registro**: `attivita.view` permette la consultazione di tutti gli eventi del registro; assegnarlo soltanto a chi ne ha necessità.

L’importazione richiede sempre la conferma dell’anteprima del mapping, anche se le colonne sono riconosciute automaticamente. Upload e conferma verificano il token CSRF; l’elaborazione consuma un’autorizzazione di sessione monouso e non ripete l’import con un semplice aggiornamento della pagina.

La dashboard degli account non amministratori contiene solo collegamenti ai moduli abilitati, senza interrogare o mostrare i KPI generali economici dell’amministratore. Le pagine di un modulo possono naturalmente includere dati collegati necessari alla funzione, come il nome del socio su un tesseramento.

## Registro attività

La voce **Attività** mostra data e ora italiana, username dell’autore, modulo, azione e riferimento al record. I filtri per autore esatto, modulo e intervallo di date si combinano; la consultazione è paginata a 50 eventi.

Vengono registrati accessi riusciti e falliti, uscite, sessioni revocate, tentativi di operazioni non autorizzate, scritture nelle tabelle applicative, esportazioni della prima nota e pubblicazioni della vetrina. Non viene registrata ogni semplice apertura di pagina; non vengono ricostruite attività anteriori all’attivazione del registro.

Password, hash, token, parametri SQL, testi dei messaggi e contenuti dei campi anagrafici non vengono copiati negli eventi. Sono conservati identificativi dei record, username e metadati tecnici minimi; un tentativo di login fallito conserva lo username tentato, limitato a 80 caratteri.

Le modifiche al database e il relativo evento condividono una transazione: un rollback elimina anche gli eventi della modifica e un errore di audit impedisce il commit. Gli aggiornamenti che non cambiano righe non generano eventi di modifica. I contenuti della vetrina sono invece salvati in un file privato atomico: file e database non sono una transazione distribuita. Se l’audit fallisce dopo il salvataggio della vetrina, il sito resta aggiornato e viene mostrato un avviso esplicito, senza cancellare le foto pubblicate.

Il registro è in sola lettura nell’applicazione: non esistono pulsanti per modificarlo o eliminarlo. Non è un archivio certificato o a prova di manomissione: chi amministra direttamente il database può alterarlo. Backup, autorizzazioni del database, durata di conservazione e cancellazioni amministrative vanno definiti dal club in base alle sue esigenze.

## Installazione e aggiornamento

Una nuova installazione usa **un solo file SQL**, `database/schema.sql`, che include già le tabelle per account e registro. Creare il primo amministratore tramite il comando CLI documentato nel README.

Per un database esistente:

1. Fare un backup verificato.
2. Applicare in ordine le migrazioni precedenti ancora mancanti.
3. Applicare `database/migrations/010_direttivo_permessi_log.sql` una sola volta tramite phpMyAdmin o client MySQL.
4. Accedere di nuovo e controllare gli account in Direttivo.

Esempio Linux Mint con XAMPP, adattando cartella e nome del database:

```bash
cd /opt/lampp/htdocs/ICBRmanaging
sudo /opt/lampp/bin/mysqldump -u root -p icbr_gestionale > "$HOME/icbr-backup-$(date +%Y%m%d-%H%M%S).sql"
sudo /opt/lampp/bin/mysql -u root -p icbr_gestionale < database/migrations/010_direttivo_permessi_log.sql
```

La migrazione aggiunge soltanto due tabelle e profili iniziali per gli account esistenti. Se viene rieseguita, non sovrascrive permessi già personalizzati. I vecchi ruoli vengono tradotti in profili iniziali prudenti; controllarli prima di assegnare gli accessi.

Prima dell’applicazione di 010, gli amministratori possono usare i moduli preesistenti con un avviso; gestione direttivo e registro non sono attivi, e gli altri account restano limitati. L’applicazione non esegue migrazioni automaticamente. **Il database di produzione non è stato modificato durante lo sviluppo.**

## Controlli ripetibili

Il test CLI rifiuta database che non terminano con `_preview` o `_test`. Usa transazioni e rollback per non lasciare gli account di prova:

```bash
ICBR_DB_NAME=icbr_preview php tests/security_audit_test.php
```

Verifica whitelist dei permessi, dipendenze, identificativi restituiti dagli INSERT, assenza di password negli eventi, aggiornamenti senza modifiche, rollback e impossibilità di confermare una modifica se la scrittura di audit fallisce.
