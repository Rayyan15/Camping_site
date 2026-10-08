const rupiahFormat = new Intl.NumberFormat('id-ID');

export function initQrCart() {
    const form = document.getElementById('order-form');
    if (!form) return;

    const lines = [...form.querySelectorAll('[data-line]')];
    const countEl = document.querySelector('[data-cart-count]');
    const totalEl = document.querySelector('[data-cart-total]');
    const submit = document.querySelector('[data-cart-submit]');

    const clamp = (input) => {
        let value = parseInt(input.value, 10);
        const max = Number(input.max);
        if (Number.isNaN(value) || value < 0) value = 0;
        if (value > max) value = max;
        input.value = value;
        return value;
    };

    const refresh = () => {
        let count = 0;
        let total = 0;

        lines.forEach((line) => {
            const input = line.querySelector('[data-qty]');
            const quantity = clamp(input);
            count += quantity;
            total += quantity * Number(line.dataset.price);
            line.querySelector('[data-step="-1"]').disabled = quantity <= 0;
            line.querySelector('[data-step="1"]').disabled = quantity >= Number(input.max);
            line.querySelector('[data-notes]').hidden = quantity === 0;
        });

        countEl.textContent = count;
        totalEl.textContent = `Rp ${rupiahFormat.format(total)}`;
        submit.disabled = count === 0;
    };

    form.addEventListener('click', (event) => {
        const button = event.target.closest('[data-step]');
        if (!button) return;
        const input = button.closest('[data-line]').querySelector('[data-qty]');
        input.value = (parseInt(input.value, 10) || 0) + Number(button.dataset.step);
        refresh();
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-qty]')) refresh();
    });

    refresh();
}
