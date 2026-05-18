<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pageTitle = 'Partite';
include __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <h2><i class="fas fa-calendar"></i> Partite</h2>

    <div class="filter-bar">
        <button class="filter-btn active" onclick="filterPartite('tutte')">Tutte</button>
        <button class="filter-btn" onclick="filterPartite('aperta')">Aperte</button>
        <button class="filter-btn" onclick="filterPartite('chiusa')">Chiuse</button>
        <button class="filter-btn" onclick="filterPartite('conclusa')">Concluse</button>
    </div>

    <div id="partite-list" class="cards-grid">
        <p class="loading"><i class="fas fa-spinner fa-spin"></i> Caricamento partite...</p>
    </div>
</div>

<script>
let allPartite = [];
let miePrenotazioni = [];

document.addEventListener('DOMContentLoaded', () => {
    loadPartite();
    loadMiePrenotazioni();
});

function loadPartite() {
    fetch('/api/partite.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                allPartite = data.partite;
                renderPartite(allPartite);
            }
        });
}

function loadMiePrenotazioni() {
    fetch('/api/prenotazioni.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                miePrenotazioni = data.prenotazioni;
                renderPartite(allPartite);
            }
        });
}

function filterPartite(stato) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    event.target.classList.add('active');

    if (stato === 'tutte') {
        renderPartite(allPartite);
    } else {
        renderPartite(allPartite.filter(p => p.stato === stato));
    }
}

function renderPartite(partite) {
    const container = document.getElementById('partite-list');
    if (partite.length === 0) {
        container.innerHTML = '<p class="empty-state"><i class="fas fa-calendar-times"></i> Nessuna partita trovata</p>';
        return;
    }

    container.innerHTML = partite.map(p => {
        const prenotato = miePrenotazioni.find(pr => pr.partita_id == p.id);
        const dataFormatted = new Date(p.data_partita).toLocaleDateString('it-IT');
        const postiRimasti = p.num_posti - p.num_prenotazioni;

        return `
        <div class="card">
            <div class="card-header">
                <h4>${escapeHtml(p.titolo)}</h4>
                <span class="badge badge-${p.stato}">${capitalize(p.stato)}</span>
            </div>
            <div class="card-body">
                <p><i class="fas fa-futbol"></i> ${escapeHtml(p.squadra_casa)} vs ${escapeHtml(p.squadra_ospite)}</p>
                <p><i class="fas fa-calendar"></i> ${dataFormatted} ore ${p.ora_partita}</p>
                <p><i class="fas fa-map-marker-alt"></i> ${escapeHtml(p.luogo)}</p>
                <p><i class="fas fa-users"></i> ${p.num_prenotazioni} / ${p.num_posti} prenotazioni</p>
                <p><i class="fas fa-user-edit"></i> Creata da: ${escapeHtml(p.creato_da)}</p>
                ${prenotato && prenotato.numero_sedia ? `
                <div class="seat-assigned">
                    <i class="fas fa-chair"></i>
                    <span>La tua sedia: <strong>N. ${prenotato.numero_sedia}</strong></span>
                </div>` : ''}
                ${prenotato && !prenotato.numero_sedia ? `
                <div class="seat-pending">
                    <i class="fas fa-hourglass-half"></i>
                    <span>In attesa di assegnazione posto</span>
                </div>` : ''}
            </div>
            <div class="card-footer">
                ${p.stato === 'aperta' && !prenotato ? `
                    <button class="btn btn-primary btn-sm" onclick="prenota(${p.id})" ${postiRimasti <= 0 ? 'disabled' : ''}>
                        <i class="fas fa-check"></i> ${postiRimasti <= 0 ? 'Posti esauriti' : 'Prenota Presenza'}
                    </button>
                ` : ''}
                ${prenotato ? `
                    <button class="btn btn-danger btn-sm" onclick="cancella(${p.id})">
                        <i class="fas fa-times"></i> Cancella Prenotazione
                    </button>
                    <span class="text-success"><i class="fas fa-check-circle"></i> Prenotato</span>
                ` : ''}
            </div>
        </div>`;
    }).join('');
}

function prenota(partitaId) {
    if (!confirm('Confermi la prenotazione per questa partita?')) return;
    const formData = new FormData();
    formData.append('action', 'prenota');
    formData.append('partita_id', partitaId);

    fetch('/api/prenotazioni.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                loadPartite();
                loadMiePrenotazioni();
            }
        });
}

function cancella(partitaId) {
    if (!confirm('Sei sicuro di voler cancellare la prenotazione?')) return;
    const formData = new FormData();
    formData.append('action', 'cancella');
    formData.append('partita_id', partitaId);

    fetch('/api/prenotazioni.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                loadPartite();
                loadMiePrenotazioni();
            }
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
