{{--
    Indicateur chiffré.
    - tone : « danger » colore la pastille d'icône en rouge (ex. factures impayées).
    - accent : navy | success | yellow ajoute le liseré gauche des cartes de comptes (Trésorerie).
    - trend / trendDirection (up|down) / trendTone (success|danger) : variation affichée sous la valeur.
--}}
@props([
    'label',
    'value',
    'icon' => null,
    'tone' => null,
    'accent' => null,
    'trend' => null,
    'trendDirection' => 'up',
    'trendTone' => null,
    'hint' => null,
])

@php
    $trendTone ??= $trendDirection === 'up' ? 'success' : 'danger';
    $classes = 'dg-kpi' . ($accent ? " dg-kpi--accent dg-kpi--accent-{$accent}" : '');
    $iconClasses = 'dg-kpi__icon' . ($tone === 'danger' ? ' dg-kpi__icon--danger' : '') . ($accent ? ' dg-kpi__icon--plain' : '');
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    <div class="dg-kpi__head">
        <span class="dg-kpi__label">{{ $label }}</span>
        @if($icon)
            <span class="{{ $iconClasses }}"><i class="bi {{ $icon }}"></i></span>
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
