# ICBRmanaging — Gestionale Inter Club Javier Zanetti Brindisi

Gestionale web per la gestione dei soci e dei tesseramenti dell'Inter Club Javier Zanetti di Brindisi.

## Stack tecnico

- **Frontend:** HTML5, CSS3 (no framework)
- **Backend:** PHP 8+ (no framework)
- **Database:** MySQL / MariaDB
- **Server:** Apache (XAMPP / Raspberry Pi 5)

## Struttura navigazione

L'interfaccia è organizzata in tre aree principali:

| Voce menu | Contenuto |
|---|---|
| **Dashboard** | KPI rapidi: soci totali, tesserati stagione corrente |
| **Soci** | Anagrafica completa. La scheda di ogni socio include lo storico dei tesseramenti stagionali |
| **Configurazione** | Voci di sistema: Stagioni sportive, Tipologie tessera |

> **Nota:** I tesseramenti non hanno una sezione dedicata nel menu principale perché
> sono strettamente legati al singolo socio. Si accede allo storico tesseramenti
> direttamente dalla scheda socio (`/soci/view.php?id=X`).

## Struttura cartelle

```
/
├── config/          # Configurazione DB e costanti
├── database/        # Schema SQL
├── docs/            # Documentazione di progetto
├── includes/        # Funzioni comuni, auth, layout header/footer
└── public/          # Pagine web accessibili
    ├── assets/      # CSS, immagini
    ├── soci/        # list, view, create, edit
    ├── stagioni/    # list (Configurazione)
    ├── tipologie/   # list (Configurazione)
    ├── tesseramenti/ # Accessibili da scheda socio
    ├── dashboard.php
    ├── login.php
    └── logout.php
```

## Roadmap fasi

- **Fase 1 ✅** — Fondamenta tecniche (auth, DB, layout)
- **Fase 2 ✅** — Anagrafica soci (CRUD completo + tesseramenti in scheda)
- **Fase 3** — Tesseramenti stagionali (gestione completa, import file Inter Club)
- **Fase 4** — Import file Excel dal portale Inter Club
- **Fase 5** — Gestione quote e pagamenti
- **Fase 6** — Prima nota contabile
- **Fase 7** — Messaggi WhatsApp assistiti

## Accesso area riservata

L'applicazione richiede autenticazione. Ruoli previsti: `admin`, `segreteria`.
Credenziali di default configurabili nel seeder SQL in `database/`.
