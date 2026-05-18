# ICBR Managing - Gestionale Interclub Brindisi

Applicazione web per la gestione delle prenotazioni dei soci dell'Interclub di Brindisi per la visione delle partite in sede.

## Funzionalita

- **Autenticazione**: Login e registrazione soci con gestione ruoli (Admin, Collaboratore, Socio)
- **Gestione Partite**: Creazione e gestione delle partite da parte di Admin e Collaboratori
- **Prenotazione Presenza**: I soci possono segnare la propria presenza alle partite
- **Assegnazione Posti**: Admin e Collaboratori assegnano i numeri di sedia ai soci prenotati
- **Visualizzazione Posto**: I soci vedono il numero di sedia assegnato per ogni partita

## Tecnologie

- **Backend**: PHP 8+ con SQLite
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Database**: SQLite3
- **Icone**: Font Awesome 6

## Installazione

### Requisiti
- PHP 8.0 o superiore
- Estensione PDO SQLite abilitata

### Avvio rapido

```bash
# Clona il repository
git clone https://github.com/IlDarwin07/ICBRmanaging.git
cd ICBRmanaging

# Avvia il server PHP integrato
php -S localhost:8000
```

Apri il browser su `http://localhost:8000`

### Credenziali di default

| Ruolo | Username | Password |
|-------|----------|----------|
| Admin | admin    | admin123 |

## Struttura del Progetto

```
ICBRmanaging/
├── index.php                  # Redirect alla pagina di login
├── config/
│   ├── database.php          # Connessione database (Singleton)
│   └── init_db.php           # Inizializzazione tabelle e admin
├── includes/
│   ├── auth.php              # Gestione autenticazione e sessioni
│   ├── header.php            # Header HTML con navbar
│   └── footer.php            # Footer HTML
├── api/
│   ├── login.php             # API login
│   ├── logout.php            # API logout
│   ├── register.php          # API registrazione
│   ├── partite.php           # CRUD partite
│   ├── prenotazioni.php      # Gestione prenotazioni
│   ├── assegnazioni.php      # Assegnazione posti
│   └── utenti.php            # Gestione utenti (admin)
├── pages/
│   ├── login.php             # Pagina login/registrazione
│   ├── dashboard.php         # Dashboard principale
│   ├── partite.php           # Lista partite con prenotazione
│   ├── gestione_partite.php  # Creazione/gestione partite
│   ├── assegna_posti.php     # Assegnazione posti a sedere
│   └── gestione_utenti.php   # Gestione utenti (admin)
├── assets/
│   ├── css/style.css         # Stili dell'applicazione
│   └── js/app.js             # JavaScript condiviso
└── data/                     # Database SQLite (auto-generato)
```

## Ruoli Utente

| Ruolo | Permessi |
|-------|----------|
| **Admin** | Gestione completa: utenti, partite, posti, cambio ruoli |
| **Collaboratore** | Creazione partite e assegnazione posti |
| **Socio** | Prenotazione presenza e visualizzazione posto assegnato |

## Database

Il database SQLite viene creato automaticamente al primo avvio in `data/icbr.db`. Le tabelle principali sono:

- `users` - Utenti del sistema
- `partite` - Partite/eventi
- `prenotazioni` - Prenotazioni dei soci
- `assegnazioni_posti` - Assegnazione sedie ai soci
