@props([
    'title',
    'message',
    'itemName' => 'deleteItemName',
    'deleteUrl' => 'deleteUrl'
])

<div x-show="deleteOpen"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4"
     @keydown.escape.window="deleteOpen = false"
     role="alertdialog"
     aria-modal="true"
     aria-label="{{ $title }}">

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full p-6"
         @click.away="deleteOpen = false">

        <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full dark:bg-red-900">
            <svg class="w-6 h-6 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4v2m0 0v2m0-2H9m3 0h3M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
        </div>

        <h3 class="text-lg font-bold text-center mb-2 text-gray-900 dark:text-white">
            {{ $title }}
        </h3>

        <p class="text-center text-gray-600 dark:text-gray-400 mb-6">
            {{ $message }}
            <span class="font-semibold" x-text="deleteItemName"></span>?
            <br>
            <span class="text-xs uppercase tracking-widest text-red-500 font-bold">{{ __('messages.default.this_action_cannot_be_undone') }}</span>
        </p>

        <div class="flex justify-center gap-3">
            <button type="button"
                    @click="deleteOpen = false"
                    class="px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
            >
                {{ __('messages.default.cancel') }}
            </button>

            <form :action="{{ $deleteUrl }}" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                    {{ __('messages.default.confirm') }}
                </button>
            </form>
        </div>
    </div>
</div>
