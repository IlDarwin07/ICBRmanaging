-- =====================================================================
-- 017 - Partite trasmesse in tutte le sale, con intervallo di sedie per sala
-- Esempio: Sala Proiettore sedie 1-20, Sala TV sedie 21-32.
-- La numerazione e' unica per partita: la sala di una sedia deriva dal numero.
-- Backup prima dell'applicazione. Le partite esistenti restano valide
-- (una sola sala, sedie da 1 a capienza).
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS partite_sale (
    id_partita_sala INT AUTO_INCREMENT PRIMARY KEY,
    id_partita INT NOT NULL,
    id_sala INT NOT NULL,
    posto_da SMALLINT UNSIGNED NOT NULL,
    posto_a SMALLINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_partita_sala (id_partita, id_sala),
    KEY idx_partite_sale_range (id_partita, posto_da),
    CONSTRAINT fk_partite_sale_partita FOREIGN KEY (id_partita) REFERENCES partite(id_partita) ON DELETE CASCADE,
    CONSTRAINT fk_partite_sale_sala FOREIGN KEY (id_sala) REFERENCES sale(id_sala) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO partite_sale (id_partita, id_sala, posto_da, posto_a)
SELECT p.id_partita, p.id_sala, 1, GREATEST(1, s.capienza)
FROM partite p JOIN sale s ON s.id_sala = p.id_sala
WHERE NOT EXISTS (SELECT 1 FROM partite_sale x WHERE x.id_partita = p.id_partita);
