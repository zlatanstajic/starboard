@props([
    // Per-page localStorage key holding the filter panel's open/closed state.
    'storageKey',
])

<button data-filter-toggle
    @click="showFilters = !showFilters; localStorage.setItem('{{ $storageKey }}', showFilters ? '1' : '0')"
    :aria-expanded="showFilters"
    {{ $attributes->merge(['class' => 'inline-flex w-28 items-center justify-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700 transition-colors']) }}>
    <svg class="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
    </svg>
    {{ __('messages.default.toggle_filters') }}
</button>
