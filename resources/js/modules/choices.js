/**
 * Big-checkbox multi-select lists with a text filter.
 *   <input data-choice-filter="#buyerList">
 *   <div id="buyerList" class="choice-list" data-choice-group="buyers"> <label class="choice-item">…</label> </div>
 *   <span data-choice-count="buyers"></span>
 */
export function initChoiceLists() {
    document.querySelectorAll('[data-choice-filter]').forEach((input) => {
        const list = document.querySelector(input.dataset.choiceFilter);
        if (!list) return;
        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            let shown = 0;
            list.querySelectorAll('.choice-item').forEach((item) => {
                const match = item.textContent.toLowerCase().includes(q);
                item.classList.toggle('d-none', !match);
                if (match) shown++;
            });
            list.querySelector('[data-choice-empty]')?.classList.toggle('d-none', shown > 0);
        });
    });

    const refresh = () => {
        document.querySelectorAll('.choice-item').forEach((item) => {
            item.classList.toggle('checked', item.querySelector('input').checked);
        });
        document.querySelectorAll('[data-choice-count]').forEach((el) => {
            const group = document.querySelector(`[data-choice-group="${el.dataset.choiceCount}"]`);
            el.textContent = group ? group.querySelectorAll('input:checked').length : 0;
        });
        updateDispatchSummary();
    };

    document.addEventListener('change', (e) => { if (e.target.closest('.choice-item')) refresh(); });
    document.addEventListener('picker:change', updateDispatchSummary);
    refresh();
}

/** "This will create N dispatch records" preview on the dispatch form. */
function updateDispatchSummary() {
    const summary = document.querySelector('[data-dispatch-summary]');
    if (!summary) return;
    const samples = document.querySelectorAll('[data-sample-chips] input[type="hidden"]').length;
    const buyers = document.querySelectorAll('[data-choice-group="buyers"] input:checked').length;
    const total = samples * buyers;
    summary.querySelector('[data-summary-text]').textContent = total
        ? `${samples} sample${samples > 1 ? 's' : ''} × ${buyers} buyer${buyers > 1 ? 's' : ''} = ${total} dispatch record${total > 1 ? 's' : ''}`
        : 'Select at least one sample and one buyer';
    summary.classList.toggle('alert-success', total > 0);
    summary.classList.toggle('alert-secondary', total === 0);
}
