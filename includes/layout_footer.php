</main>
<footer class="app-footer">
    <small>Gestionale Inter Club Brindisi &mdash; uso interno riservato</small>
</footer>
<script>
(function () {
    var dropdowns = document.querySelectorAll('.nav-dropdown');
    dropdowns.forEach(function (dd) {
        var toggle = dd.querySelector('.nav-dropdown-toggle');
        if (!toggle) return;
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            dd.classList.toggle('open');
            toggle.setAttribute('aria-expanded', dd.classList.contains('open'));
        });
    });
    document.addEventListener('click', function () {
        dropdowns.forEach(function (dd) {
            dd.classList.remove('open');
            var t = dd.querySelector('.nav-dropdown-toggle');
            if (t) t.setAttribute('aria-expanded', 'false');
        });
    });
})();
</script>
</body>
</html>
