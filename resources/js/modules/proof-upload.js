import { t } from './i18n';

const COMPRESS_ABOVE_BYTES = 500 * 1024;
const MAX_EDGE_PX = 1600;
const JPEG_QUALITY = 0.8;
const REQUEST_TIMEOUT_MS = 90000;

// Phone photos fail on weak signal because they take too long to send; 1600 px keeps a receipt readable.
async function shrink(file) {
    if (!file.type.startsWith('image/') || file.size <= COMPRESS_ABOVE_BYTES) return file;

    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, MAX_EDGE_PX / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));
        if (!blob || blob.size >= file.size) return file;

        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
    } catch {
        // The browser cannot decode this format; the server still accepts or explains the original.
        return file;
    }
}

function failureMessage(status, body) {
    if (status === 422) return t(body?.errors?.proof?.[0] ?? body?.message ?? 'Bukti belum bisa dikirim. Coba lagi.');
    if (status === 413) return t('File terlalu besar. Maksimal 10 MB.');
    if (status === 419) return t('Halaman sudah terlalu lama dibuka. Muat ulang halaman, lalu kirim lagi.');
    if (status === 429) return t('Terlalu banyak percobaan. Tunggu satu menit, lalu kirim lagi.');

    return t('Bukti belum bisa dikirim. Coba lagi.');
}

function initForm(form) {
    const input = form.querySelector('input[type="file"]');
    const button = form.querySelector('button[type="submit"]');
    const error = form.querySelector('[data-proof-error]');
    const idleLabel = button.textContent;
    let sending = false;

    const finish = (message) => {
        sending = false;
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.textContent = idleLabel;
        error.textContent = message;
        error.hidden = false;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (sending) return;

        sending = true;
        error.hidden = true;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = t('Mengirim bukti...');

        const file = await shrink(input.files[0]);
        if (file.size > Number(form.dataset.maxBytes)) {
            finish(t('File terlalu besar. Maksimal 10 MB.'));
            return;
        }

        const body = new FormData(form);
        body.set(input.name, file, file.name);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: AbortSignal.timeout?.(REQUEST_TIMEOUT_MS),
            });
            const data = await response.json().catch(() => null);

            if (response.ok && data?.redirect) {
                window.location.assign(data.redirect);
                return;
            }

            finish(failureMessage(response.status, data));
        } catch {
            finish(t('Koneksi terputus saat mengirim. Periksa sinyal, lalu tekan kirim lagi.'));
        }
    });
}

export function initProofUpload() {
    document.querySelectorAll('[data-proof-form]').forEach(initForm);
}
