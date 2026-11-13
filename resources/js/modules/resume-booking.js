const STORAGE_KEY = 'halimun.pendingCheckout';

function read() {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY));
        return saved && saved.until > Date.now() ? saved : null;
    } catch {
        return null;
    }
}

function write(value) {
    try {
        if (value) localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
        else localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Private mode or blocked storage: the booking code on the checkout page is the fallback.
    }
}

// Guests who closed or refreshed checkout re-booked and found their own held tent "full"; this points them back.
export function initResumeBooking() {
    const save = document.querySelector('[data-resume-save]');
    const clear = document.querySelector('[data-resume-clear]');

    if (save) {
        write({ url: save.dataset.resumeUrl, code: save.dataset.resumeCode, until: Number(save.dataset.resumeUntil) });
        return;
    }
    if (clear && read()?.code === clear.dataset.resumeClear) write(null);

    const pending = read();
    const banner = document.querySelector('[data-resume-banner]');
    if (!pending || !banner) return;

    banner.querySelector('[data-resume-code]').textContent = pending.code;
    banner.querySelector('[data-resume-link]').href = pending.url;
    banner.hidden = false;
}
