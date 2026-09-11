{{--
    Carte de contenu.
    - icon + color (blue, green, purple…) : l'icône du titre s'affiche dans une pastille de cette couleur.
    - icon sans color : icône simple, comme sur l'écran Rapports de la maquette.
--}}
@props(['title' => null, 'icon' => null, 'meta' => null, 'color' => null])

<div {{ $attributes->merge(['class' => 'dg-card' . ($color ? " dg-tone-{$color}" : '')]) }}>
    @if($title || $meta || isset($actions))
        <div class="dg-card__header">
            @if($title)
                <h2 class="dg-card__title">
                    @if($icon && $color)
                        <span class="dg-tile dg-tile--sm"><i class="bi {{ $icon }}"></i></span>
                    @elseif($icon)
                        <i class="bi {{ $icon }}"></i>
                    @endif
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
