function previewFeedback(anchor, message) {
    let feedback = anchor.parentElement.querySelector('.preview-feedback');
    if (!feedback) {
        feedback = document.createElement('p');
        feedback.className = 'preview-feedback';
        feedback.setAttribute('role', 'status');
        anchor.insertAdjacentElement('afterend', feedback);
    }
    feedback.textContent = message;
    feedback.scrollIntoView({behavior:'smooth', block:'nearest'});
}
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
        event.preventDefault();
        event.stopImmediatePropagation();
        previewFeedback(form, 'Questa anteprima è in sola lettura: nessun dato viene salvato e nessun messaggio viene inviato. Le funzioni operative sono disponibili nel progetto PHP su XAMPP.');
    }, true);
});
// Intercetta solo i link operativi: i clic su campi e checkbox devono funzionare.
document.querySelectorAll('a[data-preview-only], a[href^="https://wa.me/"], a[href^="mailto:"]').forEach(link => {
    link.addEventListener('click', event => {
        event.preventDefault();
        previewFeedback(link, 'Azione operativa disattivata nell’anteprima. Non vengono contattati destinatari dimostrativi.');
    });
});
