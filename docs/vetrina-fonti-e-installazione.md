# Vetrina pubblica, responsive e WhatsApp

## Struttura

- `/public/index.php`: home pubblica, anche senza database disponibile.
- `/public/login.php`: ingresso riservato, dashboard e vecchie pagine protette invariate.
- `/public/richieste/list.php`: inbox dei contatti del sito.
- `/public/messaggi/index.php`: centro WhatsApp con template, composizione ai soci,
  messaggio singolo, coda e storico.
- `config/site.php`: denominazione, sede, fonti e collegamenti pubblici.

## Fonti dei contenuti

La fondazione nel 2003 e la sede in via San Benedetto 8 sono riportate nell’articolo
[Agenda Brindisi del 23 aprile 2022](https://www.agendabrindisi.it/in-via-san-benedetto-la-ritrovata-sede-dellinter-club-javier-zanetti/).
Le tre fotografie d’archivio provengono dal medesimo articolo, con attribuzione
visibile. Non sono presentate come fotografie recenti.
Prima di una pubblicazione esterna definitiva, far confermare al direttivo sede,
contenuti, utilizzo delle fotografie e informativa privacy.
Le fotografie rimangono attribuite all’editore; nessuna licenza di riuso viene
presunta. Sostituirle con foto autorizzate del club se il riuso non è autorizzato.

Il riferimento cromatico e tipografico-editoriale è la
[sezione Inter Club di inter.it](https://www.inter.it/it/inter-club).
Non sono stati creati nuovi stemmi né copiati marchi per presentare il sito come
portale ufficiale di FC Internazionale Milano.
Non vengono inventati numeri telefonici, email, quote, orari o disponibilità biglietti.

Il logo bianco su nero “Inter Club Brindisi”, con le due linee blu, è quello
fornito direttamente dall’utente durante lo sviluppo. È conservato senza
ridisegno in `public/assets/images/logo-inter-club-brindisi.jpg` e usato nelle
intestazioni della vetrina, del gestionale e del login, oltre che nel footer.

## Installazione nuova su XAMPP Linux Mint

Resta sufficiente un solo SQL, che crea database, tabelle e nuovi campi:

```bash
sudo /opt/lampp/lampp start
cd /opt/lampp/htdocs/ICBRmanaging
sudo /opt/lampp/bin/mysql -u root < database/schema.sql
sudo /opt/lampp/bin/php bin/reset_admin_password.php admin 'SCEGLI-UNA-PASSWORD-SICURA'
```

Aprire `http://localhost/ICBRmanaging/public/`.
La home non reindirizza più al login. Il collegamento Area riservata porta al gestionale.
Non usare lo schema completo come aggiornamento di un DB esistente.

## Aggiornamento con dati esistenti

Backup preventivo. Se la 008 non è stata applicata, applicarla prima della 009.
La 009 aggiunge la tabella richieste e il collegamento dei messaggi alle richieste.
Non rieseguire migration già applicate.

```bash
sudo /opt/lampp/bin/mysqldump -u root icbr_gestionale > "$HOME/icbr-backup-$(date +%Y%m%d-%H%M%S).sql"
# SOLO se non applicata:
sudo /opt/lampp/bin/mysql -u root icbr_gestionale < database/migrations/008_prima_nota_quota_snapshot.sql
# SOLO se non applicata:
sudo /opt/lampp/bin/mysql -u root icbr_gestionale < database/migrations/009_richieste_vetrina.sql
```

Se MySQL richiede una password, aggiungere `-p`. Le operazioni di questa sessione
non hanno modificato il database di produzione.

## Anteprima

L’anteprima allegata è un’esportazione statica, in sola lettura, di 23 pagine
del progetto eseguito localmente con dati sintetici. Serve a verificare grafica,
contenuti e navigazione, non a simulare salvataggi o autenticazione.
Il collegamento “Esplora il gestionale dimostrativo” non concede accesso
all’applicazione PHP né al database reale. I moduli mostrano esplicitamente che
non salvano e i collegamenti a contatti dimostrativi sono disabilitati.
La versione PHP locale mantiene autenticazione, persistenza e funzioni operative.

## Contatti e privacy

Il modulo valida i dati sul server, usa CSRF, honeypot, limite per sessione e
limite di 5 richieste/ora per email. Sono protezioni di base, non un sistema
antibot completo: per traffico pubblico significativo aggiungere rate limiting
al reverse proxy e valutare un CAPTCHA rispettoso della privacy.
La conferma compare solo dopo il salvataggio riuscito. Non viene inviata email
automatica: il direttivo deve monitorare l’inbox.
Il telefono è facoltativo. L’informativa spiega il ricontatto anche via WhatsApp
in relazione alla richiesta, senza newsletter o campagne promozionali.
L’informativa predisposta deve essere verificata e approvata dal titolare prima
del rilascio pubblico, anche rispetto all’hosting effettivamente utilizzato.

La cancellazione di una richiesta dall’inbox elimina anche le risposte WhatsApp
collegate. Non cancella messaggi già inviati nei dispositivi o nei sistemi di terzi.
I backup hanno un ciclo di conservazione da gestire separatamente.

## WhatsApp: cosa è incluso

- Template con segnaposto, filtri destinatari, anteprima e selezione.
- Messaggi singoli a un recapito o collegati a una richiesta della vetrina.
- Testo modificabile prima dell’invio, copia con fallback manuale, apertura wa.me.
- Stato preparato, invio confermato manualmente, problema e rimessa in coda.
- Nessuna conferma di consegna/lettura e nessun invio automatico.
- Il reinvio del messaggio singolo è protetto da nonce monouso.
- L’invio automatico con API WhatsApp Business richiede un’ulteriore integrazione
  Meta, numero abilitato, credenziali e template approvati: non è attivato qui.

## Sicurezza del rilascio

In produzione impostare DocumentRoot su `public/`, HTTPS e credenziali DB dedicate.
Non esporre directory `config/`, `storage/`, `database/`, file di backup o `.git`.
Il layout e la vetrina non richiedono framework, npm o servizi esterni.
L’anteprima usa un DB separato con soli dati sintetici; non mostra l’archivio dei soci.

## SEO della vetrina

- Ogni pagina pubblica ha title, description, canonical, Open Graph/Twitter Card e
  dati strutturati JSON-LD (`SportsClub` con indirizzo e social; `FAQPage` in home).
- `robots.txt` e `sitemap.xml` sono generati da `public/robots.php` e
  `public/sitemap.php` (rewrite in `public/.htaccess`). L’area riservata è esclusa.
- In produzione impostare `ICBR_SITE_URL` (es. `SetEnv ICBR_SITE_URL https://dominio.it`
  nel VirtualHost) per URL canonici stabili; senza variabile si usa l’host della richiesta.
- Dopo la pubblicazione, inviare la sitemap a Google Search Console e collegare il
  sito alla scheda Google Business Profile del club.
