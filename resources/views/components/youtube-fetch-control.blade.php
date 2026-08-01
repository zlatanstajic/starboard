@props([
    'youtubeFetchEnabled' => false,
    'availability' => ['circuit_open' => false, 'budget_exhausted' => false],
])

@php
    $unavailable = $availability['circuit_open'] || $availability['budget_exhausted'];
    $unavailableLabel = $availability['circuit_open']
        ? __('messages.network_profile.fetch.circuit_open')
        : ($availability['budget_exhausted'] ? __('messages.network_profile.fetch.budget_exhausted') : '');
@endphp

@if($youtubeFetchEnabled)
    <form
        method="POST"
        action="{{ route('network-profiles.fetch', request()->query()) }}"
        class="shrink-0"
        x-data="youtubeFetchControl({
            active: @js((bool) session('fetch_batch_id')),
            statusUrl: @js(route('network-profiles.fetch.status')),
            unavailable: @js($unavailable),
            unavailableLabel: @js($unavailableLabel),
        })"
        @submit="submitting = true"
    >
        @csrf
        <button type="submit" :disabled="busy || unavailable" @disabled($unavailable) class="inline-flex w-28 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:ring-gray-700">
            <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin" aria-hidden="true" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.37 0 0 5.37 0 12h4z"></path>
            </svg>
            <svg x-show="!busy" class="h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" />
            </svg>
            <span role="status" aria-live="polite" class="truncate" x-text="label">{{ __('messages.default.fetch') }}</span>
        </button>
    </form>
@endif
