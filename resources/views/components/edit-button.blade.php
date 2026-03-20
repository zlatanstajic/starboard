@props([
    'payload',
    'eventName' => 'open-edit-modal'
])

<button type="button"
    @click="$dispatch('{{ $eventName }}', {{ json_encode($payload) }})"
    {{ $attributes->merge([
        'class' => 'p-2 text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 dark:bg-gray-700 transition-colors',
        'title' => __('messages.default.edit'),
    ]) }}>
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
    </svg>
</button>
