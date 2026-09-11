{{--
    Indicateur chiffré.
    - color : blue | green | purple | orange | red | teal | cyan | pink | indigo | yellow | navy (défaut)
      colore la pastille d'icône, le liseré et le lien de détail.
    - tone : « danger » équivaut à color="red" (compatibilité).
    - accent : navy | success | yellow ; liseré gauche des cartes de comptes (Trésorerie) au lieu du liseré supérieur.
    - trend / trendDirection (up|down) / trendTone (success|danger) : variation affichée sous la valeur.
--}}
@props([
    'label',
    'value',
    'icon' => null,
    'color' => null,
    'tone' => null,
    'accent' => null,
    'trend' => null,
    'trendDirection' => 'up',
    'trendTone' => null,
    'hint' => null,
])

@php
    $trendTone ??= $trendDirection === 'up' ? 'success' : 'danger';
    $color ??= $tone === 'danger' ? 'red' : (['success' => 'green', 'yellow' => 'yellow'][$accent] ?? 'navy');
    $classes = "dg-kpi dg-tone-{$color}" . ($accent ? ' dg-kpi--accent' : '');
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    <div class="dg-kpi__head">
        <span class="dg-kpi__label">{{ $label }}</span>
        @if($icon)
            <span class="dg-kpi__icon"><i class="bi {{ $icon }}"></i></span>
        @endif
    </div>
    <div class="dg-kpi__value">{{ $value }}</div>
    @if($trend || $hint || $slot->isNotEmpty())
        <div class="dg-kpi__foot">
            @if($trend)
                <span class="dg-trend dg-trend--{{ $trendTone === 'success' ? 'up' : 'down' }}">
                    <i class="bi {{ $trendDirection === 'up' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>{{ $trend }}
                </span>
            @endif
            @if($hint)
                <span>{{ $hint }}</span>
            @endif
            {{ $slot }}
        </div>
    @endif
</div>
