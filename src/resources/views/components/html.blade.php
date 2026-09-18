@if (filled($html ?? null))
    @if ($contained ?? true)
        <div class="{{ cms_class('container') }}">{!! $html !!}</div>
    @else
        {!! $html !!}
    @endif
@endif
