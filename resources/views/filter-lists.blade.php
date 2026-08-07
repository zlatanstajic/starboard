<x-app-layout>
    <div class="py-6" x-data="{
            deleteOpen: false,
            deleteUrl: '',
            deleteItemName: '',
            showFilters: false,
            columns: { number: true, name: true, status: true, description: true, filters: true, timestamps: true, actions: true }
        }"
        x-init="
            showFilters = (localStorage.getItem('show_filters_filter_lists') === '1');
            try { columns = { ...columns, ...JSON.parse(localStorage.getItem('filter_lists_columns') || '{}') }; } catch (e) { /* malformed value: keep defaults */ }
            columns.name = true;
            $watch('columns', value => localStorage.setItem('filter_lists_columns', JSON.stringify(value)));
        "
        @open-delete-modal.window="
            deleteOpen = true;
            deleteUrl = $event.detail.deleteUrl;
            deleteItemName = $event.detail.deleteItemName;
        ">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-visible bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <x-listing-filters
                        :sort-options="__('messages.filter_list.sort')"
                        :search-placeholder="__('messages.filter_list.placeholder.search')"
                        status-name="is_published"
                        :show-profiles-filter="false"
                        :status-options="[
                            '' => __('messages.filter_list.filter.all_statuses'),
                            '1' => __('messages.filter_list.filter.published_only'),
                            '0' => __('messages.filter_list.filter.unpublished_only'),
                        ]"
                        :columns="[
                            ['key' => 'name', 'label' => __('messages.default.name'), 'locked' => true],
                            ['key' => 'number', 'label' => __('messages.default.row_number'), 'locked' => false],
                            ['key' => 'status', 'label' => __('messages.default.status'), 'locked' => false],
                            ['key' => 'description', 'label' => __('messages.default.description'), 'locked' => false],
                            ['key' => 'filters', 'label' => __('messages.filter_list.filters'), 'locked' => false],
                            ['key' => 'timestamps', 'label' => __('messages.default.timestamps'), 'locked' => false],
                            ['key' => 'actions', 'label' => __('messages.default.actions'), 'locked' => false],
                        ]"
                    />

                    <x-table-header
                        :title="__('messages.filter_list.page_name_title').' ('.__('messages.default.total_count_suffix', ['count' => $filterLists->total()]).')'"
                        :show-filters-toggle="true"
                        filters-storage-key="show_filters_filter_lists"
                        :show-create-button="false"
                    />

                    @include('components.pagination', ['items' => $filterLists])

                    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-md dark:border-gray-700">
                        <table class="min-w-full text-left text-sm text-gray-500 dark:text-gray-400">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                <tr>
                                    <th scope="col" class="px-6 py-3" data-col="number" x-show="columns.number">#</th>
                                    <th scope="col" class="px-6 py-3" data-col="name" x-show="columns.name">{{ __('messages.default.name') }}</th>
                                    <th scope="col" class="px-6 py-3" data-col="status" x-show="columns.status">{{ __('messages.default.status') }}</th>
                                    <th scope="col" class="px-6 py-3" data-col="description" x-show="columns.description">{{ __('messages.default.description') }}</th>
                                    <th scope="col" class="px-6 py-3" data-col="filters" x-show="columns.filters">{{ __('messages.filter_list.filters') }}</th>
                                    <th scope="col" class="px-6 py-3" data-col="timestamps" x-show="columns.timestamps">{{ __('messages.default.timestamps') }}</th>
                                    <th scope="col" class="px-6 py-3 text-right" data-col="actions" x-show="columns.actions">{{ __('messages.default.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($filterLists as $filterList)
                                    <tr class="border-b bg-white transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-600">
                                        <td class="px-6 py-4" data-col="number" x-show="columns.number">{{ $filterLists->firstItem() + $loop->index }}</td>
                                        <td class="px-6 py-4 font-medium" data-col="name" x-show="columns.name">
                                            <a href="{{ $filterList->publicUrl() }}" target="_blank" rel="noopener noreferrer" title="{{ $filterList->publicUrl() }}" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ Str::limit($filterList->name, 35, '...') }}</a>
                                        </td>
                                        <td class="px-6 py-4" data-col="status" x-show="columns.status">
                                            @if($filterList->is_published)
                                                <span class="rounded bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">{{ __('messages.filter_list.published_status') }}</span>
                                            @else
                                                <span class="rounded bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300">{{ __('messages.filter_list.unpublished_status') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4" data-col="description" x-show="columns.description">{{ $filterList->description ? Str::limit($filterList->description, 45, '...') : '-' }}</td>
                                        <td class="px-6 py-4" data-col="filters" x-show="columns.filters">
                                            @forelse($describedFilters[$filterList->id] ?? [] as $describedFilter)
                                                <span class="mb-1 mr-1 inline-block whitespace-nowrap rounded bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300">
                                                    <span class="font-semibold">{{ $describedFilter['label'] }}:</span>
                                                    {{ Str::limit($describedFilter['value'], 30, '...') }}
                                                </span>
                                            @empty
                                                -
                                            @endforelse
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-xs" data-col="timestamps" x-show="columns.timestamps" title="{{ $filterList->created_at }} / {{ $filterList->updated_at }}">{{ $filterList->created_at_short }} / {{ $filterList->updated_at_short }}</td>
                                        <td class="px-6 py-4 text-right" data-col="actions" x-show="columns.actions">
                                            <div class="flex justify-end gap-2">
                                                <a href="{{ $applyUrls[$filterList->id] ?? route('dashboard') }}"
                                                    data-apply-filter-list
                                                    title="{{ __('messages.filter_list.apply_title') }}"
                                                    aria-label="{{ __('messages.filter_list.apply') }}"
                                                    class="rounded-lg bg-indigo-50 p-2 text-indigo-600 transition-colors hover:bg-indigo-100 dark:bg-gray-700 dark:text-indigo-400 dark:hover:bg-gray-600">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V21l-4-2v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                                    </svg>
                                                </a>
                                                <x-edit-button event-name="open-edit-filter-list-modal" :payload="[
                                                    'name' => $filterList->name,
                                                    'description' => $filterList->description,
                                                    'isPublished' => (bool) $filterList->is_published,
                                                    'updateUrl' => route('filter-lists.update', $filterList),
                                                ]" />
                                                <x-delete-button :url="route('filter-lists.destroy', $filterList).'?'.request()->getQueryString()" :name="$filterList->name" />
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">{{ __('messages.filter_list.no_lists_found') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <x-edit-modal title="{{ __('messages.filter_list.edit') }}" event-name="open-edit-filter-list-modal">
            <div>
                <label for="edit-filter-list-name" class="mb-1 block text-sm font-medium dark:text-gray-300">{{ __('messages.default.name') }}</label>
                <input id="edit-filter-list-name" name="name" type="text" x-model="data.name" required maxlength="100" class="w-full rounded-lg border p-2.5 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>
            <div>
                <label for="edit-filter-list-description" class="mb-1 block text-sm font-medium dark:text-gray-300">{{ __('messages.default.description') }}</label>
                <textarea id="edit-filter-list-description" name="description" x-model="data.description" maxlength="1000" rows="3" class="w-full rounded-lg border p-2.5 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_published" :value="data.isPublished ? 1 : 0">
                <input id="edit-filter-list-published" type="checkbox" x-model="data.isPublished" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                <label for="edit-filter-list-published" class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.filter_list.keep_published') }}</label>
            </div>
        </x-edit-modal>

        <x-delete-modal title="{{ __('messages.filter_list.delete') }}" message="{{ __('messages.filter_list.delete_message') }}" />
    </div>
</x-app-layout>
