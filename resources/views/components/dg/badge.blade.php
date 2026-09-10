{{-- Badge de statut : tone = success | warning | danger | neutral --}}
@props(['tone' => 'neutral', 'icon' => null])

<span {{ $attributes->merge(['class' => "dg-badge dg-badge--{$tone}"]) }}>
    @if($icon)<i class="bi {{ $icon }}"></i>@endif
    {{ $slot }}
</span>
