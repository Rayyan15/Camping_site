export function initGallery() {
    document.querySelectorAll('[data-gallery]').forEach((gallery) => {
        const main = gallery.querySelector('[data-gallery-main]');
        const thumbs = gallery.querySelectorAll('[data-gallery-thumb]');

        thumbs.forEach((thumb) =>
            thumb.addEventListener('click', (event) => {
                event.preventDefault();
                main.src = thumb.href;
                main.alt = thumb.dataset.alt;
                thumbs.forEach((other) => (other === thumb ? other.setAttribute('aria-current', 'true') : other.removeAttribute('aria-current')));
            }),
        );
    });
}
