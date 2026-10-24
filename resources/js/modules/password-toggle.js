import { t } from './i18n';

// Swaps the input type only; the button keeps its size so nothing shifts when the icon changes.
export function initPasswordToggle() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input) return;

        button.hidden = false;
        button.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(reveal));
            button.setAttribute('aria-label', reveal ? t('Sembunyikan kata sandi') : t('Tampilkan kata sandi'));
            button.querySelector('[data-icon="show"]').classList.toggle('hidden', reveal);
            button.querySelector('[data-icon="hide"]').classList.toggle('hidden', !reveal);
        });
    });
}
