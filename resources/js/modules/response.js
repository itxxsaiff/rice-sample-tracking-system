import { Modal } from 'bootstrap';

/**
 * "Record buyer response" popup.
 *
 * Buttons carry the dispatch as data-respond='{…}' (SampleDispatch::respondData()).
 * Confirmed shows the order fields; Rejected needs nothing else.
 * The form posts normally, so the offline safety net (data-offline-op) and
 * server validation work exactly like any other form.
 */
// Quantity is always in maunds and the rate per maund (set by the server).
const FIELDS = ['confirmed_quantity', 'rate', 'packing_type', 'packing_details', 'confirmation_date'];

function today() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

export function initResponseForm() {
    const el = document.getElementById('respondModal');
    if (!el) return;
    const form = el.querySelector('[data-response-form]');
    const modal = Modal.getOrCreateInstance(el);
    const q = (sel) => form.querySelector(sel);

    const sync = () => {
        const status = q('input[name="status"]:checked')?.value;
        form.querySelectorAll('[data-response-section]').forEach((section) => {
            const active = section.dataset.responseSection === status;
            section.classList.toggle('d-none', !active);
            section.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !active; });
        });
        form.querySelectorAll('.response-option').forEach((opt) => opt.classList.toggle('selected', opt.querySelector('input').checked));
    };

    const clearErrors = () => {
        form.querySelectorAll('.is-invalid').forEach((input) => input.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach((fb) => fb.remove());
    };

    /** Point the form at this dispatch; keepInput = the server sent the form back with errors. */
    const prepare = (d, keepInput = false) => {
        form.action = d.action;
        form.dataset.offlineTarget = d.uuid;
        form.dataset.offlineVersion = d.version;
        q('[name="respond_dispatch_id"]').value = d.id;
        q('[data-respond-title]').textContent = d.status ? 'Edit buyer response' : 'Record buyer response';
        q('[data-respond-sub]').textContent = `Dispatch ${d.number} · sent to ${d.buyer} on ${d.sent_label}`;
        q('[data-respond-sample]').textContent = d.sample;
        q('[data-respond-mill]').textContent = d.mill;
        q('[data-respond-question]').textContent = `What did ${d.buyer} decide?`;
        q('[name="confirmation_date"]').min = d.sent;
        q('[name="rejection_date"]').value = today();
        q('[data-response-choice="rejected"]').classList.toggle('d-none', !!d.locked);
        q('[data-respond-locked]').classList.toggle('d-none', !d.locked);

        if (!keepInput) {
            clearErrors();
            form.querySelectorAll('input[name="status"]').forEach((r) => { r.checked = r.value === d.status; });
            FIELDS.forEach((name) => {
                const input = q(`[name="${name}"]`);
                const value = d.values?.[name] ?? (name === 'confirmation_date' ? today() : '');
                // Older orders may have a packing that is no longer in the list: ask again.
                input.value = input.tagName === 'SELECT' && ![...input.options].some((o) => o.value === String(value)) ? '' : value;
            });
        }
        sync();
    };

    form.addEventListener('change', (e) => { if (e.target.name === 'status') sync(); });

    // Quick checks before sending (the server validates everything again). Registered on
    // document before the offline safety net, so a change saved on the phone is complete too.
    document.addEventListener('submit', (e) => {
        if (e.target !== form) return;
        clearErrors();
        const status = q('input[name="status"]:checked')?.value;
        let first = null;
        const fail = (name, message) => {
            const input = q(`[name="${name}"]`);
            input.classList.add('is-invalid');
            const fb = document.createElement('div');
            fb.className = 'invalid-feedback';
            fb.textContent = message;
            input.after(fb);
            first ??= input;
        };
        if (!status) {
            e.preventDefault();
            e.stopImmediatePropagation();
            window.alert('Choose whether the buyer confirmed or rejected the sample.');
            return;
        }
        if (status === 'confirmed') {
            if (!(Number(q('[name="confirmed_quantity"]').value) > 0)) fail('confirmed_quantity', 'Enter the confirmed quantity.');
            if (!(Number(q('[name="rate"]').value) > 0)) fail('rate', 'Enter the agreed rate.');
            if (!q('[name="packing_type"]').value) fail('packing_type', 'Choose the bag size.');
            if (!q('[name="confirmation_date"]').value) fail('confirmation_date', 'Enter the confirmation date.');
        }
        if (first) {
            e.preventDefault();
            e.stopImmediatePropagation();
            first.focus();
        }
    }, true);

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-respond]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        prepare(JSON.parse(btn.dataset.respond));
        modal.show();
    });

    // Sent back by the server with errors: reopen with what was typed.
    if (el.dataset.reopen) {
        prepare(JSON.parse(el.dataset.reopen), true);
        modal.show();
        return;
    }

    // Old "record response" links arrive as ?respond=1 on the dispatch page.
    const params = new URLSearchParams(location.search);
    const auto = params.has('respond') && document.querySelector('[data-respond][data-respond-auto]');
    if (params.has('respond')) {
        params.delete('respond');
        history.replaceState(null, '', `${location.pathname}${params.size ? `?${params}` : ''}${location.hash}`);
    }
    if (auto) auto.click();
}
