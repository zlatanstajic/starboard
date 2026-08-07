<x-public-layout
    :title="$filterList->name"
    :description="$filterList->description"
    :canonical="$filterList->publicUrl()"
>
    <section class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800"
        x-data="{ showFilters: false }"
        x-init="showFilters = (localStorage.getItem('show_filters_shared_list') === '1');">
        <header class="flex flex-col gap-3 border-b border-gray-200 px-6 py-5 sm:flex-row sm:items-start sm:justify-between dark:border-gray-700">
            <div class="min-w-0">
                <h1 class="flex items-center gap-3 text-2xl font-semibold text-gray-900 dark:text-white">
                    <x-list-icon :title="__('messages.filter_list.public.lists')" class="h-6 w-6 shrink-0 text-indigo-600 dark:text-indigo-400" />
                    {{ $filterList->name }}
                </h1>
                @if($filterList->description)
                    <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-300">{{ $filterList->description }}</p>
                @endif
            </div>

            <x-filter-toggle-button storage-key="show_filters_shared_list" class="shrink-0" />
        </header>

        <div class="p-6">
            <div x-cloak x-show="showFilters" x-transition class="mb-6">
                <form action="{{ request()->url() }}" method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5" data-shared-list-filters>
                    <select name="filter[network_source_id]" class="rounded-lg border-gray-300 bg-gray-50 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">{{ __('messages.network_profile.filter.all_network_sources') }}</option>
                        @foreach($networkSources as $source)
                            <option value="{{ $source->id }}" {{ request('filter.network_source_id') === (string) $source->id ? 'selected' : '' }}>{{ $source->name }}</option>
                        @endforeach
                    </select>

                    <select name="filter[tags]" class="rounded-lg border-gray-300 bg-gray-50 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">{{ __('messages.network_profile.filter.all_tags') }}</option>
                        <option value="any" {{ request('filter.tags') === 'any' ? 'selected' : '' }}>{{ __('messages.network_profile.filter.with_tags') }}</option>
                        <option value="none" {{ request('filter.tags') === 'none' ? 'selected' : '' }}>{{ __('messages.network_profile.filter.without_tags') }}</option>
                        @foreach($networkTags as $tag)
                            <option value="{{ $tag->id }}" {{ request('filter.tags') === (string) $tag->id ? 'selected' : '' }}>{{ $tag->name }}</option>
                        @endforeach
                    </select>

                    <select name="sort" class="rounded-lg border-gray-300 bg-gray-50 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">{{ __('messages.default.default_sort') }}</option>
                        @foreach(__('messages.filter_list.public.sort') as $value => $label)
                            <option value="{{ $value }}" {{ request('sort') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>

                    <input type="search" name="filter[search]" value="{{ is_string(request('filter.search')) ? request('filter.search') : '' }}" placeholder="{{ __('messages.network_profile.placeholder.search') }}" class="rounded-lg border-gray-300 bg-gray-50 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">

                    <div class="flex items-center gap-2">
                        <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300">
                            {{ __('messages.default.apply') }}
                        </button>

                        <a href="{{ request()->url() }}" data-clear-shared-filters class="inline-flex flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                            {{ __('messages.default.clear') }}
                        </a>
                    </div>
                </form>
            </div>

            <div>
                @include('components.pagination', ['items' => $networkProfiles])

                <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm dark:border-gray-700">
                    <table class="min-w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                            <tr>
                                <th class="px-6 py-3" scope="col">{{ __('messages.default.name') }}</th>
                                <th class="px-6 py-3" scope="col">{{ __('messages.default.source') }}</th>
                                <th class="px-6 py-3" scope="col">{{ __('messages.default.tags') }}</th>
                                <th class="px-6 py-3" scope="col">{{ __('messages.default.description') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($networkProfiles as $profile)
                                <tr class="border-b bg-white transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                        <a href="{{ $profile->profileUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-indigo-600 hover:underline dark:text-indigo-400">
                                            <x-source-icon :slug="$profile->networkSource?->icon" :title="$profile->networkSource?->name" :fallback="$profile->networkSource !== null" class="h-4 w-4 shrink-0" />
                                            {{ $profile->title ?: '@'.$profile->username }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4">{{ $profile->networkSource?->name ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ $profile->networkTags->pluck('name')->sort()->implode(', ') ?: '-' }}</td>
                                    <td class="px-6 py-4">{{ $profile->description ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">{{ __('messages.network_profile.no_profiles_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</x-public-layout>
