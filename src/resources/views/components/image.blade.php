@if (filled($src))
<section class="{{ cms_class('section') }}">
    <div class="{{ cms_class('container') }} {{ cms_class('container') }}--{{ $width }}">
        <figure class="cms-figure">
            @if (filled($link ?? null))<a href="{{ url($link) }}">@endif
            <img src="{{ $src }}" alt="{{ $alt ?? '' }}" loading="lazy">
            @if (filled($link ?? null))</a>@endif
            @if (filled($caption ?? null))
                <figcaption>{{ $caption }}</figcaption>
            @endif
        </figure>
    </div>
</section>
@endif
