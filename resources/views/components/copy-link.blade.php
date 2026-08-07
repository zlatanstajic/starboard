@props(['url'])

<div x-data="{
        copied: false,
        failed: false,
        async copy() {
            this.failed = false;
            try {
                if (!navigator.clipboard) {
                    throw new Error('Clipboard API unavailable');
                }
                await navigator.clipboard.writeText(@js($url));
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            } catch (error) {
                this.failed = true;
            }
        }
    }" class="space-y-2">
    <div class="flex gap-2">
        <input type="text" readonly value="{{ $url }}" class="min-w-0 flex-1 rounded-lg border-gray-300 bg-gray-50 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" @focus="$el.select()">
        <button type="button" @click="copy()" class="rounded-lg bg-indigo-600 p-2.5 text-white hover:bg-indigo-700" aria-label="{{ __('messages.filter_list.copy_link') }}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2m-6-8h8a2 2 0 012 2v10a2 2 0 01-2 2h-8a2 2 0 01-2-2V9a2 2 0 012-2z" /></svg>
        </button>
    </div>
    <p x-show="copied" x-cloak class="text-sm text-green-600 dark:text-green-400">{{ __('messages.filter_list.copied') }}</p>
    <p x-show="failed" x-cloak class="text-sm text-red-600 dark:text-red-400">{{ __('messages.filter_list.copy_failed') }}</p>
</div>
