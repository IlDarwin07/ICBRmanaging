<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$id_socio = (int)($_GET['id'] ?? 0);
if ($id_socio <= 0) { http_response_code(404); die('Socio non trovato.'); }

$stmt = $pdo->prepare('SELECT * FROM soci WHERE id_socio = :id');
$stmt->execute(['id' => $id_socio]);
$socio = $stmt->fetch();
if (!$socio) { http_response_code(404); die('Socio non trovato.'); }

// Stagione attiva
$stagione_attiva = $pdo->query(
    "SELECT id_stagione, codice_stagione FROM stagioni ORDER BY codice_stagione DESC LIMIT 1"
)->fetch();

// Storico tesseramenti completo
$stmt = $pdo->prepare(
    'SELECT t.*, s.codice_stagione, tt.tipo AS tipologia_tipo
     FROM tesseramenti t
     LEFT JOIN stagioni s ON s.id_stagione = t.id_stagione
     LEFT JOIN tipologie_tesseramento tt ON tt.id_tipologia = t.id_tipologia
     WHERE t.id_socio = :id
     ORDER BY s.codice_stagione DESC'
);
$stmt->execute(['id' => $id_socio]);
$tesseramenti = $stmt->fetchAll();

// Tessera stagione corrente
$tessera_corrente = null;
if ($stagione_attiva) {
    foreach ($tesseramenti as $t) {
        if ((int)$t['id_stagione'] === (int)$stagione_attiva['id_stagione']) {
            $tessera_corrente = $t;
            break;
        }
    }
}

// Telefono valido: almeno 6 cifre reali
function telefono_valido(?string $tel): bool {
    if (!$tel) return false;
    $clean = preg_replace('/[^0-9]/', '', $tel);
    return strlen($clean) >= 6;
}

$page_title = 'Scheda socio — ' . ($socio['cognome'] ?? '') . ' ' . ($socio['nome'] ?? '');
require __DIR__ . '/../../includes/layout_header.php';

// SVG icons inline
$ico_cal   = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
$ico_pin   = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
$ico_phone = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.35 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6 6l.96-.96a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
$ico_edit  = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
$ico_user  = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
$ico_home  = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>';
$ico_card  = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>';
?>

<style>
.scheda-hero {
    background: linear-gradient(135deg, var(--color-primary, #003f8a) 0%, #005cbf 100%);
    color: #fff;
    border-radius: 10px;
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.scheda-hero-avatar {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: bold; color: #fff;
    flex-shrink: 0;
}
.scheda-hero-info { flex: 1; min-width: 200px; }
.scheda-hero-info h1 { margin: 0 0 .3rem; font-size: 1.6rem; }
.scheda-hero-meta { font-size: .875rem; opacity: .9; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
.scheda-hero-meta span { display:flex; align-items:center; }
.scheda-hero-badge { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; margin-top: .5rem; }
.scheda-hero-actions { display: flex; gap: .5rem; flex-wrap: wrap; align-items: flex-start; }
.scheda-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; }
.scheda-section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom: .85rem; padding-bottom: .5rem; border-bottom: 2px solid #f0f0f0; }
.scheda-section-header h2 { font-size: 1rem; font-weight: 700; color: var(--color-primary, #003f8a); margin: 0; text-transform: uppercase; letter-spacing: .04em; display:flex; align-items:center; gap:.4rem; }
.scheda-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: .75rem 1.5rem; }
.scheda-field { display: flex; flex-direction: column; }
.scheda-field .lbl { font-size: .72rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .2rem; }
.scheda-field .val { font-size: .93rem; color: #111; }
.scheda-field .val a { color: var(--color-primary, #003f8a); text-decoration: none; }
/* Tessera card */
.tess-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: .9rem 1.1rem; background: #fafafa; margin-bottom: .6rem; display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; }
.tess-card:last-child { margin-bottom: 0; }
.tess-card.tess-corrente { border-color: #93c5fd; background: #eff6ff; }
.tess-card .t-stagione { font-size: .78rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing:.04em; }
.tess-card .t-num { font-size: 1.1rem; font-weight: 700; color: #003f8a; }
.tess-card .t-tipo { font-size: .82rem; color: #555; }
.tess-card .t-badges { display:flex; flex-wrap:wrap; gap:.35rem; align-items:center; }
.tess-card .t-actions { margin-left:auto; }
.badge-current { font-size:.68rem; font-weight:700; background:#1d4ed8; color:#fff; border-radius:4px; padding:.1rem .4rem; text-transform:uppercase; letter-spacing:.04em; display:inline-block; margin-top:.2rem; }
</style>

<!-- HERO -->
<div class="scheda-hero">
    <div class="scheda-hero-avatar">
        <?= mb_strtoupper(mb_substr($socio['nome'] ?? '?', 0, 1)) . mb_strtoupper(mb_substr($socio['cognome'] ?? '', 0, 1)) ?>
    </div>
    <div class="scheda-hero-info">
        <h1><?= h($socio['cognome'] . ' ' . $socio['nome']) ?></h1>
        <div class="scheda-hero-meta">
            <?php if ($socio['data_nascita']): ?>
                <span><?= $ico_cal ?><?= date('d/m/Y', strtotime($socio['data_nascita'])) ?></span>
            <?php endif; ?>
            <?php if ($socio['comune']): ?>
                <span><?= $ico_pin ?><?= h($socio['comune']) ?><?= $socio['provincia'] ? ' (' . h($socio['provincia']) . ')' : '' ?></span>
            <?php endif; ?>
            <?php if (telefono_valido($socio['telefono'] ?? null)): ?>
                <span><?= $ico_phone ?><?= h($socio['telefono']) ?></span>
            <?php endif; ?>
        </div>
        <div class="scheda-hero-badge">
            <span class="badge <?= $socio['attivo_record'] ? 'badge-green' : 'badge-gray' ?>" style="background:rgba(255,255,255,.25);color:#fff;border:1px solid rgba(255,255,255,.4)">
                <?= $socio['attivo_record'] ? '&#10003; Attivo' : '&#10007; Disattivato' ?>
            </span>
            <?php if ($tessera_corrente && $tessera_corrente['numero_tessera']): ?>
                <span style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.35);border-radius:4px;padding:.2rem .6rem;font-size:.83rem">
                    Tessera <?= h($stagione_attiva['codice_stagione']) ?>: <strong><?= h($tessera_corrente['numero_tessera']) ?></strong>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="scheda-hero-actions">
        <a class="btn" href="<?= $base ?>/soci/edit.php?id=<?= $id_socio ?>"><?= $ico_edit ?>Modifica</a>
        <a class="btn btn-secondary" href="<?= $base ?>/tesseramenti/create.php?id_socio=<?= $id_socio ?>">+ Tesseramento</a>
        <a class="btn btn-secondary" href="<?= $base ?>/soci/list.php" style="opacity:.85">&laquo; Elenco</a>
    </div>
</div>

<!-- DATI ANAGRAFICI -->
<div class="scheda-section">
    <div class="scheda-section-header">
        <h2><?= $ico_user ?> Dati anagrafici</h2>
    </div>
    <div class="scheda-grid">
        <div class="scheda-field"><span class="lbl">Cognome</span><span class="val"><?= h($socio['cognome']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Nome</span><span class="val"><?= h($socio['nome']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Sesso</span><span class="val"><?= h($socio['sesso']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Data di nascita</span><span class="val"><?= $socio['data_nascita'] ? date('d/m/Y', strtotime($socio['data_nascita'])) : '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Comune di nascita</span><span class="val"><?= h($socio['comune_nascita']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Nazionalità</span><span class="val"><?= h($socio['nazionalita']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Codice fiscale</span><span class="val" style="font-family:monospace;letter-spacing:.05em"><?= h($socio['codice_fiscale']) ?: '—' ?></span></div>
    </div>
</div>

<!-- RESIDENZA E CONTATTI -->
<div class="scheda-section">
    <div class="scheda-section-header">
        <h2><?= $ico_home ?> Residenza &amp; Contatti</h2>
    </div>
    <div class="scheda-grid">
        <div class="scheda-field"><span class="lbl">Indirizzo</span><span class="val"><?= h(trim(($socio['indirizzo'] ?? '') . ' ' . ($socio['numero_civico'] ?? ''))) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">CAP</span><span class="val"><?= h($socio['cap']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Comune</span><span class="val"><?= h($socio['comune']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Provincia</span><span class="val"><?= h($socio['provincia']) ?: '—' ?></span></div>
        <div class="scheda-field">
            <span class="lbl">Telefono</span>
            <span class="val">
                <?php if (telefono_valido($socio['telefono'] ?? null)): ?>
                    <a href="tel:<?= h($socio['telefono']) ?>"><?= h($socio['telefono']) ?></a>
                <?php else: ?>—<?php endif; ?>
            </span>
        </div>
        <div class="scheda-field">
            <span class="lbl">Email</span>
            <span class="val">
                <?php if (!empty($socio['email'])): ?>
                    <a href="mailto:<?= h($socio['email']) ?>"><?= h($socio['email']) ?></a>
                <?php else: ?>—<?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- TESSERAMENTI (corrente + storico unificati) -->
<div class="scheda-section">
    <div class="scheda-section-header">
        <h2><?= $ico_card ?> Tesseramenti</h2>
        <a class="btn btn-sm" href="<?= $base ?>/tesseramenti/create.php?id_socio=<?= $id_socio ?>">+ Nuovo</a>
    </div>

    <?php if (empty($tesseramenti)): ?>
        <p class="note">Nessun tesseramento registrato. <a href="<?= $base ?>/tesseramenti/create.php?id_socio=<?= $id_socio ?>">+ Crea tesseramento</a></p>
    <?php else: ?>
        <?php foreach ($tesseramenti as $t):
            $is_corrente = $stagione_attiva && (int)$t['id_stagione'] === (int)$stagione_attiva['id_stagione'];
            $quota = (float)($t['quota_associativa'] ?? 0);
        ?>
        <div class="tess-card <?= $is_corrente ? 'tess-corrente' : '' ?>">
            <!-- Stagione -->
            <div style="min-width:80px">
                <div class="t-stagione"><?= h($t['codice_stagione']) ?></div>
                <?php if ($is_corrente): ?><span class="badge-current">Corrente</span><?php endif; ?>
            </div>
            <!-- Numero + tipo -->
            <div style="min-width:130px">
                <div class="t-num"><?= $t['numero_tessera'] ? '# ' . h($t['numero_tessera']) : '<span style="color:#9ca3af;font-size:.9rem">N/A</span>' ?></div>
                <div class="t-tipo"><?= h($t['tipologia_tipo'] ?? $t['tipo_portale'] ?? '—') ?></div>
            </div>
            <!-- Badge -->
            <div class="t-badges">
                <span class="badge <?= $t['attivo_portale']      ? 'badge-green'  : 'badge-gray'   ?>">Portale: <?= $t['attivo_portale']      ? 'Attivo'      : 'Non attivo'     ?></span>
                <span class="badge <?= $t['tessera_fisica']      ? 'badge-green'  : 'badge-gray'   ?>">Tessera fisica: <?= $t['tessera_fisica']      ? 'Consegnata'  : 'Non consegnata' ?></span>
                <span class="badge <?= $t['conferma_anagrafica'] ? 'badge-green'  : 'badge-orange' ?>">Anagrafica: <?= $t['conferma_anagrafica'] ? 'Confermata'  : 'Da confermare'  ?></span>
                <?php if ($quota > 0): ?>
                    <span class="badge badge-gray">Quota: <?= number_format($quota, 2, ',', '.') ?> €</span>
                <?php endif; ?>
            </div>
            <!-- Azioni -->
            <div class="t-actions">
                <a class="btn btn-sm btn-secondary" href="<?= $base ?>/tesseramenti/view.php?id=<?= (int)$t['id_tesseramento'] ?>">Dettaglio</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
