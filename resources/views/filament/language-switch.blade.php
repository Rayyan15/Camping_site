@php
    $currentLocale = app()->getLocale();
@endphp
<div x-data="{ open: false }" class="relative flex items-center" x-on:click.outside="open = false" style="position: relative; display: inline-flex;">
    <button
        type="button"
        x-on:click="open = ! open"
        class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-full border border-gray-300 dark:border-gray-700 bg-white/80 dark:bg-gray-800/80 px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-200 shadow-sm transition hover:bg-gray-50 dark:hover:bg-gray-700"
        style="height: 32px; font-size: 12px; line-height: 1;"
        aria-label="Ganti bahasa"
    >
        <span style="display: inline-flex; align-items: center;">
            <svg style="width: 15px; height: 15px; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 2a14.5 14.5 0 0 0 0 20M12 2a14.5 14.5 0 0 1 0 20M2 12h20"/>
            </svg>
        </span>
        <span>{{ $currentLocale === 'en' ? 'EN' : 'ID' }}</span>
        <svg style="width: 12px; height: 12px; flex-shrink: 0; transition: transform 0.2s;" :style="open ? 'transform: rotate(180deg);' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        style="position: absolute; right: 0; top: 100%; margin-top: 8px; width: 150px; z-index: 9999; border-radius: 12px; border: 1px solid rgba(0,0,0,0.1); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); padding: 4px;"
        class="bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800"
    >
        <a
            href="{{ route('lang.switch', 'id') }}"
            style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-radius: 8px; font-size: 12px; font-weight: 500; text-decoration: none;"
            class="{{ $currentLocale === 'id' ? 'bg-forest-800 text-white font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}"
        >
            <span>Indonesia (ID)</span>
            @if($currentLocale === 'id')
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
            @endif
        </a>

        <a
            href="{{ route('lang.switch', 'en') }}"
            style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-radius: 8px; font-size: 12px; font-weight: 500; text-decoration: none;"
            class="{{ $currentLocale === 'en' ? 'bg-forest-800 text-white font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}"
        >
            <span>English (EN)</span>
            @if($currentLocale === 'en')
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
            @endif
        </a>
    </div>
</div>
