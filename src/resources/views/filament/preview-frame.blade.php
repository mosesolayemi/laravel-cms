{{--
    The preview modal: the rendered page in a frame, sized like a desktop,
    tablet or phone, with a way out to a full tab.

    Alpine style bindings use objects, which merge with the inline style;
    a string would replace it.

    Styled inline on purpose: Filament's stylesheet only carries the utility
    classes Filament itself uses, so Tailwind classes in a package view would
    silently do nothing.
--}}
@php
    $devices = [
        'desktop' => ['label' => 'Desktop', 'width' => '100%'],
        'tablet' => ['label' => 'Tablet', 'width' => '768px'],
        'mobile' => ['label' => 'Mobile', 'width' => '390px'],
    ];
@endphp
<div x-data="{ device: 'desktop', widths: @js(collect($devices)->map->width) }" style="display:flex;flex-direction:column;gap:12px;">
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
        <div role="group" aria-label="Preview size" style="display:inline-flex;border:1px solid rgba(128,128,128,.35);border-radius:8px;overflow:hidden;">
            @foreach ($devices as $key => $device)
                <button type="button"
                        x-on:click="device = '{{ $key }}'"
                        x-bind:style="device === '{{ $key }}' ? { background: 'rgba(128,128,128,.25)', fontWeight: 600 } : { background: 'transparent', fontWeight: 400 }"
                        x-bind:aria-pressed="(device === '{{ $key }}').toString()"
                        style="padding:6px 14px;font-size:13px;line-height:1.4;background:transparent;border:0;cursor:pointer;color:inherit;">
                    {{ $device['label'] }}
                </button>
            @endforeach
        </div>

        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;font-size:13px;opacity:.85;">
            <span>Unsaved changes included &middot; link lasts {{ $ttl }} minutes</span>
            <a href="{{ $url }}" target="_blank" rel="noopener" style="font-weight:600;text-decoration:underline;color:inherit;">
                Open in new tab &nearr;
            </a>
        </div>
    </div>

    <div style="display:flex;justify-content:center;width:100%;border-radius:10px;background:rgba(128,128,128,.12);padding:0;overflow:hidden;">
        <iframe src="{{ $url }}"
                title="Preview"
                x-bind:style="{ width: widths[device] }"
                style="display:block;width:100%;max-width:100%;height:75vh;min-height:420px;border:0;background:#fff;transition:width .2s ease;"></iframe>
    </div>
</div>
