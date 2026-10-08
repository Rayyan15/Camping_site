const POLL_INTERVAL_MS = 5000;
const ACTIVE_DOT = 'border-forest-800 bg-forest-800 text-cream';
const IDLE_DOT = 'border-sand bg-cream text-ink-soft';
const DOT_BASE = 'grid size-8 shrink-0 place-items-center rounded-full border-2 text-sm font-bold';
const STATE_LABEL = { done: '(selesai)', current: '(saat ini)', todo: '(belum)' };

export function initQrTrack() {
    const hint = document.getElementById('status-hint');
    const stepsRoot = document.querySelector('[data-steps]');
    if (!hint || !stepsRoot) return;

    const hints = JSON.parse(stepsRoot.dataset.hints);
    const steps = [...stepsRoot.querySelectorAll('[data-step-item]')];
    const payment = document.querySelector('[data-payment]');
    const finalStep = steps.length - 1;

    const paint = (data) => {
        steps.forEach((item, index) => {
            const state = index < data.step ? 'done' : index === data.step ? 'current' : 'todo';
            const active = state !== 'todo';
            item.dataset.state = state;
            item.querySelector('[data-dot]').className = `${DOT_BASE} ${active ? ACTIVE_DOT : IDLE_DOT}`;
            item.querySelector('[data-label]').className = `font-semibold ${active ? 'text-forest-900' : 'text-ink-soft'}`;
            item.querySelector('[data-sr]').textContent = STATE_LABEL[state];
        });

        hint.textContent = hints[data.status];

        if (data.billed_to_booking) {
            payment.textContent = 'Ditagihkan ke booking Anda.';
        } else {
            payment.textContent = data.paid ? 'Sudah dibayar.' : 'Belum dibayar. Bayar ke kasir.';
        }
    };

    const timer = setInterval(async () => {
        try {
            const response = await fetch(window.location.href, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const data = await response.json();
            paint(data);
            if (data.step >= finalStep) clearInterval(timer);
        } catch {
            // A failed poll is retried on the next tick.
        }
    }, POLL_INTERVAL_MS);
}
