@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;
    use Illuminate\Support\Facades\Vite;

    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    {{-- Scoped to the login frame only, so the rest of the panel keeps Filament's own theme. --}}
    <style>
        @font-face {
            font-family: "Fraunces";
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url("{{ Vite::asset('resources/fonts/fraunces-latin-wght-normal.woff2') }}") format("woff2");
        }

        .hh-login {
            --hh-surface: var(--hh-cream);
            --hh-text: var(--hh-ink);
            --hh-muted: var(--hh-forest-700);
            --hh-rule: var(--hh-ember);

            display: grid;
            min-height: 100dvh;
            background: var(--hh-surface);
            color: var(--hh-text);
        }

        .dark .hh-login {
            --hh-surface: var(--hh-forest-950);
            --hh-text: var(--hh-cream);
            --hh-muted: var(--hh-forest-300);
            --hh-rule: var(--hh-ember-light);
        }

        .hh-login__scene {
            position: relative;
            min-height: 15rem;
            overflow: hidden;
            background: var(--hh-forest-950);
        }

        .hh-login__scene img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 60%;
        }

        /* A solid plate, not a fade, so the caption keeps its contrast over any part of the photo. */
        .hh-login__caption {
            position: absolute;
            left: 1rem;
            right: 1rem;
            bottom: 1rem;
            max-width: 26rem;
            padding: 1rem 1.25rem;
            border-radius: 0.375rem;
            background: rgb(20 38 27 / 0.88);
            color: var(--hh-cream);
        }

        .hh-login__place {
            margin: 0;
            font-family: "Fraunces", Georgia, serif;
            font-size: 1.5rem;
            line-height: 1.15;
            letter-spacing: -0.01em;
        }

        .hh-login__note {
            margin: 0.375rem 0 0;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--hh-forest-300);
        }

        .hh-login__panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2rem 1rem 2.5rem;
        }

        .hh-login__form {
            width: 100%;
            max-width: 24rem;
            margin-inline: auto;
        }

        .hh-login__form .fi-simple-header-heading {
            font-family: "Fraunces", Georgia, serif;
            font-weight: 500;
            font-size: 1.875rem;
            letter-spacing: -0.015em;
            color: var(--hh-text);
        }

        /* The ember rule under the heading is the one accent on this screen. */
        .hh-login__form .fi-simple-header-heading::after {
            content: "";
            display: block;
            width: 2.5rem;
            height: 3px;
            margin: 0.75rem auto 0;
            background: var(--hh-rule);
        }

        .hh-login__form .fi-simple-header-subheading {
            color: var(--hh-muted);
        }

        /* Ember is the site's booking CTA colour; white on it is 5.58:1, where white on the panel emerald fails AA. */
        .hh-login__form .fi-ac-btn-action.fi-btn,
        .hh-login__form button[type="submit"].fi-btn {
            min-height: 2.75rem;
            background: var(--hh-ember);
            color: #fff;
        }

        .hh-login__form button[type="submit"].fi-btn:hover {
            background: var(--hh-ember-dark);
        }

        .hh-login__form button[type="submit"].fi-btn:focus-visible {
            outline: 2px solid var(--hh-text);
            outline-offset: 2px;
        }

        /* Thumb-sized targets at 360px: inputs, the password reveal and the remember-me row. */
        .hh-login__form .fi-input-wrp,
        .hh-login__form .fi-input-wrp .fi-input {
            min-height: 2.75rem;
        }

        .hh-login__form .fi-input-wrp-suffix .fi-icon-btn {
            min-width: 2.75rem;
            min-height: 2.75rem;
        }

        .hh-login__form label:has(.fi-checkbox-input) {
            min-height: 2.75rem;
            align-items: center;
        }

        .hh-login__foot {
            margin: 2rem auto 0;
            max-width: 24rem;
            font-size: 0.8125rem;
            line-height: 1.5;
            text-align: center;
            color: var(--hh-muted);
        }

        .hh-login__foot a {
            display: inline-block;
            min-height: 2.75rem;
            padding-block: 0.75rem;
            color: inherit;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .hh-login__foot a:focus-visible {
            outline: 2px solid var(--hh-rule);
            outline-offset: 2px;
            border-radius: 2px;
        }

        @media (min-width: 64rem) {
            .hh-login {
                grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
            }

            .hh-login__scene {
                min-height: 100dvh;
            }

            .hh-login__caption {
                left: 2.5rem;
                bottom: 2.5rem;
                padding: 1.5rem 1.75rem;
            }

            .hh-login__place {
                font-size: 2.25rem;
            }

            .hh-login__panel {
                padding: 3rem 2.5rem;
            }
        }

        @media (prefers-reduced-motion: no-preference) {
            .hh-login__scene img {
                animation: hh-settle 1.2s ease-out both;
            }

            @keyframes hh-settle {
                from { transform: scale(1.04); }
                to { transform: scale(1); }
            }
        }
    </style>

    <div class="fi-simple-layout hh-login">
        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        <aside class="hh-login__scene" aria-label="{{ config('site.name') }}">
            <img
                src="{{ asset('images/tent-trees-1200.webp') }}"
                srcset="{{ asset('images/tent-trees-480.webp') }} 480w, {{ asset('images/tent-trees-800.webp') }} 800w, {{ asset('images/tent-trees-1200.webp') }} 1200w"
                sizes="(min-width: 64rem) 58vw, 100vw"
                alt=""
                decoding="async"
            >
            <div class="hh-login__caption">
                <p class="hh-login__place">{{ config('site.name') }}</p>
                <p class="hh-login__note">Cisarua, Bogor. Ruang kerja untuk reservasi, dapur, dan laporan harian.</p>
            </div>
        </aside>

        <div class="hh-login__panel">
            <main id="fi-main-content" tabindex="-1" class="hh-login__form">
                {{ $slot }}
            </main>

            <p class="hh-login__foot">
                Tamu yang ingin memesan tenda bisa kembali ke
                <a href="{{ url('/') }}">halaman utama</a>.
            </p>
        </div>

        {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}

        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
