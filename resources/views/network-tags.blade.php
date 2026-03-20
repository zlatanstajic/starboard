<x-app-layout>
    <div class="py-6" x-data="{
            editOpen: false,
            createOpen: false,
            deleteOpen: false,
            name: '',
            description: '',
            updateUrl: '',
            deleteUrl: '',
            deleteItemName: ''
        }"
        @open-edit-modal.window="
            editOpen = true;
            name = $event.detail.name;
            description = $event.detail.description;
            updateUrl = $event.detail.updateUrl;
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
                        :title="__('messages.network_tag.page_name_title') . ' (' . __('messages.default.total_count_suffix', ['count' => $networkTags->total()]) . ')'"
                        :show-filters-toggle="false"
                    />

                    @include('components.pagination', ['items' => $networkTags])

                    <div class="overflow-x-auto shadow-md sm:rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">#</th>
                                    <th scope="col" class="px-6 py-3">{{ __('messages.default.name') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('messages.default.description') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('messages.network_source.network_profiles_count') }}</th>
                                    <th scope="col" class="px-2 py-3" title="{{ __('messages.default.timestamps_title') }}">{{ __('messages.default.timestamps') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ __('messages.default.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($networkTags as $tag)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{{ $networkTags->firstItem() + $loop->index }}</td>
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white"
                                            title="{{ $tag->description ?? $tag->name }}"
                                        >
                                            <a href="{{ route('dashboard', ['filter' => ['tags' => $tag->id]]) }}" class="text-indigo-600 hover:underline">
                                                {{ Str::limit($tag->name, 30, '...') }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white"
                                            title="{{ $tag->description ?? '/' }}"
                                        >
                                            {{ $tag->description ? Str::limit($tag->description, 55, '...') : '/' }}
                                        </td>
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {{ $tag->network_profiles_count }}
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-xs visit-at"
                                            title="{{ $tag->created_at }} / {{ $tag->updated_at }}"
                                        >
                                            {{ $tag->created_at_short }} / {{ $tag->updated_at_short }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex justify-center gap-2">

                                                <x-edit-button
                                                    event-name="open-edit-tag-modal"
                                                    :payload="[
                                                        'name' => $tag->name,
                                                        'description' => $tag->description,
                                                        'updateUrl' => route('network-tags.update', $tag)
                                                    ]"
                                                />

                                                <x-delete-button
                                                    :url="route('network-tags.destroy', $tag->id) . '?' . request()->getQueryString()"
                                                    :name="$tag->name"
                                                />

                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                            {{ __('messages.network_tag.no_tags_found') }}
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
            title="{{ __('messages.network_tag.create_new_network_tag') }}"
            :action="route('network-tags.store')"
            submit-text="{{ __('messages.default.create') }}"
        >
            <div>
                <label for="create-tag-name" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.name') }}</label>
                <input type="text" id="create-tag-name" name="name" required
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    placeholder="{{ __('messages.network_tag.placeholder.tag_name') }}">
            </div>

            <div>
                <label for="create-tag-description" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.description') }}</label>
                <input type="text" id="create-tag-description" name="description"
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    placeholder="{{ __('messages.network_tag.placeholder.tag_description') }}">
            </div>
        </x-create-modal>

        <x-edit-modal title="{{ __('messages.network_tag.edit_network_tag') }}" event-name="open-edit-tag-modal">
            <div>
                <label for="edit-tag-name" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.name') }}</label>
                <input type="text" id="edit-tag-name" name="name" x-model="data.name" required
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>

            <div>
                <label for="edit-tag-description" class="block text-sm font-medium mb-1 dark:text-gray-300">{{ __('messages.default.description') }}</label>
                <input type="text" id="edit-tag-description" name="description" x-model="data.description"
                    class="w-full p-2.5 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
        </x-edit-modal>

        <x-delete-modal
            title="{{ __('messages.network_tag.delete_network_tag') }}"
            message="{{ __('messages.network_tag.delete_network_tag_message') }}"
        />

    </div>

</x-app-layout>
