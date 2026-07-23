@props(['slug' => null, 'title' => null, 'fallback' => false])

@php
    $case = $slug ? \App\Enums\NetworkSourcesEnum::tryFrom($slug) : null;
@endphp

@if($case)
    <svg
        viewBox="0 0 24 24"
        fill="{{ $case->brandColor() }}"
        role="img"
        @if($title) aria-label="{{ $title }}" title="{{ $title }}" @else aria-hidden="true" @endif
        {{ $attributes->class('inline-block') }}
    >
        <path d="{{ $case->brandIconPath() }}" />
    </svg>
@elseif($fallback)
    <svg
        viewBox="0 0 24 24"
        fill="currentColor"
        role="img"
        @if($title) aria-label="{{ $title }}" title="{{ $title }}" @else aria-hidden="true" @endif
        {{ $attributes->class('inline-block text-gray-400 dark:text-gray-500') }}
    >
        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 1.5a10.5 10.5 0 1 0 0 21 10.5 10.5 0 0 0 0-21ZM6.262 6.072a8.25 8.25 0 1 0 10.562-.766 4.5 4.5 0 0 1-1.318 1.357L14.25 7.5l.165.33a.809.809 0 0 1-1.086 1.085l-.604-.302a1.125 1.125 0 0 0-1.298.21l-.132.132c-.439.439-.439 1.151 0 1.59l.296.297c.256.256.622.374.98.313l1.17-.195c.323-.054.654.036.905.245l1.33 1.108c.32.267.46.694.358 1.1a8.7 8.7 0 0 1-2.288 4.04l-.723.724a1.125 1.125 0 0 1-1.298.21l-.153-.076a1.125 1.125 0 0 1-.622-1.006v-1.089c0-.298-.119-.585-.33-.796l-1.347-1.347a1.125 1.125 0 0 1-.21-1.298L9.75 12l-1.64-1.64a6 6 0 0 1-1.676-3.257l-.172-1.03Z" />
    </svg>
@endif
