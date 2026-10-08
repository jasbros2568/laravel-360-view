{{-- 360° viewer. Without frames it renders the slot, so you can pass a fallback (e.g. a normal product photo). --}}
@if ($frames)
    <div {{ $attributes->class('view360')->merge(['style' => 'aspect-ratio: '.$aspect]) }}
         data-view360="{{ json_encode($options, JSON_UNESCAPED_SLASHES) }}"
         role="slider" tabindex="0" aria-label="{{ $alt }}" aria-orientation="horizontal"
         aria-valuemin="1" aria-valuemax="{{ count($frames) }}" aria-valuenow="{{ $options['start'] + 1 }}"
         aria-valuetext="View {{ $options['start'] + 1 }} of {{ count($frames) }}">
        <img class="view360__frame" src="{{ $frames[$options['start']] }}" alt="" draggable="false" decoding="async">
        <div class="view360__progress" hidden><span></span></div>
        @if ($hint)
            <div class="view360__hint" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12c0-2.2 4-4 9-4s9 1.8 9 4-4 4-9 4"/><path d="m9 13 3 3-3 3"/></svg>
                <span>{{ $hint }}</span>
            </div>
        @endif
    </div>

    @once
        @switch($assetMode())
            @case('published')
                <link rel="stylesheet" href="{{ $assetUrl('view360.css') }}">
                <script src="{{ $assetUrl('view360.js') }}" defer></script>
                @break
            @case('inline')
                <style>{!! $assetContents('view360.css') !!}</style>
                <script>{!! $assetContents('view360.js') !!}</script>
                @break
        @endswitch
    @endonce
@else
    {{ $slot }}
@endif
