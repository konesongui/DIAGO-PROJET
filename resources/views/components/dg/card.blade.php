@props(['title' => null, 'icon' => null, 'meta' => null])

<div {{ $attributes->merge(['class' => 'dg-card']) }}>
    @if($title || $meta || isset($actions))
        <div class="dg-card__header">
            @if($title)
                <h2 class="dg-card__title">
                    @if($icon)<i class="bi {{ $icon }}"></i>@endif
                    {{ $title }}
                </h2>
            @endif
            @if($meta)
                <span class="dg-card__meta">{{ $meta }}</span>
            @endif
            @isset($actions)
                <div class="d-flex gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    {{ $slot }}
</div>
