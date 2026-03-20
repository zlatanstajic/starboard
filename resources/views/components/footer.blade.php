<footer {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-4']) }}>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-600 dark:text-gray-400">
        Copyright © {{ date('Y') }}
        <span class="font-medium"> | </span>
        <a href="https://zlatanstajic.com"
           target="_blank"
           rel="noopener noreferrer"
           class="text-blue-600 hover:underline dark:text-blue-400">
            zlatanstajic.com
        </a>
    </div>
</footer>
