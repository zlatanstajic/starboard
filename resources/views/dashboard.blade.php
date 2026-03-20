<x-app-layout>
    <div class="py-6" x-data="{
            editOpen: false,
            createOpen: false,
            deleteOpen: false,
            username: '',
            sourceId: '',
            createSourceId: '', // stores last selected source for Create modal
            isPublic: false,
            isFavorite: false,
            selectedTags: [],
            excludedTags: [],
            updateUrl: '',
            deleteUrl: '',
            deleteItemName: '',
            showFilters: false // controls visibility of filter panel
        }"
        x-init="createSourceId = localStorage.getItem('last_network_source_id') ?? ''; showFilters = (localStorage.getItem('show_filters') === '1');"
        @open-edit-modal.window="
            editOpen = true;
            username = $event.detail.username;
            sourceId = $event.detail.sourceId;
            isPublic = !!$event.detail.isPublic;
            isFavorite = !!$event.detail.isFavorite;
            selectedTags = ($event.detail.selectedTags || []).map(id => String(id));
        "
        @open-delete-modal.window="
            deleteOpen = true;
            deleteUrl = $event.detail.deleteUrl;
            deleteItemName = $event.detail.deleteItemName;
        ">

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    <div x-cloak x-show="showFilters" x-transition class="flex flex-col md:flex-row md:items-end gap-4 mb-6">

                        <div class="w-full md:w-1/6">
                            <select onchange="window.location.href=this.value" aria-label="{{ __('messages.network_profile.filter.all_network_sources') }}" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['network_source_id' => null])]) }}" {{ !request('filter.network_source_id') ? 'selected' : '' }}>{{ __('messages.network_profile.filter.all_network_sources') }}</option>
                                @foreach($networkSources as $source)
                                    <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['network_source_id' => $source->id])]) }}"
                                        {{ request('filter.network_source_id') == $source->id ? 'selected' : '' }}>
                                        {{ ucfirst($source->name) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full md:w-1/6">
                            <select onchange="window.location.href=this.value" aria-label="{{ __('messages.network_profile.filter.all_visits') }}" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['visits' => null])]) }}" {{ !request('filter.visits') ? 'selected' : '' }}>{{ __('messages.network_profile.filter.all_visits') }}</option>
                                @foreach(['1-5', '6-10', '11-20', '21-50', '51-100', '100+'] as $range)
                                    <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['visits' => $range])]) }}"
                                        {{ request('filter.visits') === $range ? 'selected' : '' }}>
                                        {{ $range }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full md:w-1/6">
                            <select onchange="window.location.href=this.value" aria-label="{{ __('messages.network_profile.filter.all_time') }}" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['last_visit' => null])]) }}" {{ !request('filter.last_visit') ? 'selected' : '' }}>{{ __('messages.network_profile.filter.all_time') }}</option>

                                @php
                                    $visitOptions = [
                                        '24h'    => __('messages.network_profile.filter.time.last_24_hours'),
                                        '7d'     => __('messages.network_profile.filter.time.last_1_7_days'),
                                        '30d'    => __('messages.network_profile.filter.time.last_8_30_days'),
                                        'older'  => __('messages.network_profile.filter.time.over_one_month'),
                                        'not_24h' => __('messages.network_profile.filter.time.not_last_24_hours'),
                                    ];
                                @endphp

                                @foreach($visitOptions as $key => $label)
                                    <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['last_visit' => $key])]) }}"
                                        {{ request('filter.last_visit') === $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full md:w-1/6">
                            <select onchange="window.location.href=this.value" aria-label="{{ __('messages.network_profile.filter.all_statuses') }}" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['is_public' => null])]) }}" {{ !request('filter.is_public') ? 'selected' : '' }}>{{ __('messages.network_profile.filter.all_statuses') }}</option>
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['is_public' => '1'])]) }}" {{ request('filter.is_public') === '1' ? 'selected' : '' }}>{{ __('messages.network_profile.filter.public_only') }}</option>
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['is_public' => '0'])]) }}" {{ request('filter.is_public') === '0' ? 'selected' : '' }}>{{ __('messages.network_profile.filter.private_only') }}</option>
                            </select>
                        </div>

                        <div class="w-full md:w-1/6">
                            <select onchange="window.location.href=this.value" aria-label="{{ __('messages.network_profile.filter.all_profiles') }}" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['is_favorite' => null])]) }}" {{ !request('filter.is_favorite') ? 'selected' : '' }}>{{ __('messages.network_profile.filter.all_profiles') }}</option>
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['is_favorite' => '1'])]) }}" {{ request('filter.is_favorite') === '1' ? 'selected' : '' }}>{{ __('messages.network_profile.filter.favorites_only') }}</option>
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['is_favorite' => '0'])]) }}" {{ request('filter.is_favorite') === '0' ? 'selected' : '' }}>{{ __('messages.network_profile.filter.non_favorites') }}</option>
                            </select>
                        </div>

                        <div class="w-full md:w-1/6">
                            <select onchange="window.location.href=this.value" aria-label="{{ __('messages.network_profile.filter.all_tags') }}" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['tags' => null])]) }}" {{ !request('filter.tags') ? 'selected' : '' }}>{{ __('messages.network_profile.filter.all_tags') }}</option>
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['tags' => 'any'])]) }}" {{ request('filter.tags') === 'any' ? 'selected' : '' }}>{{ __('messages.network_profile.filter.with_tags') }}</option>
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['tags' => 'none'])]) }}" {{ request('filter.tags') === 'none' ? 'selected' : '' }}>{{ __('messages.network_profile.filter.without_tags') }}</option>
                            </select>
                        </div>

                    </div>

                    <div x-cloak x-show="showFilters" x-transition class="flex flex-col md:flex-row md:items-end gap-4 mb-6">

                        <div class="w-full md:w-1/4">
                            <select onchange="window.location.href=this.value" aria-label="{{ __('messages.default.default_sort') }}" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="{{ request()->fullUrlWithQuery(['sort' => null]) }}">{{ __('messages.default.default_sort') }}</option>

                                @php
                                    $sortOptions = __('messages.network_profile.sort');
                                @endphp

                                @foreach($sortOptions as $key => $label)
                                    <option value="{{ request()->fullUrlWithQuery(['sort' => $key]) }}"
                                        {{ request('sort') === $key ? 'selected' : '' }}>
                                        {{ __('messages.default.sort_by') }} {{ $label }}
                                    </option>
                                @endforeach

                            </select>

                        </div>

                        <div class="w-full md:w-1/4">
                            <select onchange="window.location.href=this.value" aria-label="{{ __('messages.network_profile.filter.all_descriptions') }}" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['has_description' => null])]) }}" {{ !request('filter.has_description') ? 'selected' : '' }}>{{ __('messages.network_profile.filter.all_descriptions') }}</option>
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['has_description' => '1'])]) }}" {{ request('filter.has_description') === '1' ? 'selected' : '' }}>{{ __('messages.network_profile.filter.with_description') }}</option>
                                <option value="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['has_description' => '0'])]) }}" {{ request('filter.has_description') === '0' ? 'selected' : '' }}>{{ __('messages.network_profile.filter.without_description') }}</option>
                            </select>
                        </div>

                        <div class="w-full md:w-1/2">
                            <form autocomplete="off" id="search-form" action="{{ request()->url() }}" method="GET" class="relative group">
                                @if(request('filter'))
                                    @foreach(request('filter') as $key => $value)
                                        @if($key !== 'username' && $value !== null)
                                            @if(is_array($value))
                                                @foreach($value as $v)
                                                    <input type="hidden" name="filter[{{ $key }}][]" value="{{ $v }}">
                                                @endforeach
                                            @else
                                                <input type="hidden" name="filter[{{ $key }}]" value="{{ $value }}">
                                            @endif
                                        @endif
                                    @endforeach
                                @endif

                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                                    </svg>
                                </div>

                                <input type="text"
                                    id="search"
                                    name="filter[search]"
                                    value="{{ request()->input('filter.search') }}"
                                    autocomplete="off"
                                    class="block w-full p-2.5 pl-10 pr-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                    placeholder="{{ __('messages.network_profile.placeholder.search') }}">

                                <button type="button"
                                        id="clear-search"
                                        aria-label="Clear search"
                                        class="{{ request()->filled('filter.search') ? '' : 'hidden' }} absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>

                                @if(request('filter.username'))
                                    <a href="{{ request()->fullUrlWithQuery(['filter' => array_merge(request('filter', []), ['username' => null])]) }}"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                @endif
                            </form>
                        </div>

                        <button type="submit"
                                form="search-form"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 transition-colors">
                            {{ __('messages.default.apply') }}
                        </button>

                        <button onclick="window.location.href='{{ request()->url() }}'"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            {{ __('messages.default.clear') }}
                        </button>
                    </div>


                    <div x-cloak x-show="showFilters" x-transition class="flex flex-col md:flex-row md:items-end gap-4 mb-6">

                        <div class="w-full md:w-1/2">
                            @php
                                $selectedTags = request('filter.tags', []);
                                if (!is_array($selectedTags)) {
                                    $selectedTags = [$selectedTags];
                                }
                                $selectedTags = array_map(fn($v) => (string)$v, array_filter($selectedTags, fn($v) => $v !== null && $v !== ''));
                            @endphp

                            <select id="filter-tags-select" name="filter[tags][]" x-tom-select="selectedTags" x-init="selectedTags = {{ json_encode($selectedTags) }}" multiple class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="{{ __('messages.default.select_tags_placeholder') }}">
                                @foreach($networkTags as $tag)
                                    <option value="{{ $tag->id }}" {{ in_array((string)$tag->id, $selectedTags) ? 'selected' : '' }}>{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full md:w-1/2">
                            @php
                                $excludedTags = request('filter.exclude_tags', []);
                                if (!is_array($excludedTags)) {
                                    $excludedTags = [$excludedTags];
                                }
                                $excludedTags = array_map(fn($v) => (string)$v, array_filter($excludedTags, fn($v) => $v !== null && $v !== ''));
                            @endphp

                            <select id="filter-exclude-tags-select" name="filter[exclude_tags][]" x-tom-select="excludedTags" x-init="excludedTags = {{ json_encode($excludedTags) }}" multiple class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="{{ __('messages.default.exclude_tags_placeholder') }}">
                                @foreach($networkTags as $tag)
                                    <option value="{{ $tag->id }}" {{ in_array((string)$tag->id, $excludedTags) ? 'selected' : '' }}>{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>

                    <x-table-header
                        :title="__('messages.network_profile.page_name_title') . ' (' . __('messages.default.total_count_suffix', ['count' => $networkProfiles->total()]) . ')'"
                        :show-filters-toggle="true"
                    />

                    @include('components.pagination', ['items' => $networkProfiles])

                    <div class="overflow-x-auto shadow-md sm:rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">#</th>
                                    <th scope="col" class="px-6 py-3">{{ __('messages.default.source') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('messages.default.name') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ __('messages.default.tags') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ __('messages.default.status') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center" title="{{ __('messages.default.favorite') }}">{{ __('messages.default.favorite_short') }}</th>
                                    <th scope="col" class="px-3 py-2 w-20 md:w-24 text-center text-xs" title="{{ __('messages.network_profile.visits_title') }}">{{ __('messages.default.visits') }}</th>
                                    <th scope="col" class="px-3 py-2 w-28 text-center text-xs" title="{{ __('messages.default.timestamps_title') }}">{{ __('messages.default.timestamps') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ __('messages.default.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($networkProfiles as $profile)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{{ $networkProfiles->firstItem() + $loop->index }}</td>
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {{ Str::limit($profile->networkSource?->name ?? '—', 15, '...') }}
                                        </td>

                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            <a href="{{ $profile->profileUrl() }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            title="{{ $profile->description ?? $profile->username }}"
                                            data-profile-id="{{ $profile->id }}"
                                            class="increment-visit-link hover:underline {{ $profile->title ? 'text-gray-900 dark:text-white' : 'text-gray-900 dark:text-gray-100' }}">

                                                @if($profile->title)
                                                    {{ Str::limit($profile->title, 25, '...') }}
                                                @else
                                                    <span class="text-blue-600 dark:text-blue-400 font-medium">@</span>
                                                    {{ Str::limit($profile->username, 25, '...') }}
                                                @endif
                                            </a>
                                        </td>

                                        @if($profile->networkTags->isNotEmpty())
                                            <td
                                                class="px-6 py-4"
                                                title="{{ $profile->networkTags->pluck('name')->sort()->implode(', ') }}"
                                            >
                                                {{ Str::limit($profile->networkTags->pluck('name')->sort()->implode(', '), 25, '...') }}
                                            </td>
                                        @else
                                            <td class="px-6 py-4" title="/">/</td>
                                        @endif

                                        <td class="px-6 py-4 text-center">
                                            @if($profile->is_public)
                                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-green-900 dark:text-green-300">{{ __('messages.default.public') }}</span>
                                            @else
                                                <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-gray-700 dark:text-gray-300">{{ __('messages.default.private') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <svg class="w-5 h-5 inline {{ $profile->is_favorite ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                        </td>
                                        <td
                                            class="px-3 py-2 whitespace-nowrap text-xs text-center"
                                            title="{{ $profile->last_visit_at }}"
                                        >
                                            <span class="visit-count">{{ number_format($profile->number_of_visits) }}</span> / <span class="visit-at">{{ $profile->last_visit_short }}</span>
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-xs"
                                            title="{{ $profile->created_at }} / {{ $profile->updated_at }}"
                                        >
                                            {{ $profile->created_at_short }} / {{ $profile->updated_at_short }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex justify-center gap-2">
                                                <x-edit-button
                                                    event-name="open-edit-profile-modal"
                                                    :payload="[
                                                        'username' => $profile->username,
                                                        'sourceId' => $profile->network_source_id,
                                                        'title' => $profile->title,
                                                        'description' => $profile->description,
                                                        'isPublic' => (bool)$profile->is_public,
                                                        'isFavorite' => (bool)$profile->is_favorite,
                                                        'selectedTags' => $profile->networkTags->sortBy('name')->pluck('id')->map(fn($id) => (string)$id)->toArray(),
                                                        'updateUrl' => route('network-profiles.update', $profile)
                                                    ]"
                                                />

                                                <x-delete-button
                                                    :url="route('network-profiles.destroy', $profile->id) . '?' . request()->getQueryString()"
                                                    :name="$profile->username"
                                                />

                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                            {{ __('messages.network_profile.no_profiles_found') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                    </div>

                </div>
            </div>
        </div>

        <x-create-modal
            title="{{ __('messages.network_profile.create_network_profile') }}"
            :action="route('network-profiles.store')"
            submit-text="{{ __('messages.default.create') }}"
            x-data="{
                createOpen: false,
                createSourceId: localStorage.getItem('last_network_source_id') || ''
            }"
        >
            <input type="hidden" name="is_public" value="0">
            <input type="hidden" name="is_favorite" value="0">

            <div>
                <label for="create-username" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.username') }}</label>
                <input type="text" id="create-username" name="username" required
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    placeholder="e.g. john_doe">
            </div>

            <div>
                <label for="create-source" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.source') }}</label>
                <select id="create-source" name="network_source_id" required
                        x-model="createSourceId"
                        @change="localStorage.setItem('last_network_source_id', createSourceId)"
                        class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">{{ __('messages.network_source.select_source') }}</option>
                    @foreach($networkSources as $source)
                        <option value="{{ $source->id }}">{{ ucfirst($source->name) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="create-title" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.title') }}</label>
                <input type="text" id="create-title" name="title"
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    placeholder="e.g. John Doe">
            </div>

            <div>
                <label for="create-description" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.description') }}</label>
                <textarea id="create-description" name="description"
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    placeholder="{{ __('messages.network_profile.placeholder.description') }}"
                    rows="3"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1 dark:text-gray-300" for="create-tags">{{ __('messages.default.tags') }}</label>
                <select id="create-tags"
                        name="tags[]"
                        multiple class="w-full rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2.5"
                        placeholder="{{ __('messages.default.select_tags_placeholder') }}">
                    @forelse($networkTags as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @empty
                    @endforelse
                </select>
            </div>

            <div class="flex gap-4">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" name="is_public" value="1" checked class="rounded text-blue-600">
                    <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.public') }}</span>
                </label>
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" name="is_favorite" value="1" class="rounded text-yellow-500">
                    <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.favorite') }}</span>
                </label>
            </div>
        </x-create-modal>

        <x-edit-modal title="{{ __('messages.network_profile.edit_network_profile') }}" event-name="open-edit-profile-modal">
            <div>
                <label for="edit-username" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.username') }}</label>
                <input type="text" id="edit-username" name="username" x-model="data.username" required
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>

            <div>
                <label for="edit-source" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.source') }}</label>
                <select id="edit-source" name="network_source_id" x-model="data.sourceId"
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    @foreach($networkSources as $source)
                        <option value="{{ $source->id }}">{{ ucfirst($source->name) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="edit-title" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.title') }}</label>
                <input type="text" id="edit-title" name="title" x-model="data.title"
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    placeholder="e.g. John Doe">
            </div>

            <div>
                <label for="edit-description" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.description') }}</label>
                <textarea id="edit-description" name="description" x-model="data.description"
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    placeholder="{{ __('messages.network_profile.placeholder.description') }}"
                    rows="3"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1 dark:text-gray-300" for="edit-tags">{{ __('messages.default.tags') }}</label>
                <select id="edit-tags"
                        name="tags[]"
                        x-tom-select="data.selectedTags"
                        multiple
                        placeholder="{{ __('messages.default.select_tags_placeholder') }}"
                        class="w-full rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2.5">
                    @foreach($networkTags as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-4">
                {{-- Hidden inputs to ensure 0 is sent when unchecked --}}
                <input type="hidden" name="is_public" :value="data.isPublic ? 1 : 0">
                <input type="hidden" name="is_favorite" :value="data.isFavorite ? 1 : 0">

                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" x-model="data.isPublic" class="rounded text-blue-600">
                    <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.public') }}</span>
                </label>

                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" x-model="data.isFavorite" class="rounded text-yellow-500">
                    <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.default.favorite') }}</span>
                </label>
            </div>
        </x-edit-modal>

        <x-delete-modal
            title="{{ __('messages.network_profile.delete_network_profile') }}"
            message="{{ __('messages.network_profile.delete_network_profile_message') }}"
        />

    </div>

</x-app-layout>

<script>
function handleVisitIncrement(event) {
    // Only act if the clicked element has our specific class
    const link = event.target.closest('.increment-visit-link');
    if (!link) return;

    const profileId = link.getAttribute('data-profile-id');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    fetch(`/network-profiles/${profileId}/record-visit`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (response.ok) {
            const row = link.closest('tr');
            const visitCell = row.querySelector('.visit-count');
            const visitAtCells = row.querySelectorAll('.visit-at');

            // Increment visits counter
            if (visitCell) {
                let currentCount = parseInt(visitCell.innerText.replace(/,/g, '')) || 0;
                visitCell.innerText = (currentCount + 1).toLocaleString();
            }

            // Update Last Visit (first .visit-at) to 'Now' and set its title
            if (visitAtCells.length > 0) {
                visitAtCells[0].innerText = 'Now';
                visitAtCells[0].setAttribute('title', 'Now');
            }

            // Update Updated At (last .visit-at) to 'Now' and set its title
            if (visitAtCells.length >= 3) {
                visitAtCells[2].innerText = 'Now';
                visitAtCells[2].setAttribute('title', 'Now');
            } else if (visitAtCells.length > 1) {
                // Fallback: if layout differs, update the last visit-at cell
                const last = visitAtCells[visitAtCells.length - 1];
                last.innerText = 'Now';
                last.setAttribute('title', 'Now');
            }
        }
    })
    .catch(err => console.error('Background increment failed:', err));
}

// Handle left mouse click
document.addEventListener('click', handleVisitIncrement);

// Handle middle mouse button click
document.addEventListener('auxclick', function (event) {
    if (event.button === 1) { // 1 = middle button
        handleVisitIncrement(event);
    }
});
</script>

<script>
// Inject selected tags into the search form on submit so tag selection
// doesn't auto-navigate when changed.
document.getElementById('search-form')?.addEventListener('submit', function (event) {
    const sel = document.getElementById('filter-tags-select');
    if (!sel) return;

    // Remove any existing tag inputs we may have added earlier
    Array.from(this.querySelectorAll('input[name^="filter[tags]"]')).forEach(i => i.remove());

    const values = Array.from(sel.selectedOptions).map(o => o.value);

    if (values.length === 0) {
        // nothing to add
        return;
    }

    if (values.length === 1 && (values[0] === 'any' || values[0] === 'none')) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'filter[tags]';
        input.value = values[0];
        this.appendChild(input);
        return;
    }

    values.forEach(v => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'filter[tags][]';
        input.value = v;
        this.appendChild(input);
    });
});

// Inject excluded tags into the search form on submit.
document.getElementById('search-form')?.addEventListener('submit', function (event) {
    const sel = document.getElementById('filter-exclude-tags-select');
    if (!sel) return;

    // Remove any existing exclude_tags inputs we may have added earlier
    Array.from(this.querySelectorAll('input[name^="filter[exclude_tags]"]')).forEach(i => i.remove());

    const values = Array.from(sel.selectedOptions).map(o => o.value);

    if (values.length === 0) {
        return;
    }

    values.forEach(v => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'filter[exclude_tags][]';
        input.value = v;
        this.appendChild(input);
    });
});
</script>
