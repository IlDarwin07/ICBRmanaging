<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/site_content.php';

const ARTICOLI_GALLERY_MAX = 8;
const ARTICOLI_NEW_PHOTO_SLOTS = 3;

/** Crea la tabella (e la colonna galleria) se mancano. Solo area riservata. */
function articoli_table_ready(): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $pdo = get_db_connection();
        $pdo->query(
            'CREATE TABLE IF NOT EXISTS articoli_eventi (
                id_articolo INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(160) NOT NULL,
                titolo VARCHAR(160) NOT NULL,
                sommario VARCHAR(300) NOT NULL,
                testo MEDIUMTEXT NOT NULL,
                data_evento DATE NOT NULL,
                luogo VARCHAR(160) NULL,
                copertina VARCHAR(120) NULL,
                copertina_alt VARCHAR(200) NULL,
                galleria TEXT NULL,
                pubblicato TINYINT(1) NOT NULL DEFAULT 0,
                id_autore INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_articolo_slug (slug),
                KEY idx_articoli_pubblico (pubblicato, data_evento),
                CONSTRAINT fk_articoli_autore FOREIGN KEY (id_autore) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $column = $pdo->query("SHOW COLUMNS FROM articoli_eventi LIKE 'galleria'")->fetch();
        if (!$column) {
            $pdo->query('ALTER TABLE articoli_eventi ADD COLUMN galleria TEXT NULL AFTER copertina_alt');
        }
        $ready = true;
    } catch (Throwable $e) {
        error_log('ICBR: tabella articoli_eventi non disponibile: ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

/** Galleria: elenco di ['file' => nome, 'alt' => didascalia], solo immagini valide. */
function articoli_gallery_decode(?string $json): array
{
    $data = json_decode((string)$json, true);
    if (!is_array($data)) return [];
    $out = [];
    foreach ($data as $item) {
        if (!is_array($item) || !is_string($item['file'] ?? null) || !site_image_valid($item['file'])) continue;
        $out[] = ['file' => $item['file'], 'alt' => is_string($item['alt'] ?? null) ? mb_substr($item['alt'], 0, 200) : ''];
    }
    return array_slice($out, 0, ARTICOLI_GALLERY_MAX);
}

function articoli_gallery_encode(array $items): ?string
{
    return $items ? json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

function articoli_slug(string $title): string
{
    $map = ['à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u', 'ç' => 'c', 'ñ' => 'n'];
    $slug = strtr(mb_strtolower(trim($title), 'UTF-8'), $map);
    $slug = trim((string)preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
    $slug = trim(substr($slug, 0, 80), '-');
    return $slug !== '' ? $slug : 'evento';
}

function articoli_unique_slug(string $base): string
{
    $stmt = get_db_connection()->prepare('SELECT COUNT(*) FROM articoli_eventi WHERE slug = :slug');
    $slug = $base;
    $n = 2;
    while (true) {
        $stmt->execute(['slug' => $slug]);
        if ((int)$stmt->fetchColumn() === 0) return $slug;
        $slug = $base . '-' . $n++;
    }
}

function articoli_date_label(string $date): string
{
    $months = [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
    $ts = strtotime($date);
    if (!$ts) return $date;
    return (int)date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

/** Paragrafi dal testo semplice: una riga vuota separa i paragrafi. */
function articoli_paragraphs(string $text): array
{
    $parts = preg_split('/\R{2,}/', trim($text)) ?: [];
    return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
}

function articoli_public_list(int $limit = 60): array
{
    try {
        $limit = max(1, min(500, $limit));
        return get_db_connection()->query(
            'SELECT id_articolo, slug, titolo, sommario, data_evento, luogo, copertina, copertina_alt, updated_at
             FROM articoli_eventi WHERE pubblicato = 1
             ORDER BY data_evento DESC, id_articolo DESC LIMIT ' . $limit
        )->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function articoli_public_find(string $slug): ?array
{
    try {
        $stmt = get_db_connection()->prepare('SELECT * FROM articoli_eventi WHERE slug = :slug AND pubblicato = 1 LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function articoli_admin_list(): array
{
    return get_db_connection()->query(
        'SELECT id_articolo, slug, titolo, data_evento, pubblicato, updated_at FROM articoli_eventi ORDER BY data_evento DESC, id_articolo DESC'
    )->fetchAll();
}

function articoli_admin_find(int $id): ?array
{
    $stmt = get_db_connection()->prepare('SELECT * FROM articoli_eventi WHERE id_articolo = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Salva un’immagine caricata; restituisce il nome del file oppure null (con eventuali errori). */
function articoli_save_cover(?array $upload, array &$errors, string $label = 'Copertina'): ?string
{
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (!is_int($upload['error'] ?? null) || $upload['error'] !== UPLOAD_ERR_OK
        || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])
        || !is_int($upload['size'] ?? null) || $upload['size'] <= 0 || $upload['size'] > 6 * 1024 * 1024) {
        $errors[] = $label . ': carica un’immagine valida, massimo 6 MB.';
        return null;
    }
    $info = @getimagesize($upload['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($extensions[$mime]) || ($info['mime'] ?? '') !== $mime
        || $info[0] < 640 || $info[1] < 360 || $info[0] * $info[1] > 24000000) {
        $errors[] = $label . ': usa JPEG, PNG o WebP, almeno 640×360 px e massimo 24 megapixel.';
        return null;
    }
    $name = 'evento-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    $path = __DIR__ . '/../public/assets/images/' . $name;
    if (!move_uploaded_file($upload['tmp_name'], $path)) {
        $errors[] = $label . ': cartella foto non scrivibile. Controlla i permessi.';
        return null;
    }
    chmod($path, 0644);
    return $name;
}
