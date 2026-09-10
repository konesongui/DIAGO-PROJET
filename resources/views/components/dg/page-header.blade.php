@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => null])

<div {{ $attributes->merge(['class' => 'dg-page-header']) }}>
    <div>
        @if($back)
            <a href="{{ $back }}" class="dg-back-link"><i class="bi bi-arrow-left"></i>{{ $backLabel ?? __('Back') }}</a>
        @endif
        <h1>{{ $title }}</h1>
        @if($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="dg-page-header__actions">{{ $actions }}</div>
    @endisset
</div>
