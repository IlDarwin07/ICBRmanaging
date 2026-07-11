# ICBRmanaging — Gestionale Web Inter Club Javier Zanetti Brindisi

Gestionale amministrativo per l'Inter Club Javier Zanetti di Brindisi, sviluppato in
PHP 8+ / MySQL, in sostituzione della gestione attuale basata su fogli Excel.

Il progetto segue la roadmap definita nei documenti di analisi (`docs/`):
brief di progetto, schema database, mappatura import file portale Inter Club e
roadmap di sviluppo a fasi.

## Stato di avanzamento

- [x] **Fase 1 — Fondazione tecnica**: struttura cartelle, connessione database,
      autenticazione admin con password hash sicuro (`password_hash`/`password_verify`),
      sessioni protette, layout base pannello admin.
- [x] **Fase 2 — Anagrafica soci**: CRUD soci, ricerca per nome/cognome/telefono/
      codice fiscale, scheda dettaglio, stato attivo/disattivo, validazioni
      server-side.
- [ ] Fase 3 — Tesseramenti stagionali (schema DB già pronto, CRUD da sviluppare)
- [ ] Fase 4 — Import file Excel Inter Club
- [ ] Fase 5 — Gestione quote e pagamenti
- [ ] Fase 6 — Prima nota
- [ ] Fase 7 — Messaggi e WhatsApp assistito
- [ ] Fase 8 — Integrazione WhatsApp Business API

Lo schema database (`database/schema.sql`) include **già tutte le tabelle** previste
da tutte le fasi (soci, stagioni, tipologie_tesseramento, tesseramenti,
pagamenti_tesseramenti, prima_nota, importazioni, righe_importazione,
template_whatsapp, messaggi_whatsapp, utenti_admin), così da non dover fare
migrazioni distruttive più avanti.

## Struttura del progetto

```
ICBRmanaging/
├── config/
│   └── database.php          # connessione PDO (legge variabili d'ambiente)
├── includes/
│   ├── auth.php               # login, sessioni, CSRF, controllo ruoli
│   ├── functions.php          # normalizzazione dati (telefono, CF, date, ecc.)
│   ├── layout_header.php
│   └── layout_footer.php
├── public/                    # <-- DOCUMENT ROOT del web server
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── soci/
│   │   ├── list.php
│   │   ├── create.php
│   │   ├── edit.php
│   │   └── view.php
│   └── assets/css/style.css
├── bin/
│   └── reset_admin_password.php   # crea/aggiorna l'utente admin da CLI
├── database/
│   └── schema.sql              # schema completo di tutte le fasi
└── docs/                       # documenti di analisi originali (PDF)
```

**Importante:** la document root del server web deve puntare alla cartella
`public/`, non alla radice del progetto, in modo che `config/`, `includes/`,
`database/` e `bin/` non siano mai raggiungibili via browser.

## Setup rapido (ambiente di sviluppo locale)

1. Creare il database e importare lo schema:
   ```bash
   mysql -u root -p -e "CREATE DATABASE icbr_gestionale CHARACTER SET utf8mb4;"
   mysql -u root -p icbr_gestionale < database/schema.sql
   ```

2. Configurare le credenziali di connessione tramite variabili d'ambiente
   (oppure modificare i valori di default in `config/database.php` solo in
   sviluppo locale, mai in produzione):
   ```bash
   export ICBR_DB_HOST=127.0.0.1
   export ICBR_DB_NAME=icbr_gestionale
   export ICBR_DB_USER=root
   export ICBR_DB_PASS=la_tua_password
   ```

3. Creare il primo utente amministratore:
   ```bash
   php bin/reset_admin_password.php admin "PasswordSiceura123!"
   ```

4. Avviare il server di sviluppo PHP puntando alla cartella `public/`:
   ```bash
   php -S localhost:8000 -t public
   ```

5. Aprire `http://localhost:8000` ed effettuare il login.

## Sicurezza

- Password sempre gestite con `password_hash()` / `password_verify()`, mai in chiaro.
- Sessioni con `session_regenerate_id()` al login, cookie `HttpOnly`/`SameSite`.
- Token CSRF su tutti i form di scrittura.
- Query sempre parametrizzate via PDO (nessuna concatenazione SQL).
- `.htaccess` in `public/` e struttura a cartelle separate per evitare che file
  sensibili (config, schema, script CLI) siano raggiungibili dal web.

## Documenti di analisi

I PDF originali di analisi (brief, schema DB, mappatura import, roadmap) sono
conservati in `docs/` come riferimento per lo sviluppo delle fasi successive.
