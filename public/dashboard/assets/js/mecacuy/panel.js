document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;

    const sidebarToggle = document.getElementById('mcSidebarToggle');
    const sidebarClose = document.getElementById('mcSidebarClose');
    const sidebarOverlay = document.getElementById('mcSidebarOverlay');

    function closeSidebar() {
        body.classList.remove('mc-sidebar-open');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            body.classList.toggle('mc-sidebar-open');
        });
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    const dropdowns = document.querySelectorAll('[data-dropdown]');

    dropdowns.forEach((dropdown) => {
        const button = dropdown.querySelector('[data-dropdown-button]');

        if (!button) return;

        button.addEventListener('click', (event) => {
            event.stopPropagation();

            dropdowns.forEach((otherDropdown) => {
                if (otherDropdown !== dropdown) {
                    otherDropdown.classList.remove('open');
                }
            });

            dropdown.classList.toggle('open');
        });
    });

    document.addEventListener('click', () => {
        dropdowns.forEach((dropdown) => {
            dropdown.classList.remove('open');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();

            dropdowns.forEach((dropdown) => {
                dropdown.classList.remove('open');
            });
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1120) {
            closeSidebar();
        }
    });
});