import { initPickers } from './picker';

/**
 * "Add Sample" form: add/remove sample rows on the client, no page reloads.
 * Every row is a separate, independent sample. Inputs are named
 * samples[N][field]; N only ever increases so removed rows don't collide.
 */
export function initSampleRows() {
    const builder = document.querySelector('[data-sample-rows-builder]');
    if (!builder) return;

    const container = builder.querySelector('[data-sample-rows]');
    const template = builder.querySelector('template[data-sample-row-template]');
    const saveLabel = document.querySelector('[data-save-label]');
    let nextKey = parseInt(builder.dataset.nextKey || '0', 10);

    const renumber = () => {
        const rows = container.querySelectorAll('.sample-row');
        rows.forEach((row, i) => {
            row.querySelector('[data-row-number]').textContent = i + 1;
            row.querySelector('[data-remove-row]').classList.toggle('invisible', rows.length === 1);
        });
        if (saveLabel) saveLabel.textContent = rows.length > 1 ? `Save ${rows.length} samples` : 'Save sample';
    };

    const addRow = (previous = null) => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__KEY__', nextKey++).trim();
        const row = wrapper.firstElementChild;

        // Same collection day as the row above saves taps; mill and variety are chosen per sample.
        if (previous) {
            const date = previous.querySelector('[data-row-date]')?.value;
            if (date) row.querySelector('[data-row-date]').value = date;
        }

        container.appendChild(row);
        initPickers(row);
        renumber();
        return row;
    };

    builder.addEventListener('click', (e) => {
        if (e.target.closest('[data-add-row]')) {
            const rows = container.querySelectorAll('.sample-row');
            const row = addRow(rows[rows.length - 1] || null);
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.querySelector('[data-picker-input]')?.focus({ preventScroll: true });
        }
        const remove = e.target.closest('[data-remove-row]');
        if (remove && container.querySelectorAll('.sample-row').length > 1) {
            remove.closest('.sample-row').remove();
            renumber();
        }
    });

    if (!container.querySelector('.sample-row')) addRow();
    renumber();
}
