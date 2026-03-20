@props(['title', 'eventName'])

<div x-data="{
        isOpen: false,
        updateUrl: '',
        data: {}
    }"
    x-on:{{ $eventName }}.window="
        updateUrl = $event.detail.updateUrl;
        data = $event.detail;
        isOpen = true;
    "
    x-show="isOpen"
    x-cloak
    @keydown.escape.window="isOpen = false"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4"
    role="dialog"
    aria-modal="true"
    :aria-label="'{{ $title }}'">

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full p-6" @click.away="isOpen = false">
        <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-white">{{ $title }}</h3>

        <form autocomplete="off" :action="updateUrl + '?' + '{{ request()->getQueryString() }}'" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            {{ $slot }}

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" @click="isOpen = false"
                    class="px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-200 transition-colors hover:text-black">
                    {{ __('messages.default.cancel') }}
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    {{ __('messages.default.save_changes') }}
                </button>
            </div>
        </form>
    </div>
</div>
