(() => {
    const root = document.documentElement;
    const key = 'mecacuy-panel-theme';
    let saved;
    try { saved = localStorage.getItem(key); } catch (_) { /* Navegación privada. */ }
    root.dataset.mcTheme = saved === 'dark' ? 'dark' : 'light';

    function syncButton() {
        const button = document.getElementById('mcThemeToggle');
        if (!button) return;
        const dark = root.dataset.mcTheme === 'dark';
        const label = dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.setAttribute('aria-pressed', String(dark));
        button.querySelector('i').className = dark ? 'ri-sun-line' : 'ri-moon-line';
        button.querySelector('span').textContent = dark ? 'Modo claro' : 'Modo oscuro';
    }

    document.addEventListener('DOMContentLoaded', () => {
        syncButton();
        document.getElementById('mcThemeToggle')?.addEventListener('click', () => {
            root.dataset.mcTheme = root.dataset.mcTheme === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem(key, root.dataset.mcTheme); } catch (_) { /* El cambio sigue funcionando. */ }
            syncButton();
            window.dispatchEvent(new Event('mc-theme-change'));
        });
    });

    window.addEventListener('storage', event => {
        if (event.key !== key) return;
        root.dataset.mcTheme = event.newValue === 'dark' ? 'dark' : 'light';
        syncButton();
        window.dispatchEvent(new Event('mc-theme-change'));
    });
})();
