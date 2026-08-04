@props([
    'ariaLabel' => '',
    'navigate' => true,
])

{{-- $navigate = false keeps the select inert so it can be submitted with a form instead of navigating on change. --}}
<select @if($navigate) onchange="window.location.href=this.value" @endif
    aria-label="{{ $ariaLabel }}"
    {{ $attributes->merge([
        'class' => 'bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white',
    ]) }}>
    {{ $slot }}
</select>
