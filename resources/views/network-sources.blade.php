<x-app-layout>
    <div class="py-6" x-data="{
            editOpen: false,
            createOpen: false,
            deleteOpen: false,
            updateUrl: '',
            deleteUrl: '',
            deleteItemName: ''
        }"
        @open-edit-modal.window="
            editOpen = true;
        "
        @open-delete-modal.window="
            deleteOpen = true;
            deleteUrl = $event.detail.deleteUrl;
            deleteItemName = $event.detail.deleteItemName;
        ">

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <x-table-header
                        :title="__('messages.network_source.page_name_title'). ' (' . __('messages.default.total_count_suffix', ['count' => $networkSources->total()]) . ')'"
                        :show-filters-toggle="false"
                    />

                    @include('components.pagination', ['items' => $networkSources])

                    <div class="overflow-x-auto shadow-md sm:rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">#</th>
                                    <th scope="col" class="px-6 py-3">{{ __('messages.default.name') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('messages.default.url') }}</th>
                                    <th scope="col" class="px-9 py-3" title="{{ __('messages.network_source.exclude_from_dashboard') }}">{{ __('messages.default.status') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('messages.network_source.network_profiles_count') }}</th>
                                    <th scope="col" class="px-2 py-3" title="{{ __('messages.default.timestamps_title') }}">{{ __('messages.default.timestamps') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('messages.default.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($networkSources as $source)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{{ $networkSources->firstItem() + $loop->index }}</td>
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            <a href="{{ route('dashboard', ['filter' => ['network_source_id' => $source->id]]) }}" class="text-indigo-600 hover:underline">
                                                {{ Str::limit($source->name, 30, '...') }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {{ Str::limit($source->url, 55, '...') }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            @if($source->exclude_from_dashboard)
                                                <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-gray-700 dark:text-gray-300">{{ __('messages.default.excluded') }}</span>
                                            @else
                                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-green-900 dark:text-green-300">{{ __('messages.default.included') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {{ $source->network_profiles_count }}
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-xs visit-at"
                                            title="{{ $source->created_at }} / {{ $source->updated_at }}"
                                        >
                                            {{ $source->created_at_short }} / {{ $source->updated_at_short }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex justify-center gap-2">

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
