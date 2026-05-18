<?php
require_once __DIR__ . '/database.php';

function initializeDatabase() {
    $db = Database::getInstance()->getConnection();

    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            nome TEXT NOT NULL,
            cognome TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            ruolo TEXT NOT NULL DEFAULT 'socio' CHECK(ruolo IN ('admin','collaboratore','socio')),
            attivo INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS partite (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titolo TEXT NOT NULL,
            squadra_casa TEXT NOT NULL,
            squadra_ospite TEXT NOT NULL,
            data_partita DATE NOT NULL,
            ora_partita TIME NOT NULL,
            luogo TEXT NOT NULL DEFAULT 'Sede Interclub Brindisi',
            num_posti INTEGER NOT NULL DEFAULT 50,
            stato TEXT NOT NULL DEFAULT 'aperta' CHECK(stato IN ('aperta','chiusa','conclusa')),
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS prenotazioni (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            partita_id INTEGER NOT NULL,
            stato TEXT NOT NULL DEFAULT 'confermata' CHECK(stato IN ('confermata','cancellata')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (partita_id) REFERENCES partite(id),
            UNIQUE(user_id, partita_id)
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS assegnazioni_posti (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prenotazione_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            partita_id INTEGER NOT NULL,
            numero_sedia INTEGER NOT NULL,
            assigned_by INTEGER NOT NULL,
            assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (prenotazione_id) REFERENCES prenotazioni(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (partita_id) REFERENCES partite(id),
            FOREIGN KEY (assigned_by) REFERENCES users(id),
            UNIQUE(partita_id, numero_sedia),
            UNIQUE(user_id, partita_id)
        )
    ");

    // Create default admin if not exists
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE ruolo = 'admin'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result['cnt'] == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (username, password_hash, nome, cognome, email, ruolo)
            VALUES ('admin', ?, 'Admin', 'ICBR', 'admin@icbr.it', 'admin')
        ");
        $stmt->execute([$hash]);
    }
}

initializeDatabase();
