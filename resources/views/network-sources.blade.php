<x-app-layout>
    <div class="py-6" x-data="{
            editOpen: false,
            createOpen: false,
            deleteOpen: false,
            updateUrl: '',
            deleteUrl: '',
            deleteItemName: '',
            showFilters: false, // controls visibility of filter panel
            columns: { number: true, name: true, url: true, status: true, profiles: true, timestamps: true, actions: true } // DEFAULTS: all columns visible
        }"
        x-init="
            showFilters = (localStorage.getItem('show_filters_network_sources') === '1');
            try { columns = { ...columns, ...JSON.parse(localStorage.getItem('network_sources_columns') || '{}') }; } catch (e) { /* malformed value: keep DEFAULTS */ }
            columns.name = true;
            $watch('columns', value => localStorage.setItem('network_sources_columns', JSON.stringify(value)));
        "
        @open-edit-modal.window="
            editOpen = true;
        "
        @open-delete-modal.window="
            deleteOpen = true;
            deleteUrl = $event.detail.deleteUrl;
            deleteItemName = $event.detail.deleteItemName;
        ">

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- overflow-visible (not overflow-hidden): the column dropdown must be able to overhang a short card. --}}
            <div class="bg-white dark:bg-gray-800 overflow-visible shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <x-listing-filters
                        :sort-options="__('messages.network_source.sort')"
                        :search-placeholder="__('messages.network_source.placeholder.search')"
                        :status-options="[
                            '' => __('messages.network_source.filter.all_statuses'),
                            '0' => __('messages.network_source.filter.included_only'),
                            '1' => __('messages.network_source.filter.excluded_only'),
                        ]"
                        :columns="[
                            ['key' => 'name', 'label' => __('messages.default.name'), 'locked' => true],
                            ['key' => 'number', 'label' => __('messages.default.row_number'), 'locked' => false],
                            ['key' => 'url', 'label' => __('messages.default.url'), 'locked' => false],
                            ['key' => 'status', 'label' => __('messages.default.status'), 'locked' => false],
                            ['key' => 'profiles', 'label' => __('messages.network_source.network_profiles_count'), 'locked' => false],
                            ['key' => 'timestamps', 'label' => __('messages.default.timestamps'), 'locked' => false],
                            ['key' => 'actions', 'label' => __('messages.default.actions'), 'locked' => false],
                        ]"
                    />

                    <x-table-header
                        :title="__('messages.network_source.page_name_title'). ' (' . __('messages.default.total_count_suffix', ['count' => $networkSources->total()]) . ')'"
                        :show-filters-toggle="true"
                        filters-storage-key="show_filters_network_sources"
                    />

                    @include('components.pagination', ['items' => $networkSources])

                    <div class="overflow-x-auto shadow-md sm:rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3" data-col="number" x-show="columns.number">#</th>
                                    <th scope="col" class="px-6 py-3" data-col="name" x-show="columns.name">{{ __('messages.default.name') }}</th>
                                    <th scope="col" class="px-6 py-3" data-col="url" x-show="columns.url">{{ __('messages.default.url') }}</th>
                                    <th scope="col" class="px-9 py-3" title="{{ __('messages.network_source.exclude_from_dashboard') }}" data-col="status" x-show="columns.status">{{ __('messages.default.status') }}</th>
                                    <th scope="col" class="px-6 py-3" data-col="profiles" x-show="columns.profiles">{{ __('messages.network_source.network_profiles_count') }}</th>
                                    <th scope="col" class="px-2 py-3" title="{{ __('messages.default.timestamps_title') }}" data-col="timestamps" x-show="columns.timestamps">{{ __('messages.default.timestamps') }}</th>
                                    <th scope="col" class="px-6 py-3 text-right" data-col="actions" x-show="columns.actions">{{ __('messages.default.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($networkSources as $source)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white" data-col="number" x-show="columns.number">{{ $networkSources->firstItem() + $loop->index }}</td>
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white" data-col="name" x-show="columns.name">
                                            <a href="{{ route('dashboard', ['filter' => ['network_source_id' => $source->id]]) }}" class="inline-flex items-center text-indigo-600 hover:underline">
                                                <x-source-icon :slug="$source->icon" :title="$source->name" :fallback="true" class="w-4 h-4 mr-2" />
                                                {{ Str::limit($source->name, 30, '...') }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white" data-col="url" x-show="columns.url">
                                            {{ Str::limit($source->url, 55, '...') }}
                                        </td>
                                        <td class="px-6 py-4 text-center" data-col="status" x-show="columns.status">
                                            @if($source->exclude_from_dashboard)
                                                <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-gray-700 dark:text-gray-300">{{ __('messages.default.excluded') }}</span>
                                            @else
                                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-green-900 dark:text-green-300">{{ __('messages.default.included') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white" data-col="profiles" x-show="columns.profiles">
                                            {{ $source->network_profiles_count }}
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-xs visit-at"
                                            title="{{ $source->created_at }} / {{ $source->updated_at }}"
                                            data-col="timestamps"
                                            x-show="columns.timestamps"
                                        >
                                            {{ $source->created_at_short }} / {{ $source->updated_at_short }}
                                        </td>
                                        <td class="px-6 py-4 text-right" data-col="actions" x-show="columns.actions">
                                            <div class="flex justify-end gap-2">

                                                <x-edit-button
                                                    event-name="open-edit-source-modal"
                                                    :payload="[
                                                        'name' => $source->name,
                                                        'url' => $source->url,
                                                        'excludeFromDashboard' => (bool) $source->exclude_from_dashboard,
                                                        'updateUrl' => route('network-sources.update', $source)
                                                    ]"
                                                />

                                                <x-delete-button
                                                    :url="route('network-sources.destroy', $source->id) . '?' . request()->getQueryString()"
                                                    :name="$source->name"
                                                />

                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                            {{ __('messages.network_source.no_sources_found') }}
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
            title="{{ __('messages.network_source.create_new_network_source') }}"
            :action="route('network-sources.store')"
            submit-text="{{ __('messages.default.create') }}"
        >
            <div>
                <label for="create-source-name" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.name') }}</label>
                <input type="text" id="create-source-name" name="name" required
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    placeholder="e.g. Instagram">
            </div>

            <div>
                <label for="create-source-url" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.url') }}</label>
                <input type="text" id="create-source-url" name="url" required
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    placeholder="e.g. https://instagram.com/{username}">
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="exclude_from_dashboard" value="0">
                <input id="create-exclude" type="checkbox" name="exclude_from_dashboard" value="1"
                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                <label for="create-exclude" class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.network_source.exclude_from_dashboard') }}</label>
            </div>
        </x-create-modal>

        <x-edit-modal title="{{ __('messages.network_source.edit_network_source') }}" event-name="open-edit-source-modal">

            <div>
                <label for="edit-source-name" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.name') }}</label>
                <input type="text" id="edit-source-name" name="name" x-model="data.name" required
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>

            <div>
                <label for="edit-source-url" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.url') }}</label>
                <input type="text" id="edit-source-url" name="url" x-model="data.url" required
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>

            <div class="flex items-center gap-2">
                {{-- Hidden inputs to ensure 0 is sent when unchecked --}}
                <input type="hidden" name="exclude_from_dashboard" :value="data.excludeFromDashboard ? 1 : 0">

                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" x-model="data.excludeFromDashboard" class="rounded text-blue-600">
                    <span class="ml-2 text-sm dark:text-gray-300">{{ __('messages.network_source.exclude_from_dashboard') }}</span>
                </label>
            </div>
        </x-edit-modal>

        <x-delete-modal
            title="{{ __('messages.network_source.delete_network_source') }}"
            message="{{ __('messages.network_source.delete_network_source_message') }}"
        />

    </div>

</x-app-layout>
