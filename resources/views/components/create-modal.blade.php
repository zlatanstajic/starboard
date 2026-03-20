@props([
    'title',
    'action',
    'submitText' => '',
    'xData' => '{ createOpen: false }'
])

<div x-data="{{ $xData }}"
    x-on:open-create-modal.window="createOpen = true"
    x-show="createOpen"
    x-cloak
    @keydown.escape.window="createOpen = false"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4"
    role="dialog"
    aria-modal="true"
    aria-label="{{ $title }}">

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full p-6" @click.away="createOpen = false">
        <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-white">{{ $title }}</h3>

        <form autocomplete="off" action="{{ $action . (request()->getQueryString() ? '?' . request()->getQueryString() : '') }}"
              method="POST"
              class="space-y-4">
            @csrf

            {{ $slot }}

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" @click="createOpen = false"
                        class="px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-200 transition-colors hover:text-black"
                >
                    {{ __('messages.default.cancel') }}
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                    {{ $submitText ?? __('messages.default.create') }}
                </button>
            </div>
        </form>
    </div>
</div>
