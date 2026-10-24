(function () {
    'use strict';

    const ready = (fn) => {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
        else fn();
    };

    function createToastStack() {
        let stack = document.querySelector('.cam-toast-stack');
        if (stack) return stack;
        stack = document.createElement('div');
        stack.className = 'cam-toast-stack';
        stack.setAttribute('aria-live', 'polite');
        stack.setAttribute('aria-atomic', 'false');
        document.body.appendChild(stack);
        return stack;
    }

    function toast(type, message, options) {
        options = options || {};
        type = ['success', 'warning', 'error', 'info'].includes(type) ? type : 'info';
        const titles = { success: 'Operación completada', warning: 'Atención', error: 'No se pudo completar', info: 'Información' };
        const icons = { success: '✓', warning: '!', error: '×', info: 'i' };
        const item = document.createElement('div');
        item.className = 'cam-toast ' + type;
        item.setAttribute('role', type === 'error' ? 'alert' : 'status');
        item.innerHTML = '<div class="cam-toast-icon" aria-hidden="true"></div><div class="cam-toast-body"><strong></strong><p></p></div><button class="cam-toast-close" type="button" aria-label="Cerrar">×</button>';
        item.querySelector('.cam-toast-icon').textContent = icons[type];
        item.querySelector('strong').textContent = options.title || titles[type];
        item.querySelector('p').textContent = String(message || '');
        const close = () => {
            if (!item.isConnected) return;
            item.classList.add('is-leaving');
            window.setTimeout(() => item.remove(), 190);
        };
        item.querySelector('.cam-toast-close').addEventListener('click', close);
        createToastStack().appendChild(item);
        const persistent = options.persistent || type === 'error';
        if (!persistent) window.setTimeout(close, Number(options.duration || (type === 'warning' ? 6500 : 4300)));
        return item;
    }
    window.camToast = toast;

    function initializeFlashToasts() {
        document.querySelectorAll('[data-cam-toast]').forEach((alert) => {
            const type = alert.dataset.camToast || 'info';
            const text = alert.textContent.trim();
            if (text) toast(type, text, { persistent: type === 'error' });
            alert.remove();
        });
    }

    function cardKey(card, index) {
        return card.dataset.cardKey || card.id || ('card-' + index);
    }

    function initializeCollapsibleCards() {
        document.querySelectorAll('.cam-collapsible').forEach((card, index) => {
            const button = card.querySelector('[data-card-collapse]');
            if (!button || button.dataset.npReady === '1') return;
            button.dataset.npReady = '1';
            const key = 'cam-card-state:' + cardKey(card, index);
            try {
                if (localStorage.getItem(key) === 'collapsed') card.classList.add('is-collapsed');
            } catch (_) {}
            const refresh = () => {
                const collapsed = card.classList.contains('is-collapsed');
                button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                button.setAttribute('aria-label', collapsed ? 'Expandir panel' : 'Contraer panel');
                button.title = collapsed ? 'Expandir panel' : 'Contraer panel';
            };
            refresh();
            button.addEventListener('click', () => {
                card.classList.toggle('is-collapsed');
                try { localStorage.setItem(key, card.classList.contains('is-collapsed') ? 'collapsed' : 'open'); } catch (_) {}
                refresh();
            });
        });
    }

    function initializeStatusBadges(root) {
        (root || document).querySelectorAll('.tag:not([data-status-ready]), .cam-status-badge:not([data-status-ready])').forEach((badge) => {
            badge.dataset.statusReady = '1';
            const text = badge.textContent.trim().toUpperCase();
            const success = ['ACTIVO', 'PROCESSED', 'PROCESADO', 'PROCESADOS', 'POSTED', 'PAGADO', 'PAID', 'CLOSED', 'CERRADO', 'APROBADO', 'SINCRONIZADO', 'OPERATIVA', 'ACTUALIZADO', 'OK'];
            const warning = ['PENDING', 'PENDIENTE', 'PENDIENTES', 'AWAITING_PAYMENT', 'EN COLA', 'REVISAR', 'IN_TRANSIT', 'EN TRÁNSITO'];
            const danger = ['INACTIVO', 'CANCELLED', 'CANCELADO', 'ANULADO', 'ANULADOS', 'ERROR', 'VENCIDO', 'RECHAZADO'];
            const info = ['OPEN', 'ABIERTO', 'EMITIDO', 'NUEVO'];
            if (success.some((value) => text.includes(value))) badge.classList.add('status-success');
            else if (warning.some((value) => text.includes(value))) badge.classList.add('status-warning');
            else if (danger.some((value) => text.includes(value))) badge.classList.add('status-danger');
            else if (info.some((value) => text.includes(value))) badge.classList.add('status-info');
            else badge.classList.add('status-neutral');
        });
    }

    function initializeInputAssistance(root) {
        (root || document).querySelectorAll('input:not([data-cam-input-ready])').forEach((input) => {
            input.dataset.npInputReady = '1';
            const name = (input.name || '').toLowerCase();
            const type = (input.type || '').toLowerCase();
            if (name.includes('phone') || name.includes('telefono') || name.includes('teléfono')) {
                input.inputMode = 'tel';
                input.addEventListener('input', () => {
                    const cursor = input.selectionStart;
                    input.value = input.value.replace(/[^0-9+()\-\s]/g, '').slice(0, 30);
                    if (cursor !== null) input.setSelectionRange(Math.min(cursor, input.value.length), Math.min(cursor, input.value.length));
                });
            }
            if (name === 'tax_id' || name.includes('rif') || name.includes('identification')) {
                input.autocapitalize = 'characters';
                input.spellcheck = false;
                input.addEventListener('blur', () => { input.value = input.value.trim().toLocaleUpperCase('es'); });
            }
            if (type === 'number') input.inputMode = 'decimal';
            if (type === 'date') input.dataset.npDate = '1';
        });
    }

    function initializeSelect2(root) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
        const $ = window.jQuery;
        (root || document).querySelectorAll('select:not([data-select2-ready]):not([data-no-select2])').forEach((select) => {
            if (select.closest('.doc-lines, template')) return;
            if (!select.hasAttribute('data-searchable') && select.options.length < 9) return;
            select.dataset.select2Ready = '1';
            const modal = select.closest('.modal-card, .wa-modal-card');
            const options = {
                width: '100%',
                placeholder: select.getAttribute('placeholder') || undefined,
                allowClear: Boolean(select.querySelector('option[value=""]')),
                language: {
                    noResults: () => 'Sin coincidencias',
                    searching: () => 'Buscando…',
                    inputTooShort: () => 'Escriba más caracteres'
                }
            };
            if (modal) options.dropdownParent = $(modal);
            try { $(select).select2(options); } catch (_) { delete select.dataset.select2Ready; }
        });
    }

    function shouldEnhanceTable(table) {
        if (table.matches('[data-no-datatable], .doc-lines')) return false;
        if (table.closest('.modal, .wa-modal')) return false;
        if (table.hasAttribute('data-datatable')) return true;
        if (!document.body.classList.contains('cam-enable-datatables')) return false;
        return table.tBodies.length > 0 && table.tBodies[0].rows.length >= 8;
    }

    function initializeDataTables(root) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) return;
        const $ = window.jQuery;
        (root || document).querySelectorAll('.table-wrap > table:not([data-datatable-ready]), table[data-datatable]:not([data-datatable-ready])').forEach((table) => {
            if (!shouldEnhanceTable(table)) return;
            table.dataset.datatableReady = '1';
            table.classList.add('cam-datatable-ready');
            try {
                $(table).DataTable({
                    pageLength: Number(table.dataset.pageLength || 25),
                    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                    order: [],
                    autoWidth: false,
                    stateSave: true,
                    language: {
                        search: 'Buscar:',
                        lengthMenu: 'Mostrar _MENU_',
                        info: 'Mostrando _START_–_END_ de _TOTAL_',
                        infoEmpty: 'Sin registros',
                        infoFiltered: '(filtrado de _MAX_)',
                        zeroRecords: 'No se encontraron coincidencias',
                        emptyTable: 'No hay información disponible',
                        paginate: { first: 'Primera', last: 'Última', next: '›', previous: '‹' }
                    }
                });
            } catch (_) {
                delete table.dataset.datatableReady;
                table.classList.remove('cam-datatable-ready');
            }
        });
    }

    function formatLocalDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function initializeDashboardPresets() {
        const form = document.querySelector('[data-dashboard-filter-form]');
        if (!form) return;
        document.querySelectorAll('[data-dashboard-period]').forEach((button) => {
            button.addEventListener('click', () => {
                const now = new Date();
                now.setHours(12, 0, 0, 0);
                let from = new Date(now);
                const mode = button.dataset.dashboardPeriod;
                if (mode === 'month') from = new Date(now.getFullYear(), now.getMonth(), 1, 12);
                else if (mode === 'quarter') from.setDate(from.getDate() - 89);
                else if (mode === 'year') from = new Date(now.getFullYear(), 0, 1, 12);
                form.elements.from.value = formatLocalDate(from);
                form.elements.to.value = formatLocalDate(now);
                form.submit();
            });
        });
    }

    function createLightbox() {
        let box = document.querySelector('.cam-lightbox');
        if (box) return box;
        box = document.createElement('div');
        box.className = 'cam-lightbox';
        box.hidden = true;
        box.innerHTML = '<div class="cam-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Vista ampliada"><button type="button" class="cam-lightbox-close" aria-label="Cerrar">×</button><img alt=""><div class="cam-lightbox-caption"></div></div>';
        document.body.appendChild(box);
        const close = () => { box.hidden = true; box.querySelector('img').src = ''; document.body.style.overflow = ''; };
        box.querySelector('.cam-lightbox-close').addEventListener('click', close);
        box.addEventListener('click', (event) => { if (event.target === box) close(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !box.hidden) close(); });
        box.camClose = close;
        return box;
    }

    function initializeLightbox(root) {
        (root || document).querySelectorAll('[data-lightbox]:not([data-lightbox-ready]), .cam-lightboxable:not([data-lightbox-ready])').forEach((element) => {
            element.dataset.lightboxReady = '1';
            element.style.cursor = 'zoom-in';
            element.addEventListener('click', (event) => {
                event.preventDefault();
                const source = element.tagName === 'IMG' ? element.currentSrc || element.src : element.getAttribute('href') || element.dataset.src;
                if (!source) return;
                const box = createLightbox();
                box.querySelector('img').src = source;
                box.querySelector('img').alt = element.getAttribute('alt') || '';
                box.querySelector('.cam-lightbox-caption').textContent = element.dataset.caption || element.getAttribute('title') || element.getAttribute('alt') || '';
                box.hidden = false;
                document.body.style.overflow = 'hidden';
                box.querySelector('.cam-lightbox-close').focus();
            });
        });
    }


    /**
     * Runtime único de la barra lateral 2CAM.
     *
     * Complementa AdminLTE 4 sin sustituir PushMenu. Admite el marcado
     * canónico de AdminLTE y el contrato data-nav-group-toggle. Desde 1.1.6
     * el estado de cada grupo se conserva explícitamente como true/false, por
     * lo que incluso el grupo que contiene la ruta activa puede permanecer
     * cerrado cuando así lo decidió el usuario.
     */
    function initialize2CamSidebarRuntime() {
        const body = document.body;
        const sidebar = document.querySelector('.app-sidebar[data-cam-sidebar], .app-sidebar[data-np-sidebar], .app-sidebar');
        const menu = sidebar && sidebar.querySelector('.sidebar-menu');
        if (!body || !sidebar || !menu || menu.dataset.camSidebarRuntimeReady === '1') return;
        menu.dataset.camSidebarRuntimeReady = '1';

        const legacyGroupsKey = body.dataset.camSidebarGroupsKey || body.dataset.npSidebarGroupsKey || '2cam:sidebar-open-groups';
        const groupStateKey = body.dataset.camSidebarGroupStateKey || body.dataset.npSidebarGroupStateKey || '2cam:sidebar-group-state:v2';
        const nativeStateKey = 'lte.sidebar.state';
        const transitionDelay = 260;
        let resizeTimer = 0;
        const groupState = Object.create(null);

        const breakpoint = () => Number(sidebar.dataset.sidebarBreakpoint || 991.98);
        const isMobile = () => window.innerWidth <= breakpoint();
        const allGroups = () => Array.from(menu.querySelectorAll('li.nav-item')).filter((item) => Boolean(item.querySelector(':scope > .nav-treeview, :scope > .nav-group-items')));
        const directSubmenu = (item) => item.querySelector(':scope > .nav-treeview, :scope > .nav-group-items');
        const directToggle = (item) => item.querySelector(':scope > [data-nav-group-toggle], :scope > .nav-link');

        function groupKey(item, index) {
            if (item.dataset.camMenuKey) return String(item.dataset.camMenuKey);
            if (item.dataset.navGroup) return String(item.dataset.navGroup);
            if (item.dataset.npMenuKey) return String(item.dataset.npMenuKey);
            const link = directToggle(item);
            const label = (link && link.textContent ? link.textContent : '').replace(/\s+/g, ' ').trim().toLowerCase();
            const depth = item.closest('.cam-level-three,.np-level-three') ? 'l3' : (item.closest('.cam-level-two,.np-level-two,.nav-treeview') ? 'l2' : 'l1');
            return depth + ':' + (label || String(index));
        }

        function loadGroupState() {
            try {
                const stored = JSON.parse(localStorage.getItem(groupStateKey) || '{}');
                if (stored && typeof stored === 'object' && !Array.isArray(stored)) {
                    Object.keys(stored).forEach((code) => {
                        if (typeof stored[code] === 'boolean') groupState[String(code)] = stored[code];
                    });
                }
            } catch (_) {}

            // Migra 1.1.3–1.1.5 y la primera candidata 1.2.0, cuyo formato
            // solo enumeraba grupos abiertos y no podía representar un cierre.
            try {
                const legacy = JSON.parse(localStorage.getItem(legacyGroupsKey) || '[]');
                if (Array.isArray(legacy)) {
                    legacy.map(String).filter(Boolean).forEach((code) => {
                        if (!Object.prototype.hasOwnProperty.call(groupState, code)) groupState[code] = true;
                    });
                }
            } catch (_) {}
        }

        function persistGroupState() {
            try {
                localStorage.setItem(groupStateKey, JSON.stringify(groupState));
                localStorage.removeItem(legacyGroupsKey);
            } catch (_) {}
        }

        function setGroupOpen(item, open, remember) {
            const submenu = directSubmenu(item);
            const toggle = directToggle(item);
            const index = allGroups().indexOf(item);
            const code = groupKey(item, index < 0 ? 0 : index);
            const isOpen = Boolean(open);
            item.classList.toggle('menu-open', isOpen);
            if (toggle) {
                // No usar la clase .active como sinónimo de grupo abierto: esa
                // clase identifica la ruta actual y se usa para llevarla al área visible.
                toggle.setAttribute('aria-expanded', String(isOpen));
            }
            if (submenu) {
                submenu.hidden = !isOpen;
                submenu.setAttribute('aria-hidden', String(!isOpen));
                // AdminLTE puede dejar display:block en línea en rutas activas.
                // La declaración important garantiza que un cierre explícito gane.
                submenu.style.setProperty('display', isOpen ? 'block' : 'none', 'important');
            }

            if (remember && code) {
                groupState[code] = isOpen;
                persistGroupState();
            }
        }

        function syncGroupVisualState() {
            allGroups().forEach((item) => setGroupOpen(item, item.classList.contains('menu-open'), false));
        }

        function syncAria() {
            const collapsed = body.classList.contains('sidebar-collapse');
            const mobileOpen = body.classList.contains('sidebar-open');
            if (!sidebar.id) sidebar.id = 'cam-main-sidebar';
            document.querySelectorAll('[data-lte-toggle="sidebar"], [data-cam-sidebar-toggle], [data-np-sidebar-toggle]').forEach((toggle) => {
                toggle.setAttribute('aria-expanded', String(isMobile() ? mobileOpen : !collapsed));
                toggle.setAttribute('aria-controls', toggle.getAttribute('aria-controls') || sidebar.id);
            });
            syncGroupVisualState();
        }

        function remeasureLayout(source) {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
                const detail = {
                    source: source || 'unknown',
                    collapsed: body.classList.contains('sidebar-collapse'),
                    mobileOpen: body.classList.contains('sidebar-open')
                };
                document.dispatchEvent(new CustomEvent('2cam:sidebar:changed', { detail }));
                document.dispatchEvent(new CustomEvent('np:sidebar:changed', { detail }));
                keepActiveEntryVisible();
            }, transitionDelay);
        }

        function keepActiveEntryVisible() {
            const active = menu.querySelector('.nav-link.active');
            if (!active || !sidebar.contains(active) || active.closest('[hidden]')) return;
            window.requestAnimationFrame(() => {
                try { active.scrollIntoView({ block: 'nearest', inline: 'nearest' }); } catch (_) {}
            });
        }

        function restoreGroups() {
            loadGroupState();
            const groups = allGroups();
            groups.forEach((item, index) => {
                const code = groupKey(item, index);
                const submenu = directSubmenu(item);
                const hasActive = Boolean(submenu && submenu.querySelector('.nav-link.active'));
                const hasExplicitState = code && Object.prototype.hasOwnProperty.call(groupState, code);
                const initialOpen = hasExplicitState
                    ? groupState[code]
                    : (hasActive || item.classList.contains('menu-open'));
                setGroupOpen(item, initialOpen, false);
            });

            // Una ruta activa abre sus ancestros solamente cuando el usuario no
            // guardó una decisión explícita de cerrarlos.
            menu.querySelectorAll('.nav-link.active').forEach((active) => {
                let parent = active.closest('li.nav-item');
                while (parent && menu.contains(parent)) {
                    if (directSubmenu(parent)) {
                        const index = groups.indexOf(parent);
                        const code = groupKey(parent, index < 0 ? 0 : index);
                        if (!Object.prototype.hasOwnProperty.call(groupState, code) || groupState[code] === true) {
                            setGroupOpen(parent, true, false);
                        }
                    }
                    parent = parent.parentElement ? parent.parentElement.closest('li.nav-item') : null;
                }
            });
            persistGroupState();
            syncAria();
        }

        function persistCollapsedState() {
            try {
                localStorage.setItem(nativeStateKey, body.classList.contains('sidebar-collapse') ? 'sidebar-collapse' : 'sidebar-open');
                localStorage.removeItem('2cam:sidebar-collapsed');
            } catch (_) {}
        }

        // Migra el almacenamiento usado por 1.1.x si la clave nativa aún no existe.
        try {
            const legacy = localStorage.getItem('2cam:sidebar-collapsed');
            if (localStorage.getItem(nativeStateKey) === null && legacy !== null) {
                localStorage.setItem(nativeStateKey, legacy === '1' ? 'sidebar-collapse' : 'sidebar-open');
            }
        } catch (_) {}

        menu.addEventListener('click', (event) => {
            const customToggle = event.target.closest('[data-nav-group-toggle]');
            if (customToggle && menu.contains(customToggle)) {
                const group = customToggle.closest('li.nav-item');
                if (group && directSubmenu(group)) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    setGroupOpen(group, !group.classList.contains('menu-open'), true);
                    remeasureLayout('group-toggle');
                    return;
                }
            }

            const link = event.target.closest('li.nav-item > .nav-link');
            const group = link && link.parentElement;
            if (group && directSubmenu(group)) {
                // Compatibilidad con marcado AdminLTE sin data-nav-group-toggle:
                // se deja actuar al Treeview y luego se captura su resultado.
                window.setTimeout(() => {
                    setGroupOpen(group, group.classList.contains('menu-open'), true);
                    remeasureLayout('native-group-toggle');
                }, 60);
                return;
            }

            const destination = event.target.closest('a.nav-link[href]');
            if (destination && isMobile() && destination.getAttribute('href') !== '#') {
                body.classList.remove('sidebar-open');
                body.classList.add('sidebar-collapse');
                persistCollapsedState();
                syncAria();
                remeasureLayout('mobile-destination');
            }
        });

        const onPushMenuChanged = (event) => {
            persistCollapsedState();
            syncAria();
            remeasureLayout(event.type || 'push-menu');
        };
        ['opened.lte.push-menu', 'collapsed.lte.push-menu'].forEach((name) => {
            sidebar.addEventListener(name, onPushMenuChanged);
            document.addEventListener(name, onPushMenuChanged);
        });

        document.querySelectorAll('[data-lte-toggle="sidebar"], [data-cam-sidebar-toggle], [data-np-sidebar-toggle]').forEach((toggle) => {
            toggle.addEventListener('click', () => window.setTimeout(() => {
                persistCollapsedState();
                syncAria();
                remeasureLayout('toggle');
            }, 80));
        });
        window.addEventListener('resize', syncAria, { passive: true });

        restoreGroups();
        keepActiveEntryVisible();
        remeasureLayout('restore');
    }

    function enhance(root) {
        initializeStatusBadges(root);
        initializeInputAssistance(root);
        initializeSelect2(root);
        initializeDataTables(root);
        initializeLightbox(root);
        initializeCollapsibleCards();
    }

    ready(() => {
        initializeFlashToasts();
        initialize2CamSidebarRuntime();
        initializeDashboardPresets();
        enhance(document);

        // Los modales y filas dinámicas del sistema pueden aparecer después de cargar.
        const observer = new MutationObserver((mutations) => {
            let needsEnhancement = false;
            for (const mutation of mutations) {
                if (mutation.addedNodes && mutation.addedNodes.length) { needsEnhancement = true; break; }
            }
            if (needsEnhancement) window.requestAnimationFrame(() => enhance(document));
        });
        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
