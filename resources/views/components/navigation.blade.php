<header {{ $attributes->merge(['class' => 'fixed top-0 w-full z-50 bg-white/80 dark:bg-gray-800 backdrop-blur-md border-b border-gray-200 dark:border-[#3E3E3A]']) }}>
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="shrink-0 flex items-center">
                <a href="{{ route('home') }}">
                    <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                </a>
            </div>
            <span class="hidden md:block font-bold text-xl tracking-tight text-gray-900 dark:text-white ml-1">
                {{ config('app.name', 'Starboard') }}
            </span>
        </div>

        @if (Route::has('login'))
            <nav class="flex items-center gap-4">
                <div class="md:flex gap-2 mr-1 border-r border-gray-200 dark:border-gray-700 pr-4">
                    @foreach(['en' => 'EN', 'sr' => 'SR'] as $locale => $label)
                        @if(app()->getLocale() !== $locale)
                            <a href="{{ route('locale.switch', $locale) }}"
                               class="px-2 py-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400">
                                {{ $label === 'SR' ? '🇷🇸' : '🇬🇧' }}
                            </a>
                        @endif
                    @endforeach
                </div>

                @auth
                    <a href="{{ url('/dashboard') }}" class="px-3 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition shadow-md shadow-indigo-500/20">
                        {{ __('messages.default.dashboard') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-indigo-600 transition">
                        {{ __('messages.default.login') }}
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="px-3 py-2 border border-indigo-600 text-indigo-600 dark:text-indigo-400 rounded-lg font-medium hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition">
                            {{ __('messages.default.register') }}
                        </a>
                    @endif
                @endauth
            </nav>
        @endif
    </div>
</header>
