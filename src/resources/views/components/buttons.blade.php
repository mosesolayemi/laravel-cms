@php($align = $align ?? 'left')
@if (! empty($buttons))
<section class="{{ cms_class('section') }}">
    <div class="{{ cms_class('container') }}">
        <div class="cms-buttons cms-align-{{ $align }}">
            @foreach ($buttons as $button)
                @continue(blank($button['label'] ?? null) || blank($button['url'] ?? null))
                @php($style = $button['style'] ?? 'primary')
                <a href="{{ url($button['url']) }}"
                   class="{{ cms_class('button') }} {{ $style === 'primary' ? '' : cms_class('button') . '--' . $style }}"
                   @if (! empty($button['new_tab'])) target="_blank" rel="noopener" @endif>{{ $button['label'] }}</a>
            @endforeach
        </div>
    </div>
</section>
@endif
