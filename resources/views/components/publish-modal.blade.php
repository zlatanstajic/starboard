<div x-data="{ isOpen: false }"
    x-on:open-publish-list-modal.window="isOpen = true"
    x-show="isOpen"
    x-cloak
    @keydown.escape.window="isOpen = false"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4"
    role="dialog"
    aria-modal="true"
    aria-label="{{ __('messages.filter_list.publish') }}">
    <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800" @click.away="isOpen = false">
        <h3 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ __('messages.filter_list.publish') }}</h3>

        <form autocomplete="off" action="{{ route('filter-lists.store').'?'.request()->getQueryString() }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="publish-list-name" class="mb-1 block text-sm font-medium dark:text-gray-300">{{ __('messages.default.name') }}</label>
                <input id="publish-list-name" name="name" type="text" required maxlength="100" class="w-full rounded-lg border p-2.5 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>
            <div>
                <label for="publish-list-description" class="mb-1 block text-sm font-medium dark:text-gray-300">{{ __('messages.default.description') }}</label>
                <textarea id="publish-list-description" name="description" maxlength="1000" rows="3" class="w-full rounded-lg border p-2.5 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="isOpen = false" class="rounded-lg bg-gray-100 px-4 py-2 text-sm hover:bg-gray-200 dark:bg-gray-700 dark:text-white">{{ __('messages.default.cancel') }}</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">{{ __('messages.filter_list.publish') }}</button>
            </div>
        </form>
    </div>
</div>
