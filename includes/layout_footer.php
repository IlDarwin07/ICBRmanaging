    </main>
    <footer class="site-footer">
        <p>Gestionale Inter Club Brindisi &mdash; uso interno riservato</p>
    </footer>
    <script>
    // Dropdown navbar
    document.querySelectorAll('.nav-dropdown-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const open = this.getAttribute('aria-expanded') === 'true';
            document.querySelectorAll('.nav-dropdown-toggle').forEach(b => {
                b.setAttribute('aria-expanded', 'false');
                b.closest('.nav-dropdown').classList.remove('open');
            });
            if (!open) {
                this.setAttribute('aria-expanded', 'true');
                this.closest('.nav-dropdown').classList.add('open');
            }
        });
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.nav-dropdown-toggle').forEach(b => {
                b.setAttribute('aria-expanded', 'false');
                b.closest('.nav-dropdown').classList.remove('open');
            });
        }
    });
    </script>
</body>
</html>
