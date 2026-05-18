<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'collaboratore']);
$pageTitle = 'Assegna Posti';

$db = Database::getInstance()->getConnection();
$partita_id = $_GET['partita_id'] ?? null;

include __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <h2><i class="fas fa-chair"></i> Assegnazione Posti</h2>

    <div class="form-group">
        <label for="select-partita">Seleziona Partita</label>
        <select id="select-partita" onchange="loadPrenotazioni(this.value)" class="select-lg">
            <option value="">-- Seleziona una partita --</option>
        </select>
    </div>

    <div id="assignment-area" class="hidden">
        <div id="match-info" class="info-banner"></div>

        <div class="action-bar">
            <button class="btn btn-primary" onclick="autoAssign()">
                <i class="fas fa-magic"></i> Assegna Automaticamente
            </button>
        </div>

        <div class="two-columns">
            <div class="column">
                <h3><i class="fas fa-users"></i> Soci Prenotati</h3>
                <div id="prenotazioni-list"></div>
            </div>
            <div class="column">
                <h3><i class="fas fa-th"></i> Mappa Posti</h3>
                <div id="seats-map"></div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPartitaId = null;
let currentPartita = null;

document.addEventListener('DOMContentLoaded', () => {
    loadPartiteSelect();
});

function loadPartiteSelect() {
    fetch('/api/partite.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('select-partita');
                data.partite.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    const dataFormatted = new Date(p.data_partita).toLocaleDateString('it-IT');
                    opt.textContent = `${p.titolo} - ${p.squadra_casa} vs ${p.squadra_ospite} (${dataFormatted})`;
                    select.appendChild(opt);
                });

                const urlParams = new URLSearchParams(window.location.search);
                const preselect = urlParams.get('partita_id');
                if (preselect) {
                    select.value = preselect;
                    loadPrenotazioni(preselect);
                }
            }
        });
}

function loadPrenotazioni(partitaId) {
    if (!partitaId) {
        document.getElementById('assignment-area').classList.add('hidden');
        return;
    }

    currentPartitaId = partitaId;
    document.getElementById('assignment-area').classList.remove('hidden');

    fetch('/api/partite.php?id=' + partitaId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                currentPartita = data.partita;
                document.getElementById('match-info').innerHTML = `
                    <strong>${escapeHtml(data.partita.titolo)}</strong> - 
                    ${escapeHtml(data.partita.squadra_casa)} vs ${escapeHtml(data.partita.squadra_ospite)} | 
                    ${new Date(data.partita.data_partita).toLocaleDateString('it-IT')} ore ${data.partita.ora_partita} |
                    Posti totali: <strong>${data.partita.num_posti}</strong> |
                    Prenotazioni: <strong>${data.partita.num_prenotazioni}</strong>
                `;
            }
        });

    fetch('/api/prenotazioni.php?partita_id=' + partitaId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderPrenotazioni(data.prenotazioni);
            }
        });

    fetch('/api/assegnazioni.php?partita_id=' + partitaId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderSeatsMap(data.assegnazioni);
            }
        });
}

function renderPrenotazioni(prenotazioni) {
    const container = document.getElementById('prenotazioni-list');
    if (prenotazioni.length === 0) {
        container.innerHTML = '<p class="empty-state">Nessuna prenotazione per questa partita</p>';
        return;
    }

    container.innerHTML = `
    <table class="table table-compact">
        <thead>
            <tr>
                <th>Socio</th>
                <th>Sedia</th>
                <th>Azione</th>
            </tr>
        </thead>
        <tbody>
            ${prenotazioni.map(p => `
            <tr>
                <td><strong>${escapeHtml(p.cognome)} ${escapeHtml(p.nome)}</strong><br><small>@${escapeHtml(p.username)}</small></td>
                <td>
                    ${p.numero_sedia ? 
                        `<span class="badge badge-success">Sedia ${p.numero_sedia}</span>` : 
                        `<span class="badge badge-warning">Non assegnata</span>`
                    }
                </td>
                <td>
                    <div class="assign-form">
                        <input type="number" id="sedia-${p.id}" min="1" max="${currentPartita ? currentPartita.num_posti : 999}" 
                            placeholder="N." class="input-sm" value="${p.numero_sedia || ''}">
                        <button class="btn btn-primary btn-xs" onclick="assignSeat(${p.id}, document.getElementById('sedia-${p.id}').value)">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </td>
            </tr>
            `).join('')}
        </tbody>
    </table>`;
}

function renderSeatsMap(assegnazioni) {
    const container = document.getElementById('seats-map');
    if (!currentPartita) return;

    const totalSeats = currentPartita.num_posti;
    const assignedMap = {};
    assegnazioni.forEach(a => {
        assignedMap[a.numero_sedia] = a;
    });

    let html = '<div class="seats-grid">';
    for (let i = 1; i <= totalSeats; i++) {
        const assigned = assignedMap[i];
        const cls = assigned ? 'seat occupied' : 'seat available';
        const title = assigned ? `${assigned.cognome} ${assigned.nome}` : `Posto ${i} - Disponibile`;
        html += `<div class="${cls}" title="${escapeHtml(title)}">${i}</div>`;
    }
    html += '</div>';
    html += '<div class="seats-legend"><span class="seat available">Disponibile</span><span class="seat occupied">Occupato</span></div>';
    container.innerHTML = html;
}

function assignSeat(prenotazioneId, numero) {
    if (!numero || numero < 1) {
        alert('Inserisci un numero di sedia valido');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'assign');
    formData.append('prenotazione_id', prenotazioneId);
    formData.append('numero_sedia', numero);

    fetch('/api/assegnazioni.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                loadPrenotazioni(currentPartitaId);
            }
        });
}

function autoAssign() {
    if (!confirm('Vuoi assegnare automaticamente i posti ai soci senza posto?')) return;

    const formData = new FormData();
    formData.append('action', 'auto_assign');
    formData.append('partita_id', currentPartitaId);

    fetch('/api/assegnazioni.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                loadPrenotazioni(currentPartitaId);
            }
        });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
