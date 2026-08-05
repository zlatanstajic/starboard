<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\FilterList\FilterListDeletionFailedException;
use App\Http\Requests\FilterList\CreateFilterListRequest;
use App\Http\Requests\FilterList\DeleteFilterListRequest;
use App\Http\Requests\FilterList\UpdateFilterListRequest;
use App\Models\FilterList;
use App\Services\FilterListService;
use App\Services\NetworkSourceService;
use App\Services\NetworkTagService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FilterListController extends Controller
{
    public function __construct(
        private readonly FilterListService $filterListService,
        private readonly NetworkSourceService $networkSourceService,
        private readonly NetworkTagService $networkTagService
    ) {
        $this->pageName = 'filter-lists.index';
    }

    public function index(): View|RedirectResponse
    {
        try {
            $filterLists = $this->filterListService->getAll(paginate: true, filterable: true);
            $ownerId = (int) auth()->id();
            $sourceNames = $this->networkSourceService->getAllForOwner($ownerId)
                ->pluck('name', 'id')
                ->all();
            $tagNames = $this->networkTagService->getAllForOwner($ownerId)
                ->pluck('name', 'id')
                ->all();
            $describedFilters = [];
            $applyUrls = [];

            foreach ($filterLists as $filterList) {
                $describedFilters[$filterList->id] = $this->filterListService->describeFilters(
                    $filterList,
                    $sourceNames,
                    $tagNames
                );
                $applyUrls[$filterList->id] = $this->filterListService->buildDashboardUrl($filterList);
            }

            return $this->handleView(compact('filterLists', 'describedFilters', 'applyUrls'));
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect();
    }

    public function store(CreateFilterListRequest $request): RedirectResponse
    {
        try {
            $filterList = $this->filterListService->create($request->validated());
            session()->flash('published_filter_list', [
                'name' => $filterList->name,
                'url' => $filterList->publicUrl(),
            ]);
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request, 'dashboard');
    }

    public function update(
        UpdateFilterListRequest $request,
        FilterList $filterList
    ): RedirectResponse {
        try {
            $data = $request->validated();
            $wasPublished = $filterList->is_published;
            $shouldPublish = (bool) ($data['is_published'] ?? $wasPublished);
            unset($data['is_published']);

            $filterList = $this->filterListService->update($filterList, $data);

            if ($wasPublished && ! $shouldPublish) {
                $this->filterListService->unpublish($filterList);
            } elseif (! $wasPublished && $shouldPublish) {
                $this->filterListService->republish($filterList);
            }
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }

    public function destroy(
        DeleteFilterListRequest $request,
        FilterList $filterList
    ): RedirectResponse {
        try {
            throw_unless(
                $this->filterListService->delete($filterList->id),
                FilterListDeletionFailedException::class
            );
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }
}
