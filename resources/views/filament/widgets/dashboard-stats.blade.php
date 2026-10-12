<x-filament-widgets::widget>
    @include('filament.widgets.partials.dashboard-style')

    <section class="hh-card hh-strip" aria-label="Kondisi malam ini">
        @foreach($items as $item)
            <div class="hh-mini">
                <x-filament::icon :icon="$item['icon']" class="hh-mini-icon" />
                <div class="hh-mini-text">
                    <p class="hh-mini-value">{{ $item['value'] }}</p>
                    <p class="hh-muted hh-xsmall">{{ $item['label'] }}</p>
                </div>
            </div>
        @endforeach
    </section>
</x-filament-widgets::widget>
