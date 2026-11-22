@php
    $slides = [
        [
            'name' => 'Medicines',
            'theme' => 'sage',
            'headline' => ['A little care.', 'A lot of happiness.'],
            'description' => 'Medicines and everyday health essentials for the people who make your world.',
            'image' => 'family-everyday-care.webp',
            'label' => 'Care for the whole family',
            'action' => 'Browse medicines',
            'href' => route('catalog.index', ['sort' => 'newest']),
        ],
        [
            'name' => 'Prescription orders',
            'theme' => 'warm',
            'headline' => ['Ready for', 'their every day.'],
            'description' => 'Upload your prescription at checkout. The pharmacy reviews it before preparing your order.',
            'image' => 'family-morning-joy.webp',
            'label' => 'Little routines. Big love.',
            'action' => 'Browse pharmacy essentials',
            'href' => route('catalog.top-products'),
        ],
        [
            'name' => 'Branches',
            'theme' => 'sage',
            'headline' => ['More moments.', 'Closer together.'],
            'description' => 'Check branch availability and opening hours before choosing delivery or pickup.',
            'image' => 'family-garden-moments.webp',
            'label' => 'More happy moments',
            'action' => 'Find a branch',
            'href' => route('catalog.stores'),
        ],
        [
            'name' => 'Family wellbeing',
            'theme' => 'warm',
            'headline' => ['End the day', 'feeling cared for.'],
            'description' => 'Make room for the little moments. Find everyday wellness essentials for your family.',
            'image' => 'family-bedtime-comfort.webp',
            'label' => 'Comfort in every moment',
            'action' => 'Shop family essentials',
            'href' => route('catalog.index'),
        ],
    ];
@endphp

<section class="home-hero" aria-label="Pharmacy highlights" aria-roledescription="carousel" data-home-hero
    x-data="{
        active: 0,
        count: {{ count($slides) }},
        touchStart: null,
        show(index) { this.active = (index + this.count) % this.count; },
        swipe(event) {
            if (!this.touchStart) { return; }
            const deltaX = event.changedTouches[0].clientX - this.touchStart.x;
            const deltaY = event.changedTouches[0].clientY - this.touchStart.y;
            if (Math.abs(deltaX) > 50 && Math.abs(deltaX) > Math.abs(deltaY)) { this.show(this.active + (deltaX < 0 ? 1 : -1)); }
            this.touchStart = null;
        }
    }">
    <div class="home-hero-stage"
        @touchstart.passive="touchStart = { x: $event.changedTouches[0].clientX, y: $event.changedTouches[0].clientY }"
        @touchend.passive="swipe($event)" @touchcancel.passive="touchStart = null">
        @foreach ($slides as $index => $slide)
            <div id="home-hero-slide-{{ $index + 1 }}" class="home-hero-slide home-hero-slide-{{ $slide['theme'] }}"
                role="group" aria-roledescription="slide" aria-label="{{ $index + 1 }} of {{ count($slides) }}: {{ $slide['name'] }}"
                x-show="active === {{ $index }}" x-transition.opacity.duration.400ms
                :inert="active !== {{ $index }}" :aria-hidden="(active !== {{ $index }}).toString()"
                aria-hidden="{{ $index === 0 ? 'false' : 'true' }}" @if ($index > 0) x-cloak inert @endif>
                @if ($slide['image'])
                    <div class="home-hero-visual">
                        <img src="{{ asset('images/banners/'.$slide['image']) }}" alt="" width="1536" height="1024"
                            loading="{{ $index === 0 ? 'eager' : 'lazy' }}" @if ($index === 0) fetchpriority="high" @endif
                            decoding="async" draggable="false" class="home-hero-image">
                        <span class="home-hero-label">{{ $slide['label'] }}</span>
                    </div>
                @endif
                <div class="home-hero-copy">
                    <p class="home-hero-eyebrow">Everyday Pharmacy <span aria-hidden="true">/</span> Care begins with family</p>
                    <h2>@foreach ($slide['headline'] as $line)<span>{{ $line }}</span>@endforeach</h2>
                    <p>{{ $slide['description'] }}</p>
                    <a href="{{ $slide['href'] }}" class="home-hero-action">{{ $slide['action'] }} <x-returns.icon name="chevron-right" class="size-4" /></a>
                </div>
            </div>
        @endforeach
    </div>

    <div class="home-hero-controls" role="group" aria-label="Hero carousel controls"
        @keydown.arrow-right.prevent="show(active + 1)" @keydown.arrow-left.prevent="show(active - 1)"
        @keydown.home.prevent="show(0)" @keydown.end.prevent="show(count - 1)">
        <div class="home-hero-selectors">
            @foreach ($slides as $index => $slide)
                <button type="button" class="home-hero-selector" @click="show({{ $index }})"
                    aria-label="Show slide {{ $index + 1 }}: {{ $slide['name'] }}" aria-controls="home-hero-slide-{{ $index + 1 }}"
                    :aria-pressed="(active === {{ $index }}).toString()" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                    <span class="home-hero-index" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="home-hero-selector-label">{{ $slide['name'] }}</span>
                </button>
            @endforeach
        </div>
        <div class="home-hero-arrows">
            <button type="button" class="home-hero-arrow" aria-label="Previous slide" @click="show(active - 1)"><x-returns.icon name="chevron-right" class="size-4 rotate-180" /></button>
            <button type="button" class="home-hero-arrow" aria-label="Next slide" @click="show(active + 1)"><x-returns.icon name="chevron-right" class="size-4" /></button>
        </div>
    </div>
    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" x-text="'Slide ' + (active + 1) + ' of ' + count">Slide 1 of {{ count($slides) }}</p>
</section>
