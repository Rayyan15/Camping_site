{{-- Other tent types: real records, each card links to its own page and keeps the stay the visitor already chose. --}}
@if($otherTypes->isNotEmpty())
    @php($stayParams = array_filter(request()->only(['check_in', 'check_out', 'guests'])))
    <section aria-labelledby="judul-tipe-lain" class="mt-24 lg:mt-32">
        <div class="mx-auto max-w-6xl px-4 sm:px-6" data-reveal>
            <p class="font-display text-xl italic text-ember-dark">Tipe lain</p>
            <h2 id="judul-tipe-lain" class="font-display mt-2 text-4xl font-semibold leading-[1.05] sm:text-5xl">Masih membandingkan?</h2>
        </div>

        <div role="region" aria-label="Tipe tenda lain, geser ke samping" tabindex="0" class="tent-rail mt-8 overflow-x-auto pb-6">
            <ul class="mx-auto flex w-max gap-4 px-4 sm:px-6 lg:max-w-6xl">
                @foreach($otherTypes as $type)
                    @php($photo = $type->photos->sortBy('sort_order')->first())
                    <li class="relative isolate flex h-72 w-64 shrink-0 overflow-hidden rounded-3xl bg-forest-800 text-cream sm:w-72">
                        @if($photo)
                            <img src="{{ $photo->url }}" alt="Tenda tipe {{ $type->name }}" width="1600" height="1067" loading="lazy" decoding="async" class="absolute inset-0 -z-10 size-full object-cover">
                        @endif
                        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-forest-950 via-forest-950/55 to-transparent" aria-hidden="true"></div>
                        <div class="mt-auto w-full p-5">
                            <h3 class="font-display text-3xl font-semibold leading-none">
                                <a href="{{ route('tenda.show', ['slug' => $type->slug] + $stayParams) }}" class="after:absolute after:inset-0 after:content-['']">{{ $type->name }}</a>
                            </h3>
                            <p class="mt-2 text-sm font-semibold text-cream/90">Hingga {{ $type->capacity }} orang</p>
                            <p class="mt-1 text-sm font-semibold text-cream/90">Mulai Rp {{ number_format(min($type->base_price_weekday, $type->base_price_weekend), 0, ',', '.') }} per malam</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
