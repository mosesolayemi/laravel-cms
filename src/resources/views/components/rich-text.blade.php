@if (filled($html))
<section class="{{ cms_class('section') }}">
    <div class="{{ cms_class('container') }} {{ cms_class('container') }}--{{ $width }}">
        <div class="{{ cms_class('prose') }}">
            {!! $html !!}
        </div>
    </div>
</section>
@endif
