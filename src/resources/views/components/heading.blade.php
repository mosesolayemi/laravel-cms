@php
    $level = $level ?? 'h2';
    $level = in_array($level, ['h1', 'h2', 'h3', 'h4'], true) ? $level : 'h2';
    $align = $align ?? 'left';
@endphp
<section class="{{ cms_class('section') }} cms-heading cms-align-{{ $align }}">
    <div class="{{ cms_class('container') }}">
        <{{ $level }} class="{{ cms_class('heading') }}">{{ $heading ?? '' }}</{{ $level }}>
        @if (filled($subheading ?? null))
            <p class="cms-heading__sub">{{ $subheading }}</p>
        @endif
    </div>
</section>
