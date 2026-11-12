# Area soci e prenotazioni in sede

I soci confermano la presenza alle partite trasmesse in sede dal proprio telefono.
Solo il direttivo assegna le sedie.

## Installazione

1. Backup del database.
2. Applicare `database/migrations/013_partite_prenotazioni.sql`.
3. In **Sede → Sale e capienze** inserire il numero reale di sedie della
   *Sala Proiettore* e della *Sala TV* (40 e 20 sono valori provvisori) e quante
   sedie ci sono per fila, per disegnare la piantina.
4. In **Direttivo** assegnare agli account i nuovi permessi:
   - *Partite e prenotazioni*: consultare, creare/aprire/chiudere, gestire presenze e sedie, esportare;
   - *Accessi area soci*: inviare, rinnovare e revocare gli accessi.
5. Configurare l'invio email (sotto). Finché non è configurato, il gestionale
   mostra il link di attivazione da inoltrare a mano su WhatsApp.

## Invio email (SMTP)

Le credenziali si leggono solo da variabili d'ambiente (vedi `config/mail.php`).
Esempio Apache, nel VirtualHost:

```apache
SetEnv ICBR_SMTP_HOST smtp.gmail.com
SetEnv ICBR_SMTP_PORT 587
SetEnv ICBR_SMTP_SECURE tls
SetEnv ICBR_SMTP_USER indirizzo-del-club@gmail.com
SetEnv ICBR_SMTP_PASS "password-per-le-app"
SetEnv ICBR_MAIL_FROM indirizzo-del-club@gmail.com
SetEnv ICBR_PUBLIC_URL https://dominio-del-club.it
```

Con Gmail serve una *password per le app* (verifica in due passaggi attiva).
`ICBR_PUBLIC_URL` serve a costruire link corretti nelle email anche dietro proxy.

## Flusso

| Chi | Dove | Cosa |
|---|---|---|
| Direttivo | Scheda socio → *Accesso all'area soci* | Invia l'accesso: email con link valido 48 ore, monouso |
| Socio | `/soci-area/attiva.php` | Sceglie la password (min. 10 caratteri) |
| Direttivo | Sede → Partite e presenze → *Nuova partita* | Partita, data, sala, chiusura prenotazioni, solo tesserati sì/no |
| Socio | `/soci-area/` | *Ci sarò* / *Non vengo più* fino alla chiusura |
| Direttivo | Scheda partita | Vede presenti e lista d'attesa, assegna le sedie (clic sul socio, poi sulla sedia, oppure *Assegna in automatico*), esporta CSV, WhatsApp con la sedia |
| Socio | `/soci-area/` | Vede il numero della sua sedia |

Regole:

- oltre la capienza il socio entra in **lista d'attesa**; se un presente annulla,
  il primo in attesa passa tra i presenti (ed è avvisato via email, se configurata);
- il database impedisce due prenotazioni dello stesso socio e la stessa sedia a due soci;
- dopo la chiusura il socio non può più modificare: il direttivo sì (anche aggiungere soci);
- cambiando sala a una partita, le sedie assegnate si liberano.

## Sicurezza

- Area soci separata dal gestionale: cookie `ICBR_SOCI` limitato a `/soci-area/`,
  sessione `$_SESSION['socio']`, tabella `soci_account`. Un socio non può aprire
  pagine del gestionale.
- Token salvati solo come hash SHA-256; recupero password con risposta identica
  per email esistenti e non, al massimo un link ogni 5 minuti.
- Blocco di 15 minuti dopo 5 password errate.
- Revocando l'accesso la password viene cancellata: un nuovo invito riparte dall'attivazione.
- Il socio vede solo le proprie prenotazioni, mai l'elenco degli altri presenti.

## Test

```bash
ICBR_DB_NAME=icbr_test php tests/partite_test.php
```

Usa un database di prova con la migration 013; tutte le modifiche vengono annullate.
