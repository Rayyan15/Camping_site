const pad = (n) => String(n).padStart(2, '0');

export function initCheckoutCountdown() {
    const el = document.getElementById('countdown');
    if (!el) return;

    const deadline = Date.now() + Number(el.dataset.remaining) * 1000;

    const tick = () => {
        const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
        el.textContent = `${pad(Math.floor(left / 60))}:${pad(left % 60)}`;
        if (left === 0) {
            window.location.reload();
            return;
        }
        setTimeout(tick, 1000);
    };

    tick();
}
