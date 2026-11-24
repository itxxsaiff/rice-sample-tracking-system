/**
 * Loading forms: the number of bags follows the quantity and the bag size
 * (1 maund = 40 kg, so 1000 maund in 25 kg bags = 1600 bags). It can still be
 * typed over; it is worked out again when the quantity or bag size changes.
 * Same rule as Loading::bagsFor() on the server.
 */
export function bagsFor(quantity, kgPerUnit, packing) {
    const bagKg = parseFloat((String(packing || '').match(/^\s*(\d+(?:\.\d+)?)\s*kg\s*$/i) || [])[1]);
    const q = parseFloat(quantity);
    if (!(q > 0) || !(kgPerUnit > 0) || !(bagKg > 0)) return null;
    return Math.ceil(Math.round((q * kgPerUnit / bagKg) * 1e6) / 1e6);
}

/** Wire one form section: [data-bag-calc data-kg-per-unit] containing quantity, packing_type and bag_count. */
export function initBagCount(section) {
    if (!section || section.dataset.bagCalcReady) return;
    section.dataset.bagCalcReady = '1';
    const kgPerUnit = parseFloat(section.dataset.kgPerUnit);
    const quantity = section.querySelector('[name="quantity"]');
    const packing = section.querySelector('[name="packing_type"]');
    const bags = section.querySelector('[name="bag_count"]');
    if (!quantity || !packing || !bags) return;

    const update = () => {
        const n = bagsFor(quantity.value, kgPerUnit, packing.value);
        if (n !== null) bags.value = n;
    };
    quantity.addEventListener('input', update);
    packing.addEventListener('change', update);
    if (!bags.value) update();
}

export function initBagCounts(scope = document) {
    scope.querySelectorAll('[data-bag-calc]').forEach(initBagCount);
}
