import { initPickers } from './picker';

/**
 * "Dispatch Samples" form: one block per dispatch, each with its own
 * samples, buyers, date and notes ("Add another dispatch"). Inputs are named
 * dispatches[N][field]; N only ever increases so removed blocks don't collide.
 * Everything is saved together with one button.
 */
export function initDispatchGroups() {
    const builder = document.querySelector('[data-dispatch-builder]');
    if (!builder) return;

    const container = builder.querySelector('[data-dispatch-groups]');
    const template = builder.querySelector('template[data-dispatch-group-template]');
    const saveLabel = document.querySelector('[data-save-label]');
    let nextKey = parseInt(builder.dataset.nextKey || '0', 10);

    const groups = () => Array.from(container.querySelectorAll('[data-dispatch-group]'));
    const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

    const refresh = () => {
        let total = 0;
        groups().forEach((group, i) => {
            group.querySelector('[data-group-number]').textContent = i + 1;
            group.querySelector('[data-remove-group]').classList.toggle('invisible', groups().length === 1);
            group.querySelectorAll('.choice-item').forEach((item) => item.classList.toggle('checked', item.querySelector('input').checked));

            const samples = group.querySelectorAll('[data-sample-chips] input[type="hidden"]').length;
            const buyers = group.querySelectorAll('[data-buyer-list] input:checked').length;
            const count = samples * buyers;
            total += count;
            group.querySelector('[data-sample-count]').textContent = samples;
            group.querySelector('[data-buyer-count]').textContent = buyers;

            const summary = group.querySelector('[data-group-summary]');
            summary.querySelector('[data-summary-text]').textContent = count
                ? `${plural(samples, 'sample')} × ${plural(buyers, 'buyer')} = ${plural(count, 'dispatch record')}`
                : 'Select at least one sample and one buyer';
            summary.classList.toggle('alert-success', count > 0);
            summary.classList.toggle('alert-secondary', count === 0);
        });
        if (saveLabel) saveLabel.textContent = total > 1 ? `Record ${total} dispatches` : 'Record dispatch';
    };

    const addGroup = () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__KEY__', nextKey++).trim();
        const group = wrapper.firstElementChild;

        // Usually sent the same day: copy the date from the dispatch above.
        const previous = groups().at(-1);
        const date = previous?.querySelector('[data-group-date]')?.value;
        if (date) group.querySelector('[data-group-date]').value = date;

        container.appendChild(group);
        initPickers(group);
        refresh();
        return group;
    };

    builder.addEventListener('click', (e) => {
        if (e.target.closest('[data-add-group]')) {
            const group = addGroup();
            group.scrollIntoView({ behavior: 'smooth', block: 'start' });
            group.querySelector('[data-picker-input]')?.focus({ preventScroll: true });
        }
        const remove = e.target.closest('[data-remove-group]');
        if (remove && groups().length > 1) {
            remove.closest('[data-dispatch-group]').remove();
            refresh();
        }
    });

    // Filter one dispatch's buyer list.
    builder.addEventListener('input', (e) => {
        const filter = e.target.closest('[data-choice-filter]');
        if (!filter) return;
        const list = filter.closest('[data-dispatch-group]').querySelector('[data-buyer-list]');
        const q = filter.value.trim().toLowerCase();
        let shown = 0;
        list.querySelectorAll('.choice-item').forEach((item) => {
            const match = item.textContent.toLowerCase().includes(q);
            item.classList.toggle('d-none', !match);
            if (match) shown++;
        });
        list.querySelector('[data-choice-empty]')?.classList.toggle('d-none', shown > 0);
    });

    builder.addEventListener('change', refresh);
    builder.addEventListener('picker:change', refresh);

    if (!groups().length) addGroup();
    refresh();
}
