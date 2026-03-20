@props([
    'ariaLabel' => '',
])

<select onchange="window.location.href=this.value"
    aria-label="{{ $ariaLabel }}"
    {{ $attributes->merge([
        'class' => 'bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white',
    ]) }}>
    {{ $slot }}
</select>
