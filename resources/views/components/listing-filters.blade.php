@props([
    // Spatie sort expression => human label (e.g. 'name' => 'Name (A-Z)')
    'sortOptions',
    'searchPlaceholder',
    // list of ['key' => string, 'label' => string, 'locked' => bool]
    'columns',
    // filter value => human label; omitted on listings without statuses
    'statusOptions' => [],
])

@php
    $search = request()->input('filter.search');
    $search = is_string($search) ? $search : '';
    $status = request()->input('filter.exclude_from_dashboard');
    $status = is_string($status) ? $status : '';
@endphp

{{--
    Shared filter panel for the simple listing pages (sources, tags): one sort
    control, one search control and one Apply button inside a single GET form,
    with the column picker and the clear button on the row below. Clearing
    navigates to the bare page URL and never touches localStorage, so the
    column selection survives it.
--}}
<div x-cloak x-show="showFilters" x-transition class="flex flex-col md:flex-row md:items-end gap-4 mb-6">
    <form autocomplete="off" id="search-form" action="{{ request()->url() }}" method="GET" class="flex flex-col md:flex-row md:items-end gap-4 w-full">

        {{--
            :navigate="false" because the value is a sort expression, not a URL:
            the select submits its own form instead, so changing the sort applies
            immediately (matching the dashboard's selects) and carries whatever
            is already typed in the search box along with it.
        --}}
        <div class="w-full md:w-1/5">
            <x-filter-select name="sort" :navigate="false" onchange="this.form.requestSubmit()" :aria-label="__('messages.default.default_sort')">
                <option value="">{{ __('messages.default.default_sort') }}</option>

                @foreach($sortOptions as $key => $label)
                    <option value="{{ $key }}" {{ request('sort') === $key ? 'selected' : '' }}>
                        {{ __('messages.default.sort_by') }} {{ $label }}
                    </option>
                @endforeach
            </x-filter-select>
        </div>

        {{--
            Profile-count ranges, mirroring the dashboard's visits filter. Unlike
            that one it is a real form field (name="filter[profiles]") rather than
            a select of URLs, so it submits alongside the sort and search.
        --}}
        <div class="w-full md:w-1/5">
            <x-filter-select name="filter[profiles]" :navigate="false" onchange="this.form.requestSubmit()" :aria-label="__('messages.default.all_profiles')">
                <option value="">{{ __('messages.default.all_profiles') }}</option>

                <option value="0" {{ request('filter.profiles') === '0' ? 'selected' : '' }}>
                    {{ __('messages.default.no_profiles') }}
                </option>

                @foreach(['1-5', '6-10', '11-20', '21-50', '51-100', '100+'] as $range)
                    <option value="{{ $range }}" {{ request('filter.profiles') === $range ? 'selected' : '' }}>
                        {{ $range }}
                    </option>
                @endforeach
            </x-filter-select>
        </div>

        @if($statusOptions !== [])
            <div class="w-full md:w-1/5">
                <x-filter-select name="filter[exclude_from_dashboard]" :navigate="false" onchange="this.form.requestSubmit()" :aria-label="__('messages.network_source.filter.all_statuses')">
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" {{ $status === (string) $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </x-filter-select>
            </div>
        @endif

        {{-- Takes the width the fixed-size fields leave over, so the row never needs to shrink them. --}}
        <div class="w-full md:flex-1">
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                    </svg>
                </div>

                <input type="text"
                    id="search"
                    name="filter[search]"
                    value="{{ $search }}"
                    autocomplete="off"
                    class="block w-full p-2.5 pl-10 pr-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    placeholder="{{ $searchPlaceholder }}">

                <button type="button"
                        id="clear-search"
                        aria-label="{{ __('messages.default.clear') }}"
                        class="{{ $search !== '' ? '' : 'hidden' }} absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <button type="submit"
                class="inline-flex w-28 shrink-0 items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 transition-colors">
            {{ __('messages.default.apply') }}
        </button>
    </form>
</div>

{{--
    `relative z-40` lifts the whole actions row above the table that follows it,
    so the column dropdown is not painted underneath a short listing.
--}}
<div x-cloak x-show="showFilters" x-transition class="relative z-40 flex flex-nowrap items-center gap-2 mb-6" data-filter-actions>
    <x-column-visibility-control :columns="$columns" />

    <button onclick="window.location.href='{{ request()->url() }}'"
        class="inline-flex w-28 shrink-0 items-center justify-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700 transition-colors">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
        </svg>
        {{ __('messages.default.clear') }}
    </button>
</div>
