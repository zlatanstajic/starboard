<div x-data="{ isOpen: false }"
    x-on:open-save-list-modal.window="isOpen = true"
    x-show="isOpen"
    x-cloak
    @keydown.escape.window="isOpen = false"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4"
    role="dialog"
    aria-modal="true"
    aria-label="{{ __('messages.filter_list.save') }}">
    <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800" @click.away="isOpen = false">
        <h3 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ __('messages.filter_list.save') }}</h3>

        <form autocomplete="off" action="{{ route('filter-lists.store').'?'.request()->getQueryString() }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="save-list-name" class="mb-1 block text-sm font-medium dark:text-gray-300">{{ __('messages.default.name') }}</label>
                <input id="save-list-name" name="name" type="text" required maxlength="100" class="w-full rounded-lg border p-2.5 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>
            <div>
                <label for="save-list-description" class="mb-1 block text-sm font-medium dark:text-gray-300">{{ __('messages.default.description') }}</label>
                <textarea id="save-list-description" name="description" maxlength="1000" rows="3" class="w-full rounded-lg border p-2.5 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_published" value="0">
                <input id="save-list-published" name="is_published" type="checkbox" value="1" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                <label for="save-list-published" class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.filter_list.published_label') }}</label>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="isOpen = false" class="inline-flex justify-center px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-200 transition-colors hover:text-black">{{ __('messages.default.cancel') }}</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">{{ __('messages.default.save') }}</button>
            </div>
        </form>
    </div>
</div>
