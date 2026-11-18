import { Modal, Toast } from 'bootstrap';

export function toast(message, type = 'success') {
    const stack = document.querySelector('.toast-stack');
    if (!stack) return;

    const icons = { success: 'check-circle-fill', danger: 'exclamation-octagon-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type}`;
    el.setAttribute('role', 'status');
    el.innerHTML = `<div class="d-flex"><div class="toast-body d-flex gap-2 align-items-center"><i class="bi bi-${icons[type] || icons.info}"></i><span></span></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    el.querySelector('span').textContent = message;
    stack.appendChild(el);
    new Toast(el, { delay: 4500 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

function initToasts() {
    document.querySelectorAll('.toast-stack .toast').forEach((el) => new Toast(el, { delay: 5000 }).show());
}

/** Forms with data-confirm="message" ask before submitting, using a styled modal. */
function initConfirmations() {
    const modalEl = document.getElementById('confirmModal');
    if (!modalEl) return;
    const modal = new Modal(modalEl);
    let pendingForm = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form.matches('form[data-confirm]') || form.dataset.confirmed === '1') return;

        event.preventDefault();
        pendingForm = form;
        modalEl.querySelector('[data-confirm-title]').textContent = form.dataset.confirmTitle || 'Are you sure?';
        modalEl.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
        const okBtn = modalEl.querySelector('[data-confirm-ok]');
        okBtn.textContent = form.dataset.confirmButton || 'Confirm';
        okBtn.className = `btn ${form.dataset.confirmVariant === 'primary' ? 'btn-primary' : 'btn-danger'}`;
        modal.show();
    }, true);

    modalEl.querySelector('[data-confirm-ok]').addEventListener('click', () => {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = '1';
        modal.hide();
        pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit();
    });
}

/** Prevent double submissions and show a spinner on the clicked button. */
function initBusyButtons() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (event.defaultPrevented || form.dataset.noBusy !== undefined) return;
        if (form.dataset.submitting === '1') { event.preventDefault(); return; }
        form.dataset.submitting = '1';

        const button = event.submitter || form.querySelector('[type="submit"]');
        if (button) {
            button.classList.add('is-busy');
            button.dataset.originalHtml = button.innerHTML;
            button.innerHTML = `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>${button.dataset.busyText || 'Saving…'}</span>`;
            // Keep the clicked button's name/value in the submitted data.
            if (button.name) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = button.name; hidden.value = button.value;
                form.appendChild(hidden);
            }
        }
    });

    // Restore buttons when returning via browser back (bfcache).
    window.addEventListener('pageshow', (e) => {
        if (!e.persisted) return;
        document.querySelectorAll('form[data-submitting="1"]').forEach((form) => {
            form.dataset.submitting = '0';
            form.dataset.confirmed = '0';
            form.querySelectorAll('.is-busy').forEach((b) => { b.classList.remove('is-busy'); b.innerHTML = b.dataset.originalHtml; });
        });
    });
}

/** Table rows with data-href are clickable (ignoring clicks on inner links/buttons). */
function initRowLinks() {
    document.addEventListener('click', (event) => {
        const row = event.target.closest('[data-href]');
        if (!row || event.target.closest('a, button, input, label, form, select')) return;
        window.location.href = row.dataset.href;
    });
}

/** Auto-submit filter forms when a select changes. */
function initAutoSubmit() {
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        form.querySelectorAll('select, input[type="date"]').forEach((el) => el.addEventListener('change', () => form.requestSubmit()));
    });
}

/** Scroll the first invalid field into view after a failed submission. */
function focusFirstError() {
    const invalid = document.querySelector('.is-invalid');
    if (invalid) {
        invalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

/** "Last truck" checkbox switches the primary button to complete the whole order. */
function initCompleteToggle() {
    const toggle = document.querySelector('[data-complete-toggle]');
    const button = document.querySelector('[data-loaded-button]');
    if (!toggle || !button) return;
    const label = button.querySelector('span');
    const original = label.textContent;
    toggle.addEventListener('change', () => {
        button.value = toggle.checked ? 'complete' : 'loaded';
        label.textContent = toggle.checked ? 'Save & complete order' : original;
    });
}

/** Full-screen photo viewer with previous/next for [data-viewer-item] links. */
function initPhotoViewer() {
    const modalEl = document.getElementById('photoViewer');
    if (!modalEl) return;
    const items = Array.from(document.querySelectorAll('[data-viewer-item]'));
    const img = modalEl.querySelector('[data-viewer-image]');
    const caption = modalEl.querySelector('[data-viewer-caption]');
    const openLink = modalEl.querySelector('[data-viewer-open]');
    const modal = new Modal(modalEl);
    let index = 0;

    const show = (i) => {
        index = (i + items.length) % items.length;
        img.src = items[index].href;
        openLink.href = items[index].href;
        caption.textContent = `${items[index].dataset.caption || ''}  (${index + 1}/${items.length})`;
    };

    items.forEach((item, i) => item.addEventListener('click', (e) => { e.preventDefault(); show(i); modal.show(); }));
    modalEl.querySelector('[data-viewer-prev]').addEventListener('click', () => show(index - 1));
    modalEl.querySelector('[data-viewer-next]').addEventListener('click', () => show(index + 1));
    modalEl.querySelectorAll('[data-viewer-prev], [data-viewer-next]').forEach((b) => b.classList.toggle('d-none', items.length < 2));

    // Swipe left/right on phones.
    let startX = null;
    img.addEventListener('touchstart', (e) => { startX = e.touches[0].clientX; }, { passive: true });
    img.addEventListener('touchend', (e) => {
        if (startX === null) return;
        const dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 50) show(index + (dx < 0 ? 1 : -1));
        startX = null;
    });
    document.addEventListener('keydown', (e) => {
        if (!modalEl.classList.contains('show')) return;
        if (e.key === 'ArrowLeft') show(index - 1);
        if (e.key === 'ArrowRight') show(index + 1);
    });
}

export function initUi() {
    initCompleteToggle();
    initPhotoViewer();
    initToasts();
    initConfirmations();
    initBusyButtons();
    initRowLinks();
    initAutoSubmit();
    focusFirstError();
    window.appToast = toast;
}
