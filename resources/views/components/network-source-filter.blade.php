@props([
    'sources',
    'label',
])

@php
    /** Query filters carried over into every option link. */
    $currentFilter = request('filter', []);
    $currentFilter = is_array($currentFilter) ? $currentFilter : [];

    $selectedId = request('filter.network_source_id');
    $selectedId = is_scalar($selectedId) ? (string) $selectedId : '';

    $selectedSource = $selectedId !== ''
        ? $sources->firstWhere('id', (int) $selectedId)
        : null;

    /** Builds the listing URL for a source, or for "all sources" when null. */
    $filterUrl = fn (?int $sourceId): string => request()->fullUrlWithQuery([
        'filter' => array_merge($currentFilter, ['network_source_id' => $sourceId]),
    ]);

    $optionBaseClasses = 'flex items-center gap-2 px-2.5 py-2 text-sm';
    $optionSelectedClasses = 'bg-blue-600 text-white hover:bg-blue-700';
    $optionIdleClasses = 'text-gray-900 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-600';
@endphp

<div class="relative"
    data-network-source-filter
    x-data="{ open: false }"
    @click.outside="open = false"
    @keydown.escape.window="open = false">

    {{-- Trigger: mirrors the sibling native selects (text-sm + p-2.5 + border) so the row keeps one height. --}}
    <button type="button"
        @click="open = !open"
        :aria-expanded="open"
        aria-haspopup="listbox"
        aria-label="{{ $label }}"
        class="bg-gray-50 border border-gray-300 text-sm rounded-lg flex w-full items-center gap-2 p-2.5 text-left dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        @if($selectedSource)
            <x-source-icon :slug="$selectedSource->icon" :fallback="true" class="w-4 h-4 shrink-0" />
            <span class="truncate">{{ ucfirst($selectedSource->name) }}</span>
        @else
            <span class="truncate">{{ $label }}</span>
        @endif

        <svg class="w-4 h-4 ml-auto shrink-0 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition
        role="listbox"
        aria-label="{{ $label }}"
        class="absolute z-50 mt-1 w-full max-h-64 overflow-y-auto py-1 rounded-lg shadow-lg bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600">

        <a href="{{ $filterUrl(null) }}"
            role="option"
            aria-selected="{{ $selectedSource ? 'false' : 'true' }}"
            class="{{ $optionBaseClasses }} {{ $selectedSource ? $optionIdleClasses : $optionSelectedClasses }}">
            {{-- Spacer keeps this label aligned with the icon-bearing options below. --}}
            <span class="w-4 h-4 shrink-0" aria-hidden="true"></span>
            <span class="truncate">{{ $label }}</span>
        </a>

        @foreach($sources as $source)
            @php
                $isSelected = $selectedSource !== null && $selectedSource->id === $source->id;
            @endphp

            <a href="{{ $filterUrl($source->id) }}"
                role="option"
                aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                class="{{ $optionBaseClasses }} {{ $isSelected ? $optionSelectedClasses : $optionIdleClasses }}">
                <x-source-icon :slug="$source->icon" :fallback="true" class="w-4 h-4 shrink-0" />
                <span class="truncate">{{ ucfirst($source->name) }}</span>
            </a>
        @endforeach
    </div>
</div>
