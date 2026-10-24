<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$templates = $pdo->query('SELECT * FROM template_whatsapp WHERE attivo = 1 ORDER BY nome_template')->fetchAll();
$stagioni  = $pdo->query('SELECT * FROM stagioni ORDER BY attiva DESC, codice_stagione DESC')->fetchAll();
$tipologie = $pdo->query('SELECT id_tipologia, listino_label, tipo FROM tipologie_tesseramento WHERE attiva = 1 ORDER BY tipo')->fetchAll();

$STATI_VALIDI = ['da_pagare', 'acconto', 'saldato', 'eccedenza', 'quota_sconosciuta'];

/**
 * Selezione destinatari: soci con tesseramento nella stagione scelta
 * (o tutte le stagioni) e filtri opzionali. Restituisce le righe con
 * quota/versato/residuo già calcolati per la personalizzazione.
 */
function seleziona_destinatari(PDO $pdo, array $filtri): array
{
    $sql = 'SELECT t.id_tesseramento, t.numero_tessera, t.attivo_portale, t.tessera_fisica,
                   t.quota_tesseramento, tip.quota_standard,
                   s.id_socio, s.nome, s.cognome, s.telefono,
                   st.codice_stagione,
                   COALESCE(NULLIF(t.quota_tesseramento, 0), NULLIF(tip.quota_standard, 0)) AS quota_dovuta,
                   COALESCE(pag.versato_netto, 0) AS versato_netto
            FROM tesseramenti t
            JOIN soci s ON s.id_socio = t.id_socio
            JOIN stagioni st ON st.id_stagione = t.id_stagione
            LEFT JOIN tipologie_tesseramento tip ON tip.id_tipologia = t.id_tipologia
            LEFT JOIN (
                SELECT id_tesseramento,
                       SUM(CASE WHEN tipo_pagamento = \'rimborso\' THEN -importo ELSE importo END) AS versato_netto
                FROM pagamenti_tesseramenti
                GROUP BY id_tesseramento
            ) pag ON pag.id_tesseramento = t.id_tesseramento
            WHERE s.attivo_record = 1';
    $params = [];

    if (!empty($filtri['id_stagione'])) {
        $sql .= ' AND t.id_stagione = :id_stagione';
        $params['id_stagione'] = (int)$filtri['id_stagione'];
    }
    if ($filtri['attivo_portale'] !== '') {
        $sql .= ' AND t.attivo_portale = :attivo';
        $params['attivo'] = (int)$filtri['attivo_portale'];
    }
    if (!empty($filtri['id_tipologia'])) {
        $sql .= ' AND t.id_tipologia = :tipologia';
        $params['tipologia'] = (int)$filtri['id_tipologia'];
    }
    if ($filtri['tessera_fisica'] !== '') {
        $sql .= ' AND t.tessera_fisica = :tessera';
        $params['tessera'] = $filtri['tessera_fisica'];
    }
    if ($filtri['solo_con_telefono']) {
        $sql .= " AND s.telefono IS NOT NULL AND TRIM(s.telefono) <> ''";
    }

    $sql .= ' ORDER BY s.cognome, s.nome LIMIT 500';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $quota = $r['quota_dovuta'] !== null ? (float)$r['quota_dovuta'] : null;
        $netto = (float)$r['versato_netto'];
        $stato = stato_quota($quota, $netto);
        // Filtro per stato pagamento applicato in PHP (dipende dalla logica centralizzata)
        if ($filtri['stato_pagamento'] !== '' && $stato['code'] !== $filtri['stato_pagamento']) {
            continue;
        }
        $r['quota'] = $quota;
        $r['pagato'] = $netto;
        $r['residuo'] = tess_residuo($quota, $netto);
        $rows[] = $r;
    }
    return $rows;
}

$filtri = [
    'id_template'      => (int)($_POST['id_template'] ?? $_GET['id_template'] ?? 0),
    'id_stagione'      => (int)($_POST['id_stagione'] ?? $_GET['id_stagione'] ?? 0),
    'stato_pagamento'  => $_POST['stato_pagamento'] ?? $_GET['stato_pagamento'] ?? '',
    'attivo_portale'   => $_POST['attivo_portale'] ?? $_GET['attivo_portale'] ?? '',
    'id_tipologia'     => (int)($_POST['id_tipologia'] ?? $_GET['id_tipologia'] ?? 0),
    'tessera_fisica'   => $_POST['tessera_fisica'] ?? $_GET['tessera_fisica'] ?? '',
    'solo_con_telefono' => isset($_POST['solo_con_telefono']) || isset($_GET['solo_con_telefono']),
];

$action = $_POST['action'] ?? '';
$saved = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    // -----------------------------------------------------------------
    // Step 2: salvataggio dei messaggi selezionati (stato "preparato")
    // -----------------------------------------------------------------
    if ($action === 'save') {
        $selezionati = array_map('intval', (array)($_POST['includi'] ?? []));
        if ($selezionati && $filtri['id_template']) {
            $tpl = $pdo->prepare('SELECT * FROM template_whatsapp WHERE id_template = :id');
            $tpl->execute(['id' => $filtri['id_template']]);
            $template = $tpl->fetch();
            if ($template) {
                $righe = seleziona_destinatari($pdo, $filtri);
                $ins = $pdo->prepare(
                    'INSERT INTO messaggi_whatsapp
                     (id_socio, id_tesseramento, id_template, telefono_destinatario, testo_finale, stato_messaggio)
                     VALUES (:socio, :tess, :tpl, :tel, :testo, \'preparato\')'
                );
                foreach ($righe as $r) {
                    if (!in_array((int)$r['id_tesseramento'], $selezionati, true)) continue;
                    $vars = [
                        'nome'          => $r['nome'],
                        'cognome'       => $r['cognome'],
                        'nome_completo' => trim($r['nome'] . ' ' . $r['cognome']),
                        'telefono'      => $r['telefono'] ?? '',
                        'quota'         => $r['quota'] !== null ? euro($r['quota']) : 'n/d',
                        'pagato'        => euro($r['pagato']),
                        'residuo'       => $r['residuo'] !== null ? euro($r['residuo']) : 'n/d',
                        'stagione'      => $r['codice_stagione'],
                        'data'          => date('d/m/Y'),
                    ];
                    $ins->execute([
                        'socio' => $r['id_socio'],
                        'tess'  => $r['id_tesseramento'],
                        'tpl'   => $template['id_template'],
                        'tel'   => $r['telefono'] ?? '',
                        'testo' => render_messaggio($template['testo_template'], $vars),
                    ]);
                    $saved++;
                }
            }
        }
        if ($saved > 0) {
            redirect_with_message('/messaggi/list.php', "$saved messaggi preparati. Aprili dalla lista per inviarli con WhatsApp.");
        } else {
            redirect_with_message('/messaggi/compose.php', 'Nessun messaggio da salvare: seleziona almeno un destinatario.', 'error');
        }
    }

    // -----------------------------------------------------------------
    // Step 1: anteprima dei messaggi personalizzati
    // -----------------------------------------------------------------
    if ($action === 'preview' && $filtri['id_template']) {
        $tpl = $pdo->prepare('SELECT * FROM template_whatsapp WHERE id_template = :id');
        $tpl->execute(['id' => $filtri['id_template']]);
        $template = $tpl->fetch();
        if ($template) {
            $righe = seleziona_destinatari($pdo, $filtri);
        }
    }
}

$csrf = generate_csrf_token();
$page_title = 'Componi messaggi';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Componi messaggi WhatsApp</h1>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a class="btn btn-secondary" href="<?= u('/messaggi/template_list.php') ?>">Template</a>
        <a class="btn btn-secondary" href="<?= u('/messaggi/list.php') ?>">Messaggi generati</a>
    </div>
</div>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'preview'): ?>

<?php if (empty($template)): ?>
<div class="flash flash-error">Template non trovato.</div>
<?php elseif (empty($righe)): ?>
<div class="flash flash-error">Nessun destinatario corrisponde ai filtri selezionati.</div>
<?php else: ?>
<p>
    <strong><?= count($righe) ?></strong> destinatari trovati.
    Anteprima con il template <strong><?= h($template['nome_template']) ?></strong>:
    verifica i messaggi, spunta i destinatari da salvare, poi premi "Prepara messaggi".
</p>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id_template" value="<?= (int)$filtri['id_template'] ?>">
    <input type="hidden" name="id_stagione" value="<?= (int)$filtri['id_stagione'] ?>">
    <input type="hidden" name="stato_pagamento" value="<?= h($filtri['stato_pagamento']) ?>">
    <input type="hidden" name="attivo_portale" value="<?= h($filtri['attivo_portale']) ?>">
    <input type="hidden" name="id_tipologia" value="<?= (int)$filtri['id_tipologia'] ?>">
    <input type="hidden" name="tessera_fisica" value="<?= h($filtri['tessera_fisica']) ?>">
    <?php if ($filtri['solo_con_telefono']): ?><input type="hidden" name="solo_con_telefono" value="1"><?php endif; ?>

    <table class="data-table">
        <thead>
        <tr>
            <th><input type="checkbox" id="sel-tutti" checked title="Seleziona tutti"></th>
            <th>Socio</th>
            <th>Telefono</th>
            <th>Stato quota</th>
            <th>Anteprima messaggio</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($righe as $r): ?>
            <?php
                $wa = wa_phone($r['telefono']);
                $vars = [
                    'nome'          => $r['nome'],
                    'cognome'       => $r['cognome'],
                    'nome_completo' => trim($r['nome'] . ' ' . $r['cognome']),
                    'telefono'      => $r['telefono'] ?? '',
                    'quota'         => $r['quota'] !== null ? euro($r['quota']) : 'n/d',
                    'pagato'        => euro($r['pagato']),
                    'residuo'       => $r['residuo'] !== null ? euro($r['residuo']) : 'n/d',
                    'stagione'      => $r['codice_stagione'],
                    'data'          => date('d/m/Y'),
                ];
                $testo = render_messaggio($template['testo_template'], $vars);
                $stato = stato_quota($r['quota'], $r['pagato']);
            ?>
            <tr>
                <td><input type="checkbox" name="includi[]" value="<?= (int)$r['id_tesseramento'] ?>" class="chk-includi" checked></td>
                <td><?= h($r['cognome'] . ' ' . $r['nome']) ?></td>
                <td>
                    <?php if ($wa): ?>
                        <span class="badge badge-green"><?= h($r['telefono']) ?></span>
                    <?php else: ?>
                        <span class="badge badge-red" title="Numero non valido per WhatsApp: si può solo copiare il testo"><?= h($r['telefono'] ?: '—') ?></span>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= $stato['badge'] ?>"><?= h($stato['label']) ?></span></td>
                <td style="max-width:460px;">
                    <textarea readonly rows="3" class="msg-preview" data-copy><?= h($testo) ?></textarea>
                    <div style="margin-top:.3rem;display:flex;gap:.4rem;">
                        <button type="button" class="btn btn-sm btn-secondary js-copia">Copia testo</button>
                        <?php if ($wa): ?>
                        <a class="btn btn-sm" target="_blank" rel="noopener"
                           href="https://wa.me/<?= h($wa) ?>?text=<?= rawurlencode($testo) ?>">Apri WhatsApp</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="form-actions" style="margin-top:1rem;">
        <button type="submit" class="btn">Prepara <?= count($righe) ?> messaggi selezionati</button>
    </div>
</form>

<script>
// Seleziona/deseleziona tutti i destinatari
document.getElementById('sel-tutti').addEventListener('change', function () {
    document.querySelectorAll('.chk-includi').forEach(function (c) { c.checked = this.checked; }, this);
});
// Copia il testo del messaggio negli appunti
document.querySelectorAll('.js-copia').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var ta = btn.closest('td').querySelector('[data-copy]');
        ta.select();
        document.execCommand('copy');
        var old = btn.textContent;
        btn.textContent = 'Copiato ✓';
        setTimeout(function () { btn.textContent = old; }, 1200);
    });
});
</script>

<?php endif; ?>

<?php else: ?>

<!-- ----------------------------------------------------------------- -->
<!-- Step 0: scelta template e filtri destinatari                        -->
<!-- ----------------------------------------------------------------- -->
<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="action" value="preview">

    <div class="form-group">
        <label>Template *</label>
        <select name="id_template" required>
            <option value="">— Seleziona —</option>
            <?php foreach ($templates as $tp): ?>
                <option value="<?= (int)$tp['id_template'] ?>" <?= $filtri['id_template'] == $tp['id_template'] ? 'selected' : '' ?>>
                    <?= h($tp['nome_template']) ?><?= $tp['categoria'] ? ' (' . h($tp['categoria']) . ')' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($templates)): ?>
        <small style="color:#b00">Nessun template attivo: creane uno prima.</small>
        <?php endif; ?>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Stagione</label>
            <select name="id_stagione">
                <option value="">— Tutte —</option>
                <?php foreach ($stagioni as $s): ?>
                    <option value="<?= (int)$s['id_stagione'] ?>" <?= $filtri['id_stagione'] == $s['id_stagione'] ? 'selected' : '' ?>>
                        <?= h($s['codice_stagione']) ?> <?= $s['attiva'] ? '(attiva)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Stato pagamento</label>
            <select name="stato_pagamento">
                <option value="">— Tutti —</option>
                <option value="da_pagare" <?= $filtri['stato_pagamento'] === 'da_pagare' ? 'selected' : '' ?>>Da saldare</option>
                <option value="acconto" <?= $filtri['stato_pagamento'] === 'acconto' ? 'selected' : '' ?>>Solo acconto</option>
                <option value="saldato" <?= $filtri['stato_pagamento'] === 'saldato' ? 'selected' : '' ?>>Saldati</option>
                <option value="eccedenza" <?= $filtri['stato_pagamento'] === 'eccedenza' ? 'selected' : '' ?>>Eccedenza</option>
                <option value="quota_sconosciuta" <?= $filtri['stato_pagamento'] === 'quota_sconosciuta' ? 'selected' : '' ?>>Quota non definita</option>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Attivo portale</label>
            <select name="attivo_portale">
                <option value="">— Tutti —</option>
                <option value="1" <?= $filtri['attivo_portale'] === '1' ? 'selected' : '' ?>>Sì</option>
                <option value="0" <?= $filtri['attivo_portale'] === '0' ? 'selected' : '' ?>>No</option>
            </select>
        </div>
        <div class="form-group">
            <label>Tipologia</label>
            <select name="id_tipologia">
                <option value="">— Tutte —</option>
                <?php foreach ($tipologie as $tp): ?>
                    <option value="<?= (int)$tp['id_tipologia'] ?>" <?= $filtri['id_tipologia'] == $tp['id_tipologia'] ? 'selected' : '' ?>>
                        <?= h($tp['listino_label'] ?: $tp['tipo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Tessera fisica</label>
            <select name="tessera_fisica">
                <option value="">— Tutti —</option>
                <?php foreach (TESSERA_FISICA_STATI as $val => $label): ?>
                    <option value="<?= h($val) ?>" <?= $filtri['tessera_fisica'] === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <label class="checkbox-inline"><input type="checkbox" name="solo_con_telefono" value="1" <?= $filtri['solo_con_telefono'] ? 'checked' : '' ?>> Solo soci con telefono</label>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Genera anteprima</button>
    </div>
</form>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
