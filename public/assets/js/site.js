document.querySelectorAll('.menu-toggle').forEach(button => {
    const menu = document.getElementById(button.getAttribute('aria-controls'));
    if (!menu) return;
    const close = () => { button.setAttribute('aria-expanded', 'false'); menu.classList.remove('is-open'); };
    button.addEventListener('click', () => {
        const open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(open));
        menu.classList.toggle('is-open', open);
    });
    menu.querySelectorAll('a').forEach(a => a.addEventListener('click', close));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { close(); button.focus(); } });
    window.matchMedia('(min-width: 1051px)').addEventListener('change', close);
});
document.querySelectorAll('[data-topic]').forEach(link => {
    link.addEventListener('click', () => {
        const topic = document.getElementById('contact-topic');
        const message = document.getElementById('contact-message');
        if (topic) topic.value = link.dataset.topic;
        if (message && !message.value.trim()) message.value = link.dataset.message || '';
    });
});
