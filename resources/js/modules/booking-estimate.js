const rupiahFormat = new Intl.NumberFormat('id-ID');
const rupiah = (n) => `Rp ${rupiahFormat.format(n)}`;
const qty = (input) => Math.max(0, parseInt(input.value, 10) || 0);

export function initBookingEstimate() {
    const form = document.getElementById('booking-form');
    if (!form) return;

    const nights = Number(form.dataset.nights);
    const unitPrice = Number(form.dataset.unitPrice);
    const taxRate = Number(form.dataset.taxRate);
    const field = (key) => form.querySelector(`[data-est="${key}"]`);

    const sum = (role, perNightAware) => {
        let total = 0;
        form.querySelectorAll(`[data-role="${role}"]`).forEach((input) => {
            const factor = perNightAware && input.hasAttribute('data-per-night') ? nights : 1;
            total += qty(input) * Number(input.dataset.price) * factor;
        });
        return total;
    };

    const refresh = () => {
        const units = qty(form.querySelector('[data-role="units"]'));
        const unitsTotal = units * unitPrice;
        const addons = sum('addon', true);
        const food = sum('food', false);
        const subtotal = unitsTotal + addons + food;
        const tax = Math.round(subtotal * taxRate);

        field('units').textContent = rupiah(unitsTotal);
        form.querySelector('[data-est-label="units"]').textContent = `${units} unit x ${nights} malam`;
        field('addons').textContent = rupiah(addons);
        field('food').textContent = rupiah(food);
        field('tax').textContent = rupiah(tax);
        field('total').textContent = rupiah(subtotal + tax);
    };

    const syncStepper = (box) => {
        const input = box.querySelector('input');
        const min = Number(input.min);
        const max = Number(input.max);
        const value = Math.min(max, Math.max(min, parseInt(input.value, 10) || min));
        input.value = value;
        box.querySelector('[data-step="-1"]').disabled = value <= min;
        box.querySelector('[data-step="1"]').disabled = value >= max;

        const row = box.closest('[data-menu-row]');
        if (row) row.querySelector('[data-serve]').hidden = value < 1;
    };

    form.querySelectorAll('[data-stepper]').forEach((box) => {
        const input = box.querySelector('input');
        box.addEventListener('click', (event) => {
            const button = event.target.closest('[data-step]');
            if (!button) return;
            input.value = (parseInt(input.value, 10) || 0) + Number(button.dataset.step);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        input.addEventListener('input', () => {
            syncStepper(box);
            refresh();
        });
        syncStepper(box);
    });

    refresh();
}
