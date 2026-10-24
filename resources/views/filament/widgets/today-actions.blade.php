<x-filament-widgets::widget>
    @include('filament.widgets.partials.dashboard-style')

    <div class="hh-dash">
        <header class="hh-card hh-head">
            <span class="hh-tile">
                <x-filament::icon icon="heroicon-o-squares-2x2" class="hh-tile-icon" />
            </span>
            <div class="hh-head-text">
                <h1 class="hh-head-title">{{ __('Dasbor') }}</h1>
                <p class="hh-muted">{{ $today }}</p>
            </div>
            @if($calendarUrl)
                <x-filament::button tag="a" :href="$calendarUrl" color="gray" icon="heroicon-m-calendar-days">
                    {{ __('Kalender okupansi') }}
                </x-filament::button>
            @endif
        </header>

        @if(count($cards) > 0)
            <div class="hh-cards">
                @foreach($cards as $card)
                    <a href="{{ $card['url'] }}" @class(['hh-card', 'hh-stat', 'is-alert' => $card['needsAction']])>
                        <span class="hh-tile hh-tile-lg">
                            <x-filament::icon :icon="$card['icon']" class="hh-tile-icon" />
                        </span>
                        <span class="hh-stat-text">
                            <span class="hh-stat-label">{{ __($card['label']) }}</span>
                            <span class="hh-stat-value">{{ $card['count'] }}</span>
                            <span class="hh-muted hh-small">{{ __($card['caption']) }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
