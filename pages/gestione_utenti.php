<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);
$pageTitle = 'Gestione Utenti';
include __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <h2><i class="fas fa-users-cog"></i> Gestione Utenti</h2>

    <div id="users-list" class="table-container">
        <p class="loading"><i class="fas fa-spinner fa-spin"></i> Caricamento utenti...</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadUsers);

function loadUsers() {
    fetch('/api/utenti.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderUsers(data.users);
            }
        });
}

function renderUsers(users) {
    const container = document.getElementById('users-list');
    container.innerHTML = `
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Nome</th>
                <th>Email</th>
                <th>Ruolo</th>
                <th>Stato</th>
                <th>Registrato</th>
                <th>Azioni</th>
            </tr>
        </thead>
        <tbody>
            ${users.map(u => `
            <tr class="${u.attivo ? '' : 'row-disabled'}">
                <td>${u.id}</td>
                <td>${escapeHtml(u.username)}</td>
                <td>${escapeHtml(u.cognome)} ${escapeHtml(u.nome)}</td>
                <td>${escapeHtml(u.email)}</td>
                <td>
                    <select onchange="changeRole(${u.id}, this.value)" class="select-sm" ${u.id == 1 ? 'disabled' : ''}>
                        <option value="socio" ${u.ruolo === 'socio' ? 'selected' : ''}>Socio</option>
                        <option value="collaboratore" ${u.ruolo === 'collaboratore' ? 'selected' : ''}>Collaboratore</option>
                        <option value="admin" ${u.ruolo === 'admin' ? 'selected' : ''}>Admin</option>
                    </select>
                </td>
                <td>
                    <span class="badge ${u.attivo ? 'badge-success' : 'badge-danger'}">
                        ${u.attivo ? 'Attivo' : 'Disattivo'}
                    </span>
                </td>
                <td>${new Date(u.created_at).toLocaleDateString('it-IT')}</td>
                <td class="actions">
                    <button class="btn btn-warning btn-sm" onclick="toggleActive(${u.id})">
                        <i class="fas fa-${u.attivo ? 'ban' : 'check'}"></i>
                        ${u.attivo ? 'Disattiva' : 'Attiva'}
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            `).join('')}
        </tbody>
    </table>`;
}

function changeRole(userId, ruolo) {
    const formData = new FormData();
    formData.append('action', 'change_role');
    formData.append('user_id', userId);
    formData.append('ruolo', ruolo);

    fetch('/api/utenti.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (!data.success) loadUsers();
        });
}

function toggleActive(userId) {
    const formData = new FormData();
    formData.append('action', 'toggle_active');
    formData.append('user_id', userId);

    fetch('/api/utenti.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            loadUsers();
        });
}

function deleteUser(userId) {
    if (!confirm('Sei sicuro di voler eliminare questo utente? Tutte le sue prenotazioni saranno eliminate.')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('user_id', userId);

    fetch('/api/utenti.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) loadUsers();
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
