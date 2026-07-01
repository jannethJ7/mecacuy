(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function toast(message, type = 'success') {
        let wrap = document.querySelector('.mc-pro-toast-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'mc-pro-toast-wrap';
            wrap.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:99999;display:flex;flex-direction:column;gap:10px;max-width:min(420px,calc(100vw - 36px));';
            document.body.appendChild(wrap);
        }

        const item = document.createElement('div');
        item.className = 'mc-pro-toast';
        item.style.cssText = `
            padding:14px 16px;border-radius:16px;color:#fff;font-weight:800;
            box-shadow:0 18px 45px rgba(0,0,0,.28);
            border:1px solid ${type === 'danger' ? 'rgba(239,68,68,.34)' : 'rgba(34,197,94,.34)'};
            background:${type === 'danger' ? 'rgba(127,29,29,.94)' : 'rgba(22,101,52,.94)'};
        `;
        item.textContent = message;
        wrap.appendChild(item);

        setTimeout(() => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(8px)';
            item.style.transition = '.25s ease';
            setTimeout(() => item.remove(), 260);
        }, 3000);
    }

    function normalize(text) {
        return (text || '').toString().toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function applySearch(input) {
        const target = document.querySelector(input.dataset.mcSearch);
        if (!target) return;

        const q = normalize(input.value);
        target.querySelectorAll('[data-search-row]').forEach(row => {
            const hay = normalize(row.dataset.search || row.textContent);
            row.classList.toggle('is-hidden', q && !hay.includes(q));
        });
    }

    function applyFilter(btn) {
        const target = document.querySelector(btn.dataset.mcFilter);
        if (!target) return;

        const group = btn.closest('.mc-pro-toolbar-actions') || btn.parentElement;
        group?.querySelectorAll('[data-mc-filter]').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');

        const value = normalize(btn.dataset.filterValue || 'all');
        target.querySelectorAll('[data-search-row]').forEach(row => {
            const rowValue = normalize(row.dataset.filter || '');
            row.classList.toggle('is-hidden', value !== 'all' && !rowValue.includes(value));
        });
    }

    function drawLineChart(canvas) {
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        let labels = [];
        let values = [];

        try {
            labels = JSON.parse(canvas.dataset.labels || '[]');
            values = JSON.parse(canvas.dataset.values || '[]').map(Number).filter(v => !Number.isNaN(v));
        } catch (e) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const width = Math.max(320, rect.width);
        const height = Number(canvas.getAttribute('height')) || 220;

        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.scale(dpr, dpr);
        ctx.clearRect(0, 0, width, height);

        const pad = { left: 44, right: 18, top: 22, bottom: 34 };
        const plotW = width - pad.left - pad.right;
        const plotH = height - pad.top - pad.bottom;

        ctx.strokeStyle = 'rgba(148,163,184,.22)';
        ctx.lineWidth = 1;

        for (let i = 0; i <= 4; i++) {
            const y = pad.top + (plotH / 4) * i;
            ctx.beginPath();
            ctx.moveTo(pad.left, y);
            ctx.lineTo(width - pad.right, y);
            ctx.stroke();
        }

        if (!values.length) {
            ctx.fillStyle = 'rgba(203,213,225,.75)';
            ctx.font = '700 14px system-ui';
            ctx.fillText('Sin datos para graficar', pad.left, height / 2);
            return;
        }

        const min = Math.min(...values);
        const max = Math.max(...values);
        const span = max - min || 1;

        const points = values.map((v, i) => {
            const x = pad.left + (plotW * (values.length === 1 ? 0 : i / (values.length - 1)));
            const y = pad.top + plotH - ((v - min) / span) * plotH;
            return { x, y, v };
        });

        const gradient = ctx.createLinearGradient(0, pad.top, 0, height - pad.bottom);
        gradient.addColorStop(0, 'rgba(201,168,106,.26)');
        gradient.addColorStop(1, 'rgba(201,168,106,0)');

        ctx.beginPath();
        points.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
        ctx.lineTo(points[points.length - 1].x, height - pad.bottom);
        ctx.lineTo(points[0].x, height - pad.bottom);
        ctx.closePath();
        ctx.fillStyle = gradient;
        ctx.fill();

        ctx.beginPath();
        points.forEach((p, i) => i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y));
        ctx.strokeStyle = '#c9a86a';
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.stroke();

        ctx.fillStyle = '#cbd5e1';
        ctx.font = '700 11px system-ui';
        ctx.fillText(max.toFixed(2), 8, pad.top + 4);
        ctx.fillText(min.toFixed(2), 8, height - pad.bottom + 4);

        if (labels.length) {
            ctx.fillStyle = 'rgba(203,213,225,.75)';
            ctx.font = '700 10px system-ui';
            ctx.fillText(labels[0] || '', pad.left, height - 10);
            ctx.textAlign = 'right';
            ctx.fillText(labels[labels.length - 1] || '', width - pad.right, height - 10);
            ctx.textAlign = 'left';
        }
    }


    function setFancySelectOpenState(wrapper, isOpen) {
        const field = wrapper.closest('.mc-pro-field');
        const card = wrapper.closest('.mc-pro-card, .mc-pro-form-card');
        const button = wrapper.querySelector('.mc-pro-select-button');
        const menu = wrapper.querySelector('.mc-pro-select-menu');

        wrapper.classList.toggle('is-open', isOpen);
        wrapper.classList.remove('is-dropup');
        field?.classList.toggle('mc-pro-field-select-open', isOpen);
        card?.classList.toggle('mc-pro-card-select-open', isOpen);
        button?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (!isOpen || !menu || !button) return;

        /*
         * No agrandamos el card ni empujamos los demás campos.
         * Si no hay espacio suficiente hacia abajo, el menú se abre hacia arriba.
         */
        requestAnimationFrame(() => {
            const buttonRect = button.getBoundingClientRect();
            const cardRect = card?.getBoundingClientRect();
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
            const maxHeight = 285;
            const menuHeight = Math.min(menu.scrollHeight || maxHeight, maxHeight);
            const safeGap = 18;

            const spaceBelowViewport = viewportHeight - buttonRect.bottom - safeGap;
            const spaceAboveViewport = buttonRect.top - safeGap;
            const spaceBelowCard = cardRect ? cardRect.bottom - buttonRect.bottom - safeGap : spaceBelowViewport;
            const spaceAboveCard = cardRect ? buttonRect.top - cardRect.top - safeGap : spaceAboveViewport;

            const availableBelow = Math.min(spaceBelowViewport, Math.max(spaceBelowCard, 0));
            const availableAbove = Math.min(spaceAboveViewport, Math.max(spaceAboveCard, 0));
            const forcedPlacement = wrapper.dataset.placement || 'auto';
            const shouldDropUp = forcedPlacement === 'up'
                ? true
                : forcedPlacement === 'down'
                    ? false
                    : (availableBelow < menuHeight && availableAbove > availableBelow);

            wrapper.classList.toggle('is-dropup', shouldDropUp);
        });
    }

    function closeFancySelects(except = null) {
        document.querySelectorAll('.mc-pro-select.is-open').forEach(select => {
            if (select !== except) {
                setFancySelectOpenState(select, false);
            }
        });
    }

    function refreshFancySelect(wrapper, nativeSelect) {
        const selected = nativeSelect.options[nativeSelect.selectedIndex];
        const value = wrapper.querySelector('.mc-pro-select-value');

        if (value) {
            value.textContent = selected?.textContent?.trim() || nativeSelect.getAttribute('placeholder') || 'Seleccionar';
        }

        wrapper.querySelectorAll('.mc-pro-select-option').forEach(option => {
            const isSelected = option.dataset.value === nativeSelect.value;
            option.classList.toggle('is-selected', isSelected);
            option.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        });
    }

    function initFancySelects(root = document) {
        root.querySelectorAll('.mc-pro-field select:not([multiple]):not([data-mc-native-select])').forEach(nativeSelect => {
            if (nativeSelect.dataset.mcFancySelect === 'ready') return;

            nativeSelect.dataset.mcFancySelect = 'ready';
            nativeSelect.classList.add('mc-pro-select-hidden');

            const wrapper = document.createElement('div');
            wrapper.className = 'mc-pro-select';
            wrapper.dataset.placement = nativeSelect.dataset.mcSelectPlacement || 'auto';
            if (nativeSelect.disabled) wrapper.classList.add('is-disabled');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'mc-pro-select-button';
            button.setAttribute('aria-haspopup', 'listbox');
            button.setAttribute('aria-expanded', 'false');

            const value = document.createElement('span');
            value.className = 'mc-pro-select-value';

            const arrow = document.createElement('span');
            arrow.className = 'mc-pro-select-arrow';
            arrow.setAttribute('aria-hidden', 'true');

            button.append(value, arrow);

            const menu = document.createElement('div');
            menu.className = 'mc-pro-select-menu';
            menu.setAttribute('role', 'listbox');

            Array.from(nativeSelect.options).forEach(option => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'mc-pro-select-option';
                item.dataset.value = option.value;
                item.textContent = option.textContent;
                item.disabled = option.disabled;
                item.setAttribute('role', 'option');

                item.addEventListener('click', () => {
                    if (item.disabled) return;
                    nativeSelect.value = option.value;
                    nativeSelect.dispatchEvent(new Event('input', { bubbles: true }));
                    nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    refreshFancySelect(wrapper, nativeSelect);
                    closeFancySelects();
                    button.focus();
                });

                menu.appendChild(item);
            });

            button.addEventListener('click', () => {
                if (nativeSelect.disabled) return;
                const willOpen = !wrapper.classList.contains('is-open');
                closeFancySelects(wrapper);
                setFancySelectOpenState(wrapper, willOpen);
            });

            button.addEventListener('keydown', e => {
                if (['ArrowDown', 'Enter', ' '].includes(e.key)) {
                    e.preventDefault();
                    closeFancySelects(wrapper);
                    setFancySelectOpenState(wrapper, true);
                    const selected = wrapper.querySelector('.mc-pro-select-option.is-selected:not(:disabled)') || wrapper.querySelector('.mc-pro-select-option:not(:disabled)');
                    selected?.focus();
                }

                if (e.key === 'Escape') {
                    closeFancySelects();
                }
            });

            menu.addEventListener('keydown', e => {
                const options = Array.from(menu.querySelectorAll('.mc-pro-select-option:not(:disabled)'));
                const current = document.activeElement;
                const index = Math.max(0, options.indexOf(current));

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    options[Math.min(options.length - 1, index + 1)]?.focus();
                }

                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    options[Math.max(0, index - 1)]?.focus();
                }

                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeFancySelects();
                    button.focus();
                }
            });

            nativeSelect.addEventListener('change', () => refreshFancySelect(wrapper, nativeSelect));

            nativeSelect.after(wrapper);
            wrapper.append(button, menu);
            refreshFancySelect(wrapper, nativeSelect);
        });
    }

    function clampNumberInput(input) {
        const value = Number(input.value);
        if (Number.isNaN(value)) return;

        const min = input.min !== '' ? Number(input.min) : null;
        const max = input.max !== '' ? Number(input.max) : null;

        if (min !== null && value < min) input.value = min;
        if (max !== null && value > max) input.value = max;
    }

    function initNumberSteppers(root = document) {
        root.querySelectorAll('.mc-pro-field input[type="number"]:not([data-mc-native-number])').forEach(input => {
            if (input.dataset.mcNumberStepper === 'ready') return;

            input.dataset.mcNumberStepper = 'ready';

            const wrapper = document.createElement('div');
            wrapper.className = 'mc-pro-number';

            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            const actions = document.createElement('div');
            actions.className = 'mc-pro-number-actions';

            const up = document.createElement('button');
            up.type = 'button';
            up.className = 'mc-pro-number-step';
            up.setAttribute('aria-label', 'Subir valor');
            up.textContent = '▲';

            const down = document.createElement('button');
            down.type = 'button';
            down.className = 'mc-pro-number-step';
            down.setAttribute('aria-label', 'Bajar valor');
            down.textContent = '▼';

            actions.append(up, down);
            wrapper.appendChild(actions);

            function step(direction) {
                if (input.disabled || input.readOnly) return;
                direction > 0 ? input.stepUp() : input.stepDown();
                clampNumberInput(input);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.focus();
            }

            up.addEventListener('click', () => step(1));
            down.addEventListener('click', () => step(-1));
            input.addEventListener('blur', () => clampNumberInput(input));
        });
    }

    document.addEventListener('input', function (e) {
        if (e.target.matches('[data-mc-search]')) applySearch(e.target);
    });

    document.addEventListener('click', async function (e) {
        if (!e.target.closest('.mc-pro-select')) {
            closeFancySelects();
        }

        const dismiss = e.target.closest('[data-mc-dismiss]');
        if (dismiss) {
            dismiss.closest('.mc-pro-flash')?.remove();
            return;
        }

        const filterBtn = e.target.closest('[data-mc-filter]');
        if (filterBtn) {
            applyFilter(filterBtn);
            return;
        }

        const form = e.target.closest('form[data-mc-confirm]');
        if (form && e.target.closest('button, a, input[type="submit"]')) {
            if (!confirm(form.dataset.mcConfirm || '¿Confirmas esta acción?')) {
                e.preventDefault();
            }
            return;
        }

        const sw = e.target.closest('[data-mc-switch]');
        if (sw) {
            const url = sw.dataset.url;
            const state = sw.dataset.state;

            if (!url) {
                toast('Falta configurar la ruta de control manual.', 'danger');
                return;
            }

            sw.disabled = true;

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ estado: Number(state), on: Number(state) === 1 })
                });

                if (!res.ok) throw new Error('No se pudo cambiar el estado');

                const card = sw.closest('.mc-pro-actuator-card');
                const wrap = sw.closest('.mc-pro-toggle-wrap');

                wrap?.querySelectorAll('[data-mc-switch]').forEach(b => b.classList.remove('is-active'));
                sw.classList.add('is-active');

                card?.classList.toggle('is-on', Number(state) === 1);
                card?.classList.toggle('is-off', Number(state) !== 1);

                const label = card?.querySelector('[data-state-label]');
                if (label) {
                    label.textContent = Number(state) === 1 ? 'ON' : 'OFF';
                    label.classList.toggle('is-success', Number(state) === 1);
                    label.classList.toggle('is-muted', Number(state) !== 1);
                }

                toast(Number(state) === 1 ? 'Actuador encendido.' : 'Actuador apagado.');
            } catch (err) {
                toast(err.message || 'Error de control manual.', 'danger');
            } finally {
                sw.disabled = false;
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-mc-autohide]').forEach(el => {
            setTimeout(() => el.remove(), 4500);
        });

        document.querySelectorAll('[data-mc-line-chart]').forEach(drawLineChart);
        initFancySelects();
        initNumberSteppers();
    });

    window.addEventListener('resize', function () {
        document.querySelectorAll('[data-mc-line-chart]').forEach(drawLineChart);
    });
})();
