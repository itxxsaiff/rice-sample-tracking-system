import { Modal } from 'bootstrap';
import { initImageInputs } from '../../modules/images';
import { cfg, h, matches } from './util';

/**
 * Form controls for the offline app. Markup and classes match the online
 * Blade components, so the forms look and behave the same.
 */

const id = (name) => `o_${name.replace(/[^a-z0-9]+/gi, '_')}`;

export function input(name, label, { value = '', type = 'text', required = false, hint = '', cls = 'mb-3', attrs = '', placeholder = '', prefix = '', suffix = '' } = {}) {
    const field = `<input type="${type}" name="${h(name)}" id="${id(name)}" value="${h(value ?? '')}" class="form-control" ${required ? 'required' : ''} placeholder="${h(placeholder)}" ${attrs}>`;
    const group = prefix || suffix
        ? `<div class="input-group has-validation">${prefix ? `<span class="input-group-text">${h(prefix)}</span>` : ''}${field}${suffix ? `<span class="input-group-text">${h(suffix)}</span>` : ''}<div class="invalid-feedback"></div></div>`
        : `${field}<div class="invalid-feedback"></div>`;
    return `<div class="${cls}">${label ? `<label class="form-label ${required ? 'required' : ''}" for="${id(name)}">${h(label)}</label>` : ''}
        ${group}
        ${hint ? `<div class="form-text">${h(hint)}</div>` : ''}</div>`;
}

export function textarea(name, label, { value = '', rows = 2, cls = 'mb-3', placeholder = '', required = false } = {}) {
    return `<div class="${cls}"><label class="form-label ${required ? 'required' : ''}" for="${id(name)}">${h(label)}</label>
        <textarea name="${h(name)}" id="${id(name)}" rows="${rows}" class="form-control" placeholder="${h(placeholder)}" ${required ? 'required' : ''}>${h(value ?? '')}</textarea><div class="invalid-feedback"></div></div>`;
}

export function select(name, label, options, { value = '', required = false, placeholder = null, cls = 'mb-3', attrs = '' } = {}) {
    const opts = Object.entries(options).map(([k, v]) => `<option value="${h(k)}" ${String(value) === String(k) ? 'selected' : ''}>${h(v)}</option>`).join('');
    return `<div class="${cls}">${label ? `<label class="form-label ${required ? 'required' : ''}" for="${id(name)}">${h(label)}</label>` : ''}
        <select name="${h(name)}" id="${id(name)}" class="form-select" ${required ? 'required' : ''} ${attrs}>${placeholder !== null ? `<option value="">${h(placeholder)}</option>` : ''}${opts}</select>
        <div class="invalid-feedback"></div></div>`;
}

export function photoInput(name, { multiple = false, label = '' } = {}) {
    return `<div class="mb-0" data-image-upload ${multiple ? `data-multiple data-max="${cfg().max_photos || 10}"` : ''}>
        ${label ? `<label class="form-label">${h(label)}</label>` : ''}
        <div class="upload-buttons">
            <label class="btn btn-soft mb-0"><i class="bi bi-camera"></i> Take photo<input type="file" accept="image/*" capture="environment" class="d-none" data-image-source></label>
            <label class="btn btn-light mb-0"><i class="bi bi-images"></i> ${multiple ? 'Choose photos' : 'Choose photo'}<input type="file" name="${h(name)}" accept="image/*,.heic,.heif" ${multiple ? 'multiple' : ''} class="d-none" data-image-target></label>
        </div>
        <div class="small text-muted mt-2" data-image-status></div>
        <div class="photo-grid mt-2" data-image-previews></div>
        <div class="invalid-feedback d-block" data-error-for="${h(name)}"></div>
    </div>`;
}

/**
 * Searchable picker over local records (mills, buyers). Value = record uuid.
 * items: [{ uuid, label, sub }]
 */
export function picker(name, label, { value = '', display = '', required = false, quickAdd = null, cls = 'mb-3', placeholder = 'Search…' } = {}) {
    return `<div class="${cls} quick-scope">
        <div class="d-flex justify-content-between align-items-end gap-2">
            <label class="form-label ${required ? 'required' : ''}" for="${id(name)}">${h(label)}</label>
            ${quickAdd ? `<button type="button" class="btn btn-link btn-sm p-0 mb-1 text-decoration-none" data-local-quick-add="${h(quickAdd)}"><i class="bi bi-plus-circle"></i> New ${h(quickAdd)}</button>` : ''}
        </div>
        <div class="picker" data-local-picker="${h(name)}">
            <div class="search-input"><i class="bi bi-search"></i>
                <input type="text" id="${id(name)}" class="form-control" data-picker-input value="${h(display)}" placeholder="${h(placeholder)}" autocomplete="off" autocorrect="off" spellcheck="false">
            </div>
            <input type="hidden" name="${h(name)}" value="${h(value)}">
            <div class="picker-menu" role="listbox"></div>
        </div>
        <div class="invalid-feedback d-block" data-error-for="${h(name)}"></div>
    </div>`;
}

export function mountPicker(root, getItems) {
    const input = root.querySelector('[data-picker-input]');
    const hidden = root.querySelector('input[type=hidden]');
    const menu = root.querySelector('.picker-menu');
    if (root.dataset.ready) return;
    root.dataset.ready = '1';

    const render = () => {
        const q = input.value;
        const items = getItems().filter((i) => matches(`${i.label} ${i.sub}`, q)).slice(0, 30);
        menu.innerHTML = items.length
            ? items.map((i) => `<div class="picker-option" data-uuid="${h(i.uuid)}"><div class="fw-medium">${h(i.label)}</div>${i.sub ? `<div class="sub">${h(i.sub)}</div>` : ''}</div>`).join('')
            : '<div class="picker-empty">No matches on this device</div>';
    };
    input.addEventListener('focus', () => { root.classList.add('open'); render(); });
    input.addEventListener('input', () => { hidden.value = ''; root.classList.add('open'); render(); });
    menu.addEventListener('pointerdown', (e) => {
        const opt = e.target.closest('.picker-option');
        if (!opt) return;
        e.preventDefault();
        setPicker(root, getItems().find((i) => i.uuid === opt.dataset.uuid));
    });
    document.addEventListener('pointerdown', (e) => { if (!root.contains(e.target)) root.classList.remove('open'); });
}

export function setPicker(root, item) {
    if (!item) return;
    root.querySelector('input[type=hidden]').value = item.uuid;
    root.querySelector('[data-picker-input]').value = item.sub ? `${item.label} — ${item.sub}` : item.label;
    root.classList.remove('open');
    root.closest('.quick-scope')?.querySelector('[data-error-for]')?.replaceChildren();
    root.dispatchEvent(new CustomEvent('picker:change', { bubbles: true, detail: item }));
}

export function enableImages(scope) {
    initImageInputs(scope);
}

/** Files currently selected (already compressed) in a photo input. */
export function photosFrom(scope, name) {
    const el = scope.querySelector(`input[data-image-target][name="${name}"]`);
    return el ? Array.from(el.files || []) : [];
}

/** Plain object from a form (repeated names ending in [] become arrays). */
export function values(form) {
    const out = {};
    for (const [key, value] of new FormData(form).entries()) {
        if (value instanceof File) continue;
        if (key.endsWith('[]')) (out[key.slice(0, -2)] ??= []).push(value);
        else out[key] = typeof value === 'string' ? value.trim() : value;
    }
    return out;
}

/** Show { field: message } next to the matching inputs; returns true if there were errors. */
export function showErrors(form, errors) {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach((el) => { el.textContent = ''; });

    for (const [key, message] of Object.entries(errors)) {
        const el = form.querySelector(`[name="${CSS.escape(key)}"]`);
        const holder = form.querySelector(`[data-error-for="${CSS.escape(key)}"]`);
        if (holder) holder.textContent = message;
        if (el && el.type !== 'hidden') {
            el.classList.add('is-invalid');
            if (!holder) {
                const feedback = el.parentElement.querySelector('.invalid-feedback');
                if (feedback) feedback.textContent = message;
            }
        }
        el?.closest('.picker')?.querySelector('[data-picker-input]')?.classList.add('is-invalid');
    }

    const first = form.querySelector('.is-invalid, [data-error-for]:not(:empty)');
    first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return Object.keys(errors).length > 0;
}

/**
 * A small dialog for adding a mill or buyer offline from inside another form.
 * onSave(fields) must return the created item or an { errors } object.
 */
export function quickAddDialog(kind, onSave) {
    const el = document.createElement('div');
    el.className = 'modal fade';
    el.tabIndex = -1;
    el.innerHTML = `<div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down"><form class="modal-content" novalidate>
        <div class="modal-header"><h5 class="modal-title fw-semibold">New ${h(kind)}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            ${input('name', kind === 'mill' ? 'Mill name' : 'Buyer / company name', { required: true, attrs: 'autocapitalize="words"' })}
            ${input('city', 'City', { required: true, attrs: 'list="offlineCities" autocapitalize="words" autocomplete="off"' })}
            <div class="row">${input('contact_person', 'Contact person', { cls: 'col-6 mb-3' })}${input('phone', 'Phone', { type: 'tel', cls: 'col-6 mb-3', attrs: 'inputmode="tel"' })}</div>
            <datalist id="offlineCities">${(cfg().cities || []).map((c) => `<option value="${h(c)}"></option>`).join('')}</datalist>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add ${h(kind)}</button></div>
    </form></div>`;
    document.body.appendChild(el);
    const modal = new Modal(el);
    const form = el.querySelector('form');

    return new Promise((resolve) => {
        let result = null;
        let shown = false;
        // Bootstrap ignores hide() while the opening animation runs; wait for it.
        const close = () => (shown ? modal.hide() : el.addEventListener('shown.bs.modal', () => modal.hide(), { once: true }));
        const button = form.querySelector('.btn-primary');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (button.disabled) return;
            button.disabled = true;
            try {
                const res = await onSave(values(form));
                if (res?.errors) { showErrors(form, res.errors); return; }
                result = res;
                close();
            } finally {
                button.disabled = false;
            }
        });
        el.addEventListener('hidden.bs.modal', () => { el.remove(); resolve(result); });
        el.addEventListener('shown.bs.modal', () => { shown = true; form.querySelector('input')?.focus(); });
        modal.show();
    });
}
