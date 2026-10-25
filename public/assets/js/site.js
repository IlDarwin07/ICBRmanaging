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
let automaticContactMessage = '';
document.querySelectorAll('[data-topic]').forEach(link => {
    link.addEventListener('click', () => {
        const topic = document.getElementById('contact-topic');
        const message = document.getElementById('contact-message');
        if (topic) topic.value = link.dataset.topic;
        if (message && (!message.value.trim() || message.value === automaticContactMessage)) {
            automaticContactMessage = link.dataset.message || '';
            message.value = automaticContactMessage;
        }
    });
});

function openLinkedFaq(hash) {
    if (!hash || !hash.startsWith('#faq-')) return;
    let id;
    try { id = decodeURIComponent(hash.slice(1)); } catch { return; }
    const target = document.getElementById(id);
    if (target instanceof HTMLDetailsElement) target.open = true;
}
document.querySelectorAll('[data-faq-link]').forEach(link => {
    link.addEventListener('click', () => openLinkedFaq(link.hash));
});
window.addEventListener('hashchange', () => openLinkedFaq(window.location.hash));
openLinkedFaq(window.location.hash);

const flipCards = [...document.querySelectorAll('.flip-card')];
let membershipFrontHeight = 350;
function membershipFaceHeight(face) {
    face.style.bottom = 'auto';
    const height = Math.max(350, face.offsetHeight);
    face.style.bottom = '';
    return height;
}
function sizeMembershipCard(card) {
    const face = card.querySelector(card.classList.contains('is-flipped') ? '.membership-back' : '.membership-front');
    // Misura l'altezza naturale, anche per testi lunghi del CMS e font ingranditi.
    const height = Math.max(membershipFrontHeight, membershipFaceHeight(face));
    card.querySelector('.membership-flipper').style.height = `${height}px`;
}
function sizeAllMembershipCards() {
    membershipFrontHeight = Math.max(350, ...flipCards.map(card => membershipFaceHeight(card.querySelector('.membership-front'))));
    flipCards.forEach(sizeMembershipCard);
}
if (flipCards.length) {
    document.documentElement.classList.add('has-flip-cards');
    flipCards.forEach(card => {
        const front = card.querySelector('.membership-front');
        const back = card.querySelector('.membership-back');
        const open = card.querySelector('[data-flip-open]');
        const close = card.querySelector('[data-flip-close]');
        const setFlipped = flipped => {
            card.classList.toggle('is-flipped', flipped);
            front.inert = flipped;
            back.inert = !flipped;
            front.setAttribute('aria-hidden', String(flipped));
            back.setAttribute('aria-hidden', String(!flipped));
            open.setAttribute('aria-expanded', String(flipped));
            sizeMembershipCard(card);
            (flipped ? close : open).focus({preventScroll:true});
        };
        back.inert = true;
        back.setAttribute('aria-hidden', 'true');
        open.addEventListener('click', () => setFlipped(true));
        close.addEventListener('click', () => setFlipped(false));
        card.addEventListener('keydown', event => {
            if (event.key === 'Escape' && card.classList.contains('is-flipped')) {
                event.preventDefault();
                setFlipped(false);
            }
        });
    });
    sizeAllMembershipCards();
    let resizeFrame;
    window.addEventListener('resize', () => {
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(sizeAllMembershipCards);
    });
    if (document.fonts) document.fonts.ready.then(sizeAllMembershipCards);
}
