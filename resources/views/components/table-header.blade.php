@props([
    'title',
    'showFiltersToggle' => true,
])

<div {{ $attributes->merge(['class' => 'flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-4']) }}>
    <div class="flex items-center gap-2 my-2">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $title }}
        </h2>
    </div>

    <div class="flex items-center gap-2">
        @if($showFiltersToggle)
            <div x-cloak x-show="showFilters" x-transition>
                <div class="relative w-full md:w-auto" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                    <button type="button"
                            @click="open = !open"
                            :aria-expanded="open"
                            aria-haspopup="true"
                            aria-label="{{ __('messages.default.columns_help') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18M3 19h18M6 5v14M12 5v14M18 5v14" />
                        </svg>
                        {{ __('messages.default.columns') }}
                    </button>

                    <div x-show="open" x-cloak x-transition
                        class="absolute z-50 mt-2 w-56 rounded-lg shadow-lg bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 p-3">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">{{ __('messages.default.columns') }}</p>
                        <div class="flex flex-col gap-2">
                            {{-- Name: always visible, disabled + checked (hide-all guard) --}}
                            <label class="flex items-center cursor-not-allowed opacity-70">
                                <input type="checkbox" checked disabled class="rounded text-blue-600">
                                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.name') }}</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" x-model="columns.number" class="rounded text-blue-600">
                                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.row_number') }}</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" x-model="columns.tags" class="rounded text-blue-600">
                                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.tags') }}</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" x-model="columns.status" class="rounded text-blue-600">
                                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.status') }}</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" x-model="columns.favorite" class="rounded text-blue-600">
                                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.favorite') }}</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" x-model="columns.visits" class="rounded text-blue-600">
                                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.visits') }}</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" x-model="columns.timestamps" class="rounded text-blue-600">
                                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.timestamps') }}</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" x-model="columns.actions" class="rounded text-blue-600">
                                <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.actions') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <button @click="showFilters = !showFilters; localStorage.setItem('show_filters', showFilters ? '1' : '0')"
                :aria-expanded="showFilters"
                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700 transition-colors">
                <svg class="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                {{ __('messages.default.toggle_filters') }}
            </button>

        @endif

        {{-- Fetch button hidden for now (YouTube videos fetch disabled on the frontend). --}}
        {{--
        <div x-data="fetchIndicator({
                active: @js((bool) session('fetch_batch_id')),
                statusUrl: @js(route('network-profiles.fetch.status')),
                progressLabel: @js(__('messages.network_profile.fetch.in_progress')),
            })" class="flex items-center gap-2">
            <form id="fetch-form" method="POST" action="{{ route('network-profiles.fetch', request()->query()) }}" @submit="submitting = true">
                @csrf
                <button type="submit" :disabled="busy"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg x-show="busy" x-cloak class="w-4 h-4 mr-2 animate-spin" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <svg x-show="!busy" class="w-4 h-4 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 10.5V6a1 1 0 0 0-1-1H9.914a1 1 0 0 1-.707-.293L7.293 2.793A1 1 0 0 0 6.586 2.5H4a1 1 0 0 0-1 1V17a1 1 0 0 0 1 1h6M13 15h7m-3.5-3.5v7"/>
                    </svg>
                    <span x-show="!busy">{{ __('messages.default.fetch') }}</span>
                    <span x-show="busy" x-cloak x-text="label"></span>
                </button>
            </form>
        </div>
        --}}


        @once
            <script>
                window.fetchIndicator = ({ active, statusUrl, progressLabel }) => ({
                    active,
                    statusUrl,
                    progressLabel,
                    submitting: false,
                    total: 0,
                    processed: 0,
                    timer: null,
                    get busy() {
                        return this.submitting || this.active;
                    },
                    get label() {
                        return this.total > 0
                            ? `${this.progressLabel} ${this.processed}/${this.total}`
                            : this.progressLabel;
                    },
                    init() {
                        if (! this.active) {
                            return;
                        }

                        this.poll();
                        this.timer = setInterval(() => this.poll(), 2500);
                    },
                    async poll() {
                        try {
                            const response = await fetch(this.statusUrl, {
                                headers: { 'Accept': 'application/json' },
                            });

                            if (! response.ok) {
                                return;
                            }

                            const data = await response.json();

                            if (! data.active) {
                                this.stop();

                                return;
                            }

                            this.total = data.total ?? 0;
                            this.processed = data.processed ?? 0;

                            if (data.finished) {
                                this.stop();
                                window.location.reload();
                            }
                        } catch (error) {
                            // Network hiccup — keep polling on the next tick.
                        }
                    },
                    stop() {
                        this.active = false;

                        if (this.timer) {
                            clearInterval(this.timer);
                            this.timer = null;
                        }
                    },
                });
            </script>
        @endonce

        <button onclick="window.location.href=window.location.href"
                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700 transition-colors">
            <svg class="w-4 h-4 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 18 20">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 1v5h-5M2 19v-5h5m10-4a8 8 0 0 1-14.947 3.97M1 10a8 8 0 0 1 14.947-3.97"/>
            </svg>
            {{ __('messages.default.refresh') }}
        </button>

        <x-create-button />
    </div>
</div>
