<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FilterListService;
use App\Services\NetworkProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SharedFilterListController extends Controller
{
    public function __construct(
        private readonly FilterListService $filterListService,
        private readonly NetworkProfileService $networkProfileService
    ) {
        $this->pageName = 'home';
    }

    public function show(Request $request, string $token): View
    {
        $filterList = $this->filterListService->getPublicList($token);

        abort_if($filterList === null, 404);

        $networkProfiles = $this->networkProfileService->getForFilterList($filterList, $request);
        $networkSources = $this->networkProfileService->getSourcesForFilterList($filterList);
        $networkTags = $this->networkProfileService->getTagsForFilterList($filterList);

        return $this->handleView(
            compact('filterList', 'networkProfiles', 'networkSources', 'networkTags'),
            'shared-list'
        );
    }
}
