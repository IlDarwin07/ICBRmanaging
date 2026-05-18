<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'collaboratore']);
$pageTitle = 'Gestione Partite';
include __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <h2><i class="fas fa-plus-circle"></i> Gestione Partite</h2>

    <div class="form-card">
        <h3><i class="fas fa-plus"></i> Nuova Partita</h3>
        <form id="newMatchForm" onsubmit="createMatch(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="titolo">Titolo Evento</label>
                    <input type="text" id="titolo" name="titolo" required placeholder="es. Serie A - Giornata 10">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="squadra_casa">Squadra Casa</label>
                    <input type="text" id="squadra_casa" name="squadra_casa" required placeholder="es. Brindisi FC">
                </div>
                <div class="form-group">
                    <label for="squadra_ospite">Squadra Ospite</label>
                    <input type="text" id="squadra_ospite" name="squadra_ospite" required placeholder="es. Lecce">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="data_partita">Data Partita</label>
                    <input type="date" id="data_partita" name="data_partita" required>
                </div>
                <div class="form-group">
                    <label for="ora_partita">Ora Partita</label>
                    <input type="time" id="ora_partita" name="ora_partita" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="luogo">Luogo</label>
                    <input type="text" id="luogo" name="luogo" value="Sede Interclub Brindisi" placeholder="Luogo dell'evento">
                </div>
                <div class="form-group">
                    <label for="num_posti">Numero Posti</label>
                    <input type="number" id="num_posti" name="num_posti" value="50" min="1" max="500" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Crea Partita
            </button>
        </form>
    </div>

    <div class="section">
        <h3><i class="fas fa-list"></i> Partite Create</h3>
        <div id="matches-list" class="table-container">
            <p class="loading"><i class="fas fa-spinner fa-spin"></i> Caricamento...</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadMatches);

function createMatch(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'create');

    fetch('/api/partite.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                e.target.reset();
                document.getElementById('luogo').value = 'Sede Interclub Brindisi';
                document.getElementById('num_posti').value = '50';
                loadMatches();
            }
        });
}

function loadMatches() {
    fetch('/api/partite.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderMatches(data.partite);
            }
        });
}

function renderMatches(partite) {
    const container = document.getElementById('matches-list');
    if (partite.length === 0) {
        container.innerHTML = '<p class="empty-state">Nessuna partita creata</p>';
        return;
    }

    container.innerHTML = `
    <table class="table">
        <thead>
            <tr>
                <th>Titolo</th>
                <th>Squadre</th>
                <th>Data/Ora</th>
                <th>Prenotazioni</th>
                <th>Stato</th>
                <th>Azioni</th>
            </tr>
        </thead>
        <tbody>
            ${partite.map(p => `
            <tr>
                <td>${escapeHtml(p.titolo)}</td>
                <td>${escapeHtml(p.squadra_casa)} vs ${escapeHtml(p.squadra_ospite)}</td>
                <td>${new Date(p.data_partita).toLocaleDateString('it-IT')} ${p.ora_partita}</td>
                <td>${p.num_prenotazioni} / ${p.num_posti}</td>
                <td><span class="badge badge-${p.stato}">${capitalize(p.stato)}</span></td>
                <td class="actions">
                    <select onchange="changeStato(${p.id}, this.value)" class="select-sm">
                        <option value="" disabled selected>Cambia stato</option>
                        <option value="aperta" ${p.stato === 'aperta' ? 'disabled' : ''}>Aperta</option>
                        <option value="chiusa" ${p.stato === 'chiusa' ? 'disabled' : ''}>Chiusa</option>
                        <option value="conclusa" ${p.stato === 'conclusa' ? 'disabled' : ''}>Conclusa</option>
                    </select>
                    <a href="/pages/assegna_posti.php?partita_id=${p.id}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-chair"></i> Posti
                    </a>
                    <button class="btn btn-danger btn-sm" onclick="deleteMatch(${p.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            `).join('')}
        </tbody>
    </table>`;
}

function changeStato(id, stato) {
    const formData = new FormData();
    formData.append('action', 'update_stato');
    formData.append('partita_id', id);
    formData.append('stato', stato);

    fetch('/api/partite.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            loadMatches();
        });
}

function deleteMatch(id) {
    if (!confirm('Sei sicuro di voler eliminare questa partita? Tutte le prenotazioni e assegnazioni saranno eliminate.')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('partita_id', id);

    fetch('/api/partite.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) loadMatches();
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
