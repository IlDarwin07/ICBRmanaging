-- =====================================================================
-- 014 - Famiglia (prenotare per i familiari) e sostenitori
-- Fare il backup prima. Non modifica dati esistenti.
-- ATTENZIONE: l'ALTER TABLE va eseguito UNA SOLA VOLTA.
-- =====================================================================

SET NAMES utf8mb4;

-- Soci per cui un titolare puo prenotare (relazione decisa dal direttivo).
-- Per un legame reciproco servono due righe (titolare->familiare e viceversa).
CREATE TABLE IF NOT EXISTS soci_famiglia (
    id_titolare INT NOT NULL,
    id_familiare INT NOT NULL,
    creato_da INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_titolare, id_familiare),
    KEY idx_famiglia_familiare (id_familiare),
    CONSTRAINT fk_famiglia_titolare FOREIGN KEY (id_titolare) REFERENCES soci(id_socio) ON DELETE CASCADE,
    CONSTRAINT fk_famiglia_familiare FOREIGN KEY (id_familiare) REFERENCES soci(id_socio) ON DELETE CASCADE,
    CONSTRAINT fk_famiglia_autore FOREIGN KEY (creato_da) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chi ha fatto la prenotazione, se diverso dall'intestatario (NULL = il socio stesso).
ALTER TABLE prenotazioni
    ADD COLUMN prenotata_da INT NULL AFTER id_socio,
    ADD CONSTRAINT fk_prenotazioni_prenotata_da FOREIGN KEY (prenotata_da) REFERENCES soci(id_socio) ON DELETE SET NULL;
