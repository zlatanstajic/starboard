@props([
    'url',
    'name',
    'title' => ''
])

<button type="button"
    @click="$dispatch('open-delete-modal', {
        deleteUrl: {{ json_encode($url) }},
        deleteItemName: {{ json_encode($name) }}
    })"
    {{ $attributes->merge([
        'class' => 'text-red-600 bg-red-50 hover:bg-red-100 dark:bg-gray-700 dark:text-red-400 dark:hover:bg-gray-600 p-2 rounded-lg transition-colors',
    ]) }}
    title="{{ $title ?: __('messages.default.delete') }}">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
</button>
