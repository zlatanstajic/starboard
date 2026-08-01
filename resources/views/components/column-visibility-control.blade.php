<div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button"
            @click="open = !open"
            :aria-expanded="open"
            aria-haspopup="true"
            aria-label="{{ __('messages.default.columns_help') }}"
            class="inline-flex w-28 items-center justify-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700 transition-colors">
        <svg class="w-4 h-4 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18M3 19h18M6 5v14M12 5v14M18 5v14" />
        </svg>
        {{ __('messages.default.columns') }}
    </button>

    <div x-show="open" x-cloak x-transition
        class="absolute z-50 mt-2 w-56 rounded-lg shadow-lg bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 p-3">
        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">{{ __('messages.default.columns') }}</p>
        <div class="flex flex-col gap-2">
            {{-- Name: always visible, disabled + checked (hide-all guard) --}}
            <label class="flex items-center cursor-not-allowed opacity-70">
                <input type="checkbox" checked disabled class="rounded text-blue-600">
                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.name') }}</span>
            </label>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" x-model="columns.number" class="rounded text-blue-600">
                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.row_number') }}</span>
            </label>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" x-model="columns.tags" class="rounded text-blue-600">
                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.tags') }}</span>
            </label>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" x-model="columns.status" class="rounded text-blue-600">
                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.status') }}</span>
            </label>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" x-model="columns.favorite" class="rounded text-blue-600">
                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.favorite') }}</span>
            </label>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" x-model="columns.visits" class="rounded text-blue-600">
                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.visits') }}</span>
            </label>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" x-model="columns.timestamps" class="rounded text-blue-600">
                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.timestamps') }}</span>
            </label>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" x-model="columns.actions" class="rounded text-blue-600">
                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.actions') }}</span>
            </label>
        </div>
    </div>
</div>
