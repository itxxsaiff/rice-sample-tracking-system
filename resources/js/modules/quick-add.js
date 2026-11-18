import { Modal } from 'bootstrap';
import { requestJson } from './http';
import { toast } from './ui';

/**
 * Inline "add new" modals so the user never has to leave a half-filled form.
 *   Trigger: <button data-quick-add="#quickMillModal"> inside a .quick-scope wrapping a picker or select.
 *   Modal form: <form data-quick-form action="..."> returns { id, label, sub }.
 */
export function initQuickAdd() {
    let activeScope = null;

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-quick-add]');
        if (!trigger) return;
        activeScope = trigger.closest('.quick-scope');
        const modalEl = document.querySelector(trigger.dataset.quickAdd);
        const form = modalEl.querySelector('form');
        form.reset();
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        Modal.getOrCreateInstance(modalEl).show();
        modalEl.addEventListener('shown.bs.modal', () => form.querySelector('input:not([type=hidden])')?.focus(), { once: true });
    });

    document.querySelectorAll('form[data-quick-form]').forEach((form) => {
        form.dataset.noBusy = '';
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = form.querySelector('[type="submit"]');
            button.disabled = true;
            form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));

            try {
                const item = await requestJson(form.action, { method: 'POST', body: new FormData(form) });
                applyToScope(activeScope, item, form.dataset.quickForm);
                Modal.getInstance(form.closest('.modal')).hide();
                toast(`${item.label} added`);
            } catch (error) {
                Object.entries(error.errors || {}).forEach(([field, messages]) => {
                    const el = form.querySelector(`[name="${field}"]`);
                    if (!el) return;
                    el.classList.add('is-invalid');
                    const feedback = el.parentElement.querySelector('.invalid-feedback');
                    if (feedback) feedback.textContent = messages[0];
                });
                if (!Object.keys(error.errors || {}).length) toast(error.message, 'danger');
            } finally {
                button.disabled = false;
            }
        });
    });
}

function applyToScope(scope, item, kind) {
    // Select-based targets (varieties): add the option to every matching select on the page.
    if (kind === 'variety') {
        document.querySelectorAll('select[data-variety-select], template').forEach((node) => {
            const selects = node.tagName === 'TEMPLATE'
                ? node.content.querySelectorAll('select[data-variety-select]')
                : [node];
            selects.forEach((select) => {
                const option = new Option(item.label, item.id);
                select.add(option);
            });
        });
        const target = scope?.querySelector('select');
        if (target) { target.value = item.id; target.classList.remove('is-invalid'); }
        return;
    }

    // Checkbox list targets (buyers on the dispatch form): add a pre-ticked entry.
    if (kind === 'buyer') {
        const list = document.querySelector('[data-choice-group="buyers"]');
        if (!list) return;
        const label = document.createElement('label');
        label.className = 'choice-item checked';
        label.innerHTML = `<input class="form-check-input" type="checkbox" name="buyer_ids[]" checked>
            <span class="min-w-0"><span class="d-block fw-medium text-truncate"></span><span class="small text-muted"></span></span>`;
        label.querySelector('input').value = item.id;
        label.querySelector('.fw-medium').textContent = item.label;
        label.querySelector('.small').textContent = item.sub || '';
        list.prepend(label);
        label.querySelector('input').dispatchEvent(new Event('change', { bubbles: true }));
        return;
    }

    const picker = scope?.querySelector('[data-picker]');
    if (picker) picker.dispatchEvent(new CustomEvent('picker:set', { detail: item }));
}
