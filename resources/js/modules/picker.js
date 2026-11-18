import { escapeHtml, requestJson } from './http';

/**
 * Searchable remote picker (replacement for very long <select> lists, which are
 * painful on iPhone).
 *
 * <div class="picker" data-picker data-source="/lookup/mills" data-name="mill_id" [data-multiple]>
 *   <input type="text" class="form-control" data-picker-input>
 *   <input type="hidden" name="mill_id" value="">          (single mode)
 *   <div data-picker-chips></div>                           (multiple mode)
 *   <div class="picker-menu"></div>
 * </div>
 *
 * The endpoint returns { data: [{ id, label, sub }] }.
 */
export function initPicker(root) {
    if (root.dataset.pickerReady) return;
    root.dataset.pickerReady = '1';

    const input = root.querySelector('[data-picker-input]');
    const menu = root.querySelector('.picker-menu');
    const hidden = root.querySelector('input[type="hidden"]');
    const chips = root.querySelector('[data-picker-chips]');
    const multiple = root.hasAttribute('data-multiple');
    const name = root.dataset.name;
    let items = [];
    let highlight = -1;
    let timer = null;
    let requestId = 0;

    const selectedIds = () => multiple
        ? Array.from(chips.querySelectorAll('input[type="hidden"]')).map((i) => i.value)
        : [hidden.value];

    const renderMenu = () => {
        const taken = selectedIds();
        const visible = items.filter((item) => !multiple || !taken.includes(String(item.id)));
        if (!visible.length) {
            menu.innerHTML = `<div class="picker-empty">${input.value ? 'No matches found' : 'Start typing to search'}</div>`;
            return;
        }
        menu.innerHTML = visible.map((item, i) => `
            <div class="picker-option ${i === highlight ? 'highlight' : ''}" data-index="${items.indexOf(item)}">
                <div class="fw-medium">${escapeHtml(item.label)}</div>
                ${item.sub ? `<div class="sub">${escapeHtml(item.sub)}</div>` : ''}
            </div>`).join('');
    };

    const search = (query) => {
        const id = ++requestId;
        const url = new URL(root.dataset.source, window.location.origin);
        url.searchParams.set('q', query);
        menu.innerHTML = '<div class="picker-empty"><span class="spinner-border spinner-border-sm me-2"></span>Searching…</div>';
        requestJson(url.toString())
            .then((res) => { if (id === requestId) { items = res.data || []; highlight = -1; renderMenu(); } })
            .catch(() => { menu.innerHTML = '<div class="picker-empty text-danger">Could not load results</div>'; });
    };

    const open = () => { root.classList.add('open'); search(input.value.trim()); };
    const close = () => root.classList.remove('open');

    const addChip = (item) => {
        if (selectedIds().includes(String(item.id))) return;
        const chip = document.createElement('div');
        chip.className = 'd-flex align-items-center justify-content-between gap-2 border rounded-3 px-3 py-2 mb-2 bg-white';
        chip.innerHTML = `<div class="min-w-0"><div class="fw-semibold text-truncate">${escapeHtml(item.label)}</div>
            ${item.sub ? `<div class="small text-muted text-truncate">${escapeHtml(item.sub)}</div>` : ''}</div>
            <button type="button" class="btn btn-sm btn-light flex-shrink-0" aria-label="Remove"><i class="bi bi-x-lg"></i></button>
            <input type="hidden" name="${escapeHtml(name)}[]" value="${escapeHtml(item.id)}">`;
        chip.querySelector('button').addEventListener('click', () => { chip.remove(); root.dispatchEvent(new CustomEvent('picker:change', { bubbles: true })); });
        chips.appendChild(chip);
    };

    const choose = (item) => {
        if (multiple) {
            addChip(item);
            input.value = '';
            input.focus();
            search('');
        } else {
            hidden.value = item.id;
            input.value = item.label;
            input.classList.remove('is-invalid');
            close();
        }
        root.dispatchEvent(new CustomEvent('picker:change', { bubbles: true, detail: item }));
    };

    input.addEventListener('focus', open);
    input.addEventListener('input', () => {
        if (!multiple) hidden.value = '';
        root.classList.add('open');
        clearTimeout(timer);
        timer = setTimeout(() => search(input.value.trim()), 220);
    });
    input.addEventListener('keydown', (e) => {
        const options = menu.querySelectorAll('.picker-option');
        if (e.key === 'ArrowDown') { e.preventDefault(); highlight = Math.min(highlight + 1, options.length - 1); renderMenu(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight = Math.max(highlight - 1, 0); renderMenu(); }
        else if (e.key === 'Enter') {
            if (root.classList.contains('open') && options[highlight]) { e.preventDefault(); choose(items[options[highlight].dataset.index]); }
            else if (root.classList.contains('open')) e.preventDefault();
        } else if (e.key === 'Escape') close();
    });
    // pointerdown fires before the input blurs, so the choice registers on touch devices.
    menu.addEventListener('pointerdown', (e) => {
        const option = e.target.closest('.picker-option');
        if (!option) return;
        e.preventDefault();
        choose(items[option.dataset.index]);
    });
    document.addEventListener('pointerdown', (e) => { if (!root.contains(e.target)) close(); });

    // Programmatic selection (e.g. after quick-adding a new mill).
    root.addEventListener('picker:set', (e) => choose(e.detail));

    if (multiple && root.dataset.initial) {
        JSON.parse(root.dataset.initial).forEach(addChip);
    }
}

export function initPickers(scope = document) {
    scope.querySelectorAll('[data-picker]').forEach(initPicker);
}
