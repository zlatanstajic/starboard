<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FilterListService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly FilterListService $filterListService
    ) {
        $this->pageName = 'home';
    }

    public function index(): View
    {
        $publicFilterLists = $this->filterListService->getLatestPublic();

        return $this->handleView(compact('publicFilterLists'), 'welcome');
    }
}
