/** Dispatch response form: show only the section for the chosen outcome. */
export function initResponseForm() {
    const form = document.querySelector('[data-response-form]');
    if (!form) return;

    const sync = () => {
        const status = form.querySelector('input[name="status"]:checked')?.value;
        form.querySelectorAll('[data-response-section]').forEach((section) => {
            const active = section.dataset.responseSection === status;
            section.classList.toggle('d-none', !active);
            section.querySelectorAll('input, select, textarea').forEach((el) => { el.disabled = !active; });
        });
        form.querySelectorAll('.response-option').forEach((opt) => {
            opt.classList.toggle('selected', opt.querySelector('input').checked);
        });
    };

    form.addEventListener('change', (e) => { if (e.target.name === 'status') sync(); });

    // Quick rejection reasons.
    form.querySelectorAll('[data-fill-reason]').forEach((btn) => btn.addEventListener('click', () => {
        const field = form.querySelector('[name="rejection_reason"]');
        field.value = field.value.trim() ? `${field.value.trim()}; ${btn.dataset.fillReason}` : btn.dataset.fillReason;
        field.classList.remove('is-invalid');
        field.focus();
    }));

    sync();
}
