document.querySelectorAll('table.data-table, table.soci-table, table.pay-table').forEach(table => {
    if (table.parentElement.classList.contains('table-scroll')) return;
    const wrap = document.createElement('div');
    if (table.querySelectorAll('thead th').length <= 3) table.classList.add('compact-table');
    wrap.className = 'table-scroll';
    wrap.tabIndex = 0;
    wrap.setAttribute('role', 'region');
    wrap.setAttribute('aria-label', 'Tabella dati. Scorri orizzontalmente per tutte le colonne.');
    if (table.style.maxWidth) {
        wrap.style.maxWidth = table.style.maxWidth;
        table.style.maxWidth = '100%';
    }
    table.parentNode.insertBefore(wrap, table);
    wrap.appendChild(table);
});
document.querySelectorAll('table tbody td[colspan]').forEach(cell => {
    if (cell.parentElement.parentElement.rows.length === 1) cell.classList.add('empty-table-state');
});
// Menu utente disponibili anche da mobile, senza dipendere dal menu desktop.
const nav = document.getElementById('admin-navigation');
if (nav) {
    const user = document.querySelector('.topbar-user');
    user?.querySelectorAll('a').forEach(link => {
        const clone = link.cloneNode(true);
        clone.classList.add('mobile-user-link');
        nav.appendChild(clone);
    });
}
window.icbrConfirm = message => new Promise(resolve => {
    const modal = document.createElement('dialog');
    modal.className = 'club-dialog';
    modal.setAttribute('aria-labelledby','club-dialog-title');
    modal.setAttribute('aria-describedby','club-dialog-message');
    modal.innerHTML = '<div class="club-dialog-kicker">INTER CLUB / CONTROLLO</div><h2 id="club-dialog-title">Confermi l’operazione?</h2><p id="club-dialog-message"></p><div class="club-dialog-actions"><button type="button" class="btn btn-secondary" data-cancel autofocus>Annulla</button><button type="button" class="btn" data-accept>Conferma</button></div>';
    modal.querySelector('p').textContent = message;
    modal.querySelector('[data-cancel]').addEventListener('click',()=>modal.close('cancel'));
    modal.querySelector('[data-accept]').addEventListener('click',()=>modal.close('confirm'));
    modal.addEventListener('close',()=>{const accepted=modal.returnValue==='confirm';modal.remove();resolve(accepted);},{once:true});
    document.body.appendChild(modal);
    modal.showModal();
});
document.querySelectorAll('form[data-confirm]').forEach(form => {
    let accepted = false;
    form.addEventListener('submit', async event => {
        if (accepted) { accepted = false; return; }
        event.preventDefault();
        if (await window.icbrConfirm(form.dataset.confirm)) {
            accepted = true;
            form.requestSubmit(event.submitter);
        }
    });
});
document.querySelectorAll('.js-copia').forEach(button => {
    button.addEventListener('click', async () => {
        const text = button.closest('td')?.querySelector('[data-copy]');
        if (!text) return;
        let copied = false;
        try { await navigator.clipboard.writeText(text.value); copied = true; }
        catch (_) {
            text.focus(); text.select();
            try { copied = document.execCommand('copy'); } catch (_) { copied = false; }
        }
        const old = button.textContent;
        button.textContent = copied ? 'Copiato' : 'Seleziona e copia il testo';
        setTimeout(() => { button.textContent = old; }, 2000);
    });
});
