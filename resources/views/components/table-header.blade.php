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
            <button @click="showFilters = !showFilters; localStorage.setItem('show_filters', showFilters ? '1' : '0')"
                :aria-expanded="showFilters"
                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700 transition-colors">
                <svg class="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                {{ __('messages.default.toggle_filters') }}
            </button>
        @endif

        <div x-data="fetchIndicator({
                active: @js((bool) session('fetch_batch_id')),
                statusUrl: @js(route('network-profiles.fetch.status')),
                progressLabel: @js(__('messages.network_profile.fetch.in_progress')),
            })">
            <form method="POST" action="{{ route('network-profiles.fetch') }}" @submit="submitting = true">
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
