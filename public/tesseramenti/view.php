<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT t.*,
            s.nome, s.cognome, s.telefono, s.email, s.codice_fiscale,
            st.codice_stagione, st.descrizione AS desc_stagione,
            tip.tipo AS tipo_tip, tip.listino_label, tip.quota_standard
     FROM tesseramenti t
     JOIN soci s ON s.id_socio = t.id_socio
     JOIN stagioni st ON st.id_stagione = t.id_stagione
     LEFT JOIN tipologie_tesseramento tip ON tip.id_tipologia = t.id_tipologia
     WHERE t.id_tesseramento = :id'
);
$stmt->execute(['id' => $id]);
$t = $stmt->fetch();

if (!$t) {
    redirect_with_message('/tesseramenti/list.php', 'Tesseramento non trovato.', 'error');
}

// Pagamenti associati
$stmt_p = $pdo->prepare(
    'SELECT p.*, ua.nome AS op_nome, ua.cognome AS op_cognome
     FROM pagamenti_tesseramenti p
     LEFT JOIN utenti_admin ua ON ua.id_utente = p.registrato_da
     WHERE p.id_tesseramento = :id
     ORDER BY p.data_pagamento DESC'
);
$stmt_p->execute(['id' => $id]);
$pagamenti = $stmt_p->fetchAll();

// Totale pagato
$stmt_tot = $pdo->prepare('SELECT COALESCE(SUM(importo),0) FROM pagamenti_tesseramenti WHERE id_tesseramento = :id');
$stmt_tot->execute(['id' => $id]);
$totale_pagato = (float)$stmt_tot->fetchColumn();

$page_title = 'Tesseramento ' . $t['cognome'] . ' ' . $t['nome'];
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Tesseramento: <?= h($t['cognome'] . ' ' . $t['nome']) ?></h1>
    <div class="btn-group">
        <a class="btn btn-secondary" href="/tesseramenti/list.php">← Lista</a>
        <a class="btn" href="/tesseramenti/edit.php?id=<?= $id ?>">Modifica</a>
        <a class="btn" href="/tesseramenti/pagamento_add.php?id_tesseramento=<?= $id ?>">+ Pagamento</a>
    </div>
</div>

<div class="detail-grid">
    <section class="detail-card">
        <h2>Socio</h2>
        <dl>
            <dt>Nome</dt><dd><?= h($t['nome'] . ' ' . $t['cognome']) ?></dd>
            <dt>Codice fiscale</dt><dd><?= h($t['codice_fiscale']) ?: '—' ?></dd>
            <dt>Telefono</dt><dd><?= h($t['telefono']) ?: '—' ?></dd>
            <dt>Email</dt><dd><?= h($t['email']) ?: '—' ?></dd>
        </dl>
        <a href="/soci/view.php?id=<?= (int)$t['id_socio'] ?>">Apri scheda socio →</a>
    </section>

    <section class="detail-card">
        <h2>Tesseramento</h2>
        <dl>
            <dt>Stagione</dt><dd><?= h($t['codice_stagione']) ?> <?= h($t['desc_stagione']) ?></dd>
            <dt>Tipologia</dt><dd><?= h($t['listino_label'] ?: $t['tipo_tip']) ?: '—' ?></dd>
            <dt>Listino originale</dt><dd><?= h($t['listino_originale']) ?: '—' ?></dd>
            <dt>N° tessera</dt><dd><?= h($t['numero_tessera']) ?: '—' ?></dd>
            <dt>Ruolo portale</dt><dd><?= h($t['ruolo_portale']) ?: '—' ?></dd>
            <dt>Tipo portale</dt><dd><?= h($t['tipo_portale']) ?: '—' ?></dd>
            <dt>Attivo portale</dt><dd><?= $t['attivo_portale'] ? '<span class="badge badge-ok">Sì</span>' : '<span class="badge">No</span>' ?></dd>
            <dt>Socio+</dt><dd><?= $t['socio_plus'] ? '<span class="badge badge-plus">Sì</span>' : 'No' ?></dd>
            <dt>Attivo scorsa stagione</dt><dd><?= $t['attivo_scorsa_stagione'] ? 'Sì' : 'No' ?></dd>
            <dt>Anagrafica confermata</dt><dd><?= $t['conferma_anagrafica'] ? 'Sì' : 'No' ?></dd>
            <dt>Tessera fisica</dt><dd><?= $t['tessera_fisica'] ? 'Sì' : 'No' ?></dd>
            <dt>Data/ora attivazione</dt><dd><?= $t['data_ora_attivazione'] ? date('d/m/Y H:i', strtotime($t['data_ora_attivazione'])) : '—' ?></dd>
            <dt>Fonte</dt><dd><?= h($t['fonte_inserimento']) ?></dd>
        </dl>
    </section>

    <section class="detail-card">
        <h2>Pagamenti</h2>
        <?php if ($t['quota_standard']): ?>
            <p>Quota standard: <strong>€ <?= number_format((float)$t['quota_standard'], 2, ',', '.') ?></strong>
               | Pagato: <strong>€ <?= number_format($totale_pagato, 2, ',', '.') ?></strong>
               | Residuo: <strong>€ <?= number_format(max(0, (float)$t['quota_standard'] - $totale_pagato), 2, ',', '.') ?></strong>
            </p>
        <?php else: ?>
            <p>Totale pagato: <strong>€ <?= number_format($totale_pagato, 2, ',', '.') ?></strong></p>
        <?php endif; ?>

        <?php if (empty($pagamenti)): ?>
            <p><em>Nessun pagamento registrato.</em></p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Data</th><th>Tipo</th><th>Importo</th><th>Metodo</th><th>Rif.</th><th>Operatore</th></tr></thead>
            <tbody>
            <?php foreach ($pagamenti as $p): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($p['data_pagamento'])) ?></td>
                    <td><?= h($p['tipo_pagamento']) ?></td>
                    <td>€ <?= number_format((float)$p['importo'], 2, ',', '.') ?></td>
                    <td><?= h($p['metodo_pagamento']) ?></td>
                    <td><?= h($p['riferimento']) ?: '—' ?></td>
                    <td><?= h($p['op_nome'] . ' ' . $p['op_cognome']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <a class="btn" href="/tesseramenti/pagamento_add.php?id_tesseramento=<?= $id ?>">+ Registra pagamento</a>
    </section>
</div>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
