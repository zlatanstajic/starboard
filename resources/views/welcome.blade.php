<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <title>{{ config('app.name', 'Starboard') }} - {{ __('messages.welcome.manage_your_network') }}</title>
        <meta name="description" content="{{ __('messages.welcome.description') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('logo.png') }}" type="image/x-icon">
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] antialiased flex flex-col min-h-dvh">

        <x-navigation />

        <main class="flex-grow pt-32 pb-20 px-6 dark:bg-gray-900">
            <div class="max-w-5xl mx-auto text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-xs font-bold uppercase tracking-wider mb-6">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                    </span>
                    {{ __('messages.welcome.catch_phrase') }}
                </div>

                <h1 class="text-5xl md:text-7xl font-bold tracking-tight mb-8 bg-gradient-to-r from-gray-900 via-indigo-800 to-gray-900 dark:from-white dark:via-indigo-300 dark:to-white bg-clip-text text-transparent">
                    {{ config('app.name', 'Starboard') }}
                </h1>

                <p class="text-lg md:text-xl text-gray-600 dark:text-gray-400 mb-10 max-w-2xl mx-auto leading-relaxed">
                    {{ __('messages.welcome.description') }}
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="{{ route('register') }}" class="w-full sm:w-auto px-8 py-4 bg-gray-900 dark:bg-white dark:text-black text-white rounded-xl font-bold text-lg hover:scale-105 transition shadow-2xl">
                        {{ __('messages.welcome.get_started') }}
                    </a>
                    <a href="#features" class="w-full sm:w-auto px-8 py-4 border border-gray-300 dark:border-gray-700 rounded-xl font-bold text-lg hover:bg-gray-50 dark:hover:bg-gray-900 transition">
                        {{ __('messages.welcome.view_features') }}
                    </a>
                </div>
            </div>

            <div class="flex justify-center mt-16 mb-12">
                <a href="https://www.producthunt.com/products/starboard/launches/starboard-2?embed=true&amp;utm_source=badge-featured&amp;utm_medium=badge&amp;utm_campaign=badge-starboard-2" target="_blank" rel="noopener noreferrer"><img alt="Starboard - Surf the Web like a pro | Product Hunt" width="250" height="54" src="https://api.producthunt.com/widgets/embed-image/v1/featured.svg?post_id=1078486&amp;theme=dark&amp;t=1772649373270"></a>
            </div>

            <section id="description" class="max-w-7xl mx-auto mt-7 p-1" aria-labelledby="faq-section-title">
                <h2 id="faq-section-title" class="text-2xl font-bold m-4 p-12 text-gray-900 text-center uppercase dark:text-gray-100">{{ __('messages.default.faq') }}</h2>

                <div class="grid gap-8 md:grid-cols-3">
                    @php
                        $faqs = trans('messages.faqs');
                    @endphp
                    @foreach($faqs as $faq)
                        <div class="p-8 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-[#3E3E3A] shadow-sm">
                            <div class="text-indigo-600 mb-4 font-bold text-2xl">{{ $faq['title'] }}</div>
                            <p class="text-gray-500 dark:text-gray-400">{{ $faq['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section id="features" class="max-w-7xl mx-auto mt-7 p-1" aria-labelledby="features-section-title">
                <h2 id="features-section-title" class="text-2xl font-bold m-4 p-12 text-gray-900 text-center uppercase dark:text-gray-100">{{ __('messages.default.features') }}</h2>

                @php
                    // Load features from translation files (returns an array)
                    $features = trans('messages.features');
                @endphp

                <div class="space-y-3">
                    @foreach($features as $index => $feature)
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-[#3E3E3A] shadow-sm overflow-hidden transition-all duration-300 hover:border-gray-200 dark:hover:border-[#4E4E4A]">
                            <button
                                class="feature-accordion-btn w-full px-8 py-5 text-left font-bold text-lg flex items-center justify-between text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                data-feature="{{ $index }}"
                                aria-expanded="false"
                                aria-controls="feature-content-{{ $index }}"
                            >
                                <span class="flex items-center gap-3">
                                    <span class="text-indigo-600 text-2xl font-bold">+</span>
                                    {{ $feature['title'] }}
                                </span>
                                <svg class="w-5 h-5 text-indigo-600 transition-transform duration-300 feature-accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                </svg>
                            </button>
                            <div
                                id="feature-content-{{ $index }}"
                                class="feature-accordion-content max-h-0 overflow-hidden transition-all duration-300 ease-in-out"
                            >
                                <div class="px-8 py-4 text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-[#3E3E3A]">
                                    {{ $feature['desc'] }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </main>

        <x-footer />

        <x-back-to-top />

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const accordionButtons = document.querySelectorAll('.feature-accordion-btn');

                accordionButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const isExpanded = this.getAttribute('aria-expanded') === 'true';
                        const contentId = this.getAttribute('aria-controls');
                        const content = document.getElementById(contentId);
                        const icon = this.querySelector('.feature-accordion-icon');

                        // Close all other accordions
                        accordionButtons.forEach(otherButton => {
                            if (otherButton !== this) {
                                const otherContentId = otherButton.getAttribute('aria-controls');
                                const otherContent = document.getElementById(otherContentId);
                                const otherIcon = otherButton.querySelector('.feature-accordion-icon');

                                otherButton.setAttribute('aria-expanded', 'false');
                                otherButton.classList.remove('bg-indigo-50', 'dark:bg-gray-700');
                                otherContent.style.maxHeight = '0px';
                                otherIcon.style.transform = 'rotate(0deg)';
                            }
                        });

                        // Toggle current accordion
                        if (isExpanded) {
                            this.setAttribute('aria-expanded', 'false');
                            this.classList.remove('bg-indigo-50', 'dark:bg-gray-700');
                            content.style.maxHeight = '0px';
                            icon.style.transform = 'rotate(0deg)';
                        } else {
                            this.setAttribute('aria-expanded', 'true');
                            this.classList.add('bg-indigo-50', 'dark:bg-gray-700');
                            content.style.maxHeight = content.scrollHeight + 'px';
                            icon.style.transform = 'rotate(180deg)';
                        }
                    });
                });
            });
        </script>
    </body>
</html>
