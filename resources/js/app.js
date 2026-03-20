import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// TomSelect is loaded via a synchronous CDN <script> in <head>, so it is
// always available before this module runs (Vite modules are deferred).
/** @type {typeof import('tom-select').default | undefined} */
const TomSelect = /** @type {any} */ (window).TomSelect;

// Register Alpine directive for TomSelect BEFORE Alpine.start()
Alpine.directive('tom-select', (el, { expression }, { evaluateLater, effect }) => {
    if (!TomSelect) return;

    const getRemoteValue = evaluateLater(expression);

    const isTouchDevice = (() => {
        return (('ontouchstart' in window) || (navigator.maxTouchPoints && navigator.maxTouchPoints > 0));
    })();

    const instance = new TomSelect(el, {
        plugins: ['remove_button'],
        create: false,
        persist: false,
        placeholder: el.getAttribute('placeholder') || '',
        openOnFocus: !isTouchDevice
    });

    // When TomSelect value changes, update Alpine data
    instance.on('change', value => {
        const root = Alpine.$data(el);
        const path = expression.split('.');
        const last = path.pop();
        let target = root;

        // Navigate to nested object
        for (const p of path) {
            if (target[p] === undefined) target[p] = {};
            target = target[p];
        }

        // Set the value as array of strings
        target[last] = Array.isArray(value) ? value.map(String) : (value ? [String(value)] : []);
    });

    // When Alpine data changes, update TomSelect
    effect(() => {
        getRemoteValue(value => {
            const vals = Array.isArray(value) ? value.map(String) : (value ? [String(value)] : []);
            instance.setValue(vals, true);
        });
    });
});

Alpine.start();

/**
 * Class TagSelectorInitializer
 * Initializes TomSelect instances for tag selection.
 */
class TagSelectorInitializer {
    static init(selector) {
        if (!TomSelect) return;

        const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!el) return;

        const isTouchDevice = (('ontouchstart' in window) || (navigator.maxTouchPoints && navigator.maxTouchPoints > 0));

        new TomSelect(el, {
            plugins: ['remove_button'],
            create: false,
            persist: false,
            openOnFocus: !isTouchDevice
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search');
    const clearBtn = document.getElementById('clear-search');
    if (searchInput && clearBtn) {
        searchInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
            }
        });
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            clearBtn.classList.add('hidden');
            searchInput.focus();

            // If the URL currently has a search filter, reload to clear results
            const url = new URL(window.location.href);
            if (url.searchParams.has('filter[search]')) {
                url.searchParams.delete('filter[search]');
                window.location.href = url.pathname + url.search;
            }
        });
    }

    const table = document.querySelector('.overflow-x-auto table');
    if (table) {
        const tbody = table.querySelector('tbody');
        if (tbody) {
            tbody.addEventListener('click', (evt) => {
                const tr = evt.target.closest('tr');
                if (!tr || !tbody.contains(tr)) return;

                // Remove selection from any previous row
                tbody.querySelectorAll('tr.selected').forEach(r => {
                    r.classList.remove('selected');
                    r.removeAttribute('aria-selected');
                });

                // Add selection to clicked row
                tr.classList.add('selected');
                tr.setAttribute('aria-selected', 'true');
            }, true); // capture phase so inner elements that stop propagation are still handled
        }
    }

    TagSelectorInitializer.init('#create-tags');
});
