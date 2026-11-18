import { initPickers } from './picker';
import { initImageInputs } from './images';

/**
 * Bundle builder: add/remove sample rows on the client, no page reloads.
 * Each row's inputs are named samples[N][field]; N is a monotonically
 * increasing key so removed rows don't leave index collisions.
 */
export function initBundleBuilder() {
    const builder = document.querySelector('[data-bundle-builder]');
    if (!builder) return;

    const container = builder.querySelector('[data-bundle-rows]');
    const template = builder.querySelector('template[data-bundle-template]');
    const counter = document.querySelectorAll('[data-bundle-count]');
    let nextKey = parseInt(builder.dataset.nextKey || '0', 10);
    const bundleDate = document.querySelector('[data-bundle-date]');
    let previousDate = bundleDate?.value;

    // Rows follow the bundle date unless the user changed a row's date by hand.
    bundleDate?.addEventListener('change', () => {
        container.querySelectorAll('[data-row-date]').forEach((input) => {
            if (!input.value || input.value === previousDate) input.value = bundleDate.value;
        });
        previousDate = bundleDate.value;
    });

    const renumber = () => {
        const rows = container.querySelectorAll('.bundle-row');
        rows.forEach((row, i) => {
            row.querySelector('[data-row-number]').textContent = i + 1;
            row.querySelector('[data-remove-row]').classList.toggle('invisible', rows.length === 1);
        });
        counter.forEach((el) => { el.textContent = rows.length; });
    };

    const addRow = (copyFrom = null) => {
        const html = template.innerHTML.replaceAll('__KEY__', nextKey++);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;

        // "Same mill as previous" saves taps when several samples come from one mill.
        if (copyFrom) {
            const prevHidden = copyFrom.querySelector('[data-picker] input[type="hidden"]');
            const prevText = copyFrom.querySelector('[data-picker-input]');
            const prevVariety = copyFrom.querySelector('select[data-variety-select]');
            const prevUnit = copyFrom.querySelector('[data-unit-select]');
            if (prevHidden?.value) {
                row.querySelector('[data-picker] input[type="hidden"]').value = prevHidden.value;
                row.querySelector('[data-picker-input]').value = prevText.value;
            }
            if (prevVariety) row.querySelector('select[data-variety-select]').value = prevVariety.value;
            if (prevUnit) row.querySelector('[data-unit-select]').value = prevUnit.value;
        }

        if (bundleDate?.value) row.querySelector('[data-row-date]').value = bundleDate.value;

        container.appendChild(row);
        initPickers(row);
        initImageInputs(row);
        renumber();
        return row;
    };

    builder.addEventListener('click', (e) => {
        if (e.target.closest('[data-add-row]')) {
            const rows = container.querySelectorAll('.bundle-row');
            const row = addRow(rows[rows.length - 1] || null);
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        const remove = e.target.closest('[data-remove-row]');
        if (remove && container.querySelectorAll('.bundle-row').length > 1) {
            remove.closest('.bundle-row').remove();
            renumber();
        }
    });

    if (!container.querySelector('.bundle-row')) addRow();
    renumber();
}
