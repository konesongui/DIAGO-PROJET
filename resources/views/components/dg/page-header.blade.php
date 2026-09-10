@props(['title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'dg-page-header']) }}>
    <div>
        <h1>{{ $title }}</h1>
        @if($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="dg-page-header__actions">{{ $actions }}</div>
    @endisset
</div>
