# ICBRmanaging — Gestionale Inter Club Javier Zanetti Brindisi

Sito vetrina pubblico e gestionale riservato per soci, tesseramenti, quote e comunicazioni dell'Inter Club Javier Zanetti di Brindisi.

La home è pubblica; il gestionale si apre da **Area riservata**. Per fonti,
privacy, foto d'archivio, installazione XAMPP Linux Mint e limiti dell'anteprima,
vedi [vetrina, fonti e installazione](docs/vetrina-fonti-e-installazione.md).

## Stack tecnico

- **Frontend:** HTML5, CSS3 (no framework)
- **Backend:** PHP 8+ (no framework)
- **Database:** MySQL / MariaDB
- **Server:** Apache (XAMPP / Raspberry Pi 5)

## Struttura navigazione

| Voce menu | Contenuto |
|---|---|
| **Dashboard** | KPI soci + KPI economici (incassato stagione, residuo previsto, saldi cassa/banca) |
| **Soci** | Anagrafica completa. La scheda di ogni socio include lo storico dei tesseramenti stagionali |
| **Tesseramenti** | Lista con filtri (stagione, stato pagamento, attivo portale, tipologia, tessera fisica), colonna residuo, export CSV |
| **Prima nota** | Registro contabile: entrate/uscite, conti cassa/banca, saldi, filtri per periodo, export CSV |
| **WhatsApp** | Centro messaggi: template, filtri soci, risposte singole alle richieste, coda e storico manuale |
| **Richieste sito** | Inbox della vetrina, stato richieste, risposta email/WhatsApp e cancellazione |
| **Importa** | Caricamento file XLSX/XLS del portale Inter Club con mappatura colonne |
| **Configurazione** | Stagioni sportive, Tipologie tessera |

## Struttura cartelle

```
/
├── bin/             # Script da riga di comando (reset password admin)
├── config/          # Configurazione DB e costanti
├── database/        # Schema SQL + migrations
│   ├── schema.sql   # Installazione da zero
│   └── migrations/  # Aggiornamenti per DB esistenti
├── docs/            # Documentazione di progetto
├── includes/        # Funzioni comuni, auth, layout header/footer
└── public/          # Pagine web accessibili
    ├── assets/      # CSS
    ├── soci/         # list, view, create, edit
    ├── stagioni/     # list, create, edit
    ├── tipologie/    # list, create, edit
    ├── tesseramenti/ # list, view, create, edit, pagamento_add/delete
    ├── prima_nota/   # list, create, edit, delete, export
    ├── messaggi/     # template CRUD, compose, list
    ├── importazioni/ # upload, mapping, process, log
    ├── dashboard.php
    ├── login.php
    └── logout.php
```

## Roadmap fasi

- **Fase 1 ✅** — Fondamenta tecniche (auth, DB, layout)
- **Fase 2 ✅** — Anagrafica soci (CRUD completo + tesseramenti in scheda)
- **Fase 3 ✅** — Tesseramenti stagionali (gestione completa, import file Inter Club)
- **Fase 4 ✅** — Import file Excel dal portale Inter Club
- **Fase 5 ✅** — Gestione quote e pagamenti (filtri per stato pagamento, residuo, snapshot quota non retroattivo, prima nota collegata al pagamento)
- **Fase 6 ✅** — Prima nota contabile (entrate/uscite, conti cassa/banca, saldi, export)
- **Fase 7 ✅** — Messaggi WhatsApp assistiti (template con placeholder, compositore per gruppi, wa.me, copia testo)
- **Fase 8** — Invio automatico via WhatsApp Business API (richiede configurazione Meta)

## Logica quote e pagamenti

- **Quota dovuta:** ogni tesseramento salva uno *snapshot* della quota al momento
  della creazione (`quota_tesseramento`). Se assente, viene usata la quota corrente
  della tipologia. Modificare i listini NON cambia retroattivamente i residui storici.
- **Versato:** somma dei pagamenti; i **rimborsi** vengono sottratti (netto).
- **Stati:** `Da saldare` (nessun versamento), `Solo acconto`, `Saldato`,
  `Eccedenza` (versato > quota), `A credito` (rimborsi > incassi), `Quota n/d`.
- **Prima nota:** registrando un pagamento puoi creare in automatico il movimento
  contabile (stesso importo, conto scelto, entrata o uscita per i rimborsi).
  Il movimento è vincolato 1:1 al pagamento (chiave unica) e si modifica/elimina
  dal pagamento stesso.

## Messaggi WhatsApp assistiti

1. Crea un template con placeholder: `{{nome}}`, `{{cognome}}`, `{{nome_completo}}`,
   `{{telefono}}`, `{{quota}}`, `{{pagato}}`, `{{residuo}}`, `{{stagione}}`, `{{data}}`.
2. Componi l'invio scegliendo i destinatari per filtri (stagione, stato pagamento, tipologia...).
3. Verifica l'anteprima personalizzata, copia il testo o apri `wa.me`.
4. I messaggi salvati restano nella lista; l'apertura di WhatsApp NON li segna come
   inviati: usa il pulsante "Conferma inviato" (l'invio automatico è la fase 8).
5. Dal Centro WhatsApp puoi creare un messaggio singolo o rispondere a una richiesta
   della vetrina. Le risposte vengono collegate alla richiesta, restano modificabili
   finché non confermi l'invio e possono essere rimesse in coda in caso di problemi.

## Installazione

```bash
# 1. Crea il database e importa lo schema
mysql -u root -p < database/schema.sql

# 2. Crea l'utente amministratore
php bin/reset_admin_password.php admin "PasswordSicura123!"

# 3. Configura config/database.php se necessario

# 4. Servi la cartella public/ (DocumentRoot) oppure tutto il progetto
#    in sottocartella (XAMPP: http://localhost/ICBRmanaging/public/)
```

### Aggiornamento di un database esistente

Applica le migration **non ancora applicate** in ordine di numero; quelle già
applicate vanno saltate (falliscono con "Duplicate key/column" se rieseguite).
Per un database con tessera_fisica ENUM, tipologie 2027 e id_stagione in importazioni,
ma senza 008/009, applicare entrambe. Se la 008 è già presente, eseguire solo la 009:

```bash
mysql -u root -p icbr_gestionale < database/migrations/008_prima_nota_quota_snapshot.sql
mysql -u root -p icbr_gestionale < database/migrations/009_richieste_vetrina.sql
```

La 008: aggiunge lo snapshot quota ai tesseramenti esistenti (backfill
automatico), crea la chiave unica prima nota↔pagamento e l'unicità delle
tipologie (i riferimenti dei tesseramenti vengono prima spostati sulla
tipologia conservata, quindi nessun tesseramento perde il listino).

Su un DB più vecchio applica prima anche 002, 006 e 007, verificando che
non siano già presenti.

La 009 aggiunge le richieste del sito e il collegamento alle risposte WhatsApp.
Per una **installazione nuova** basta sempre il solo `database/schema.sql`:
non applicare anche le migration. Eseguire un backup prima di aggiornare un DB
con dati esistenti. La vetrina funziona senza DB, ma il modulo contatti conferma
una richiesta soltanto se il salvataggio è riuscito.

### Limiti noti

- La registrazione pagamento NON è idempotente: il reinvio del form crea un
  secondo pagamento (la chiave unica prima nota↔pagamento protegge solo il
  rapporto 1:1 movimento-pagamento).
- Il filtro "Tutte le stagioni" nella lista tesseramenti disattiva il default
  della stagione attiva: è voluto (il default senza parametro resta la stagione attiva).
- Numeri WhatsApp: i numeri nazionali senza prefisso sono considerati italiani
  (viene anteposto 39 per il link wa.me); i prefissi internazionali già
  presenti (+XX / 00XX) vengono mantenuti.

## Accesso area riservata

L'area gestionale richiede autenticazione; la vetrina no. Gli amministratori gestiscono account personali con etichetta ruolo e permessi per modulo; i ruoli tecnici storici restano compatibili con il database.
Credenziali di default configurabili con `bin/reset_admin_password.php`.

## Editor del sito vetrina

Gli utenti `admin` e gli account con permesso `sito.write` possono modificare testi, sede, social e fotografie dal menu
**Sito vetrina**. Nessuna nuova migrazione SQL per questa funzione:
vedi [installazione, permessi e backup](docs/editor-vetrina.md).
Le immagini iniziali sono locali e versionate in `public/assets/images/`.

## Account del direttivo e registro

Gestione account personali, etichette ruolo, permessi per modulo e registro attività: [guida completa](docs/direttivo-permessi-attivita.md).
Su database già esistenti serve la migrazione `010_direttivo_permessi_log.sql`; una nuova installazione usa il solo `database/schema.sql`.
