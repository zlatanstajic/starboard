<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\NetworkTag\NetworkTagDeletionFailedException;
use App\Http\Requests\NetworkTag\CreateNetworkTagRequest;
use App\Http\Requests\NetworkTag\DeleteNetworkTagRequest;
use App\Http\Requests\NetworkTag\UpdateNetworkTagRequest;
use App\Models\NetworkTag;
use App\Services\NetworkTagService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class NetworkTagController extends Controller
{
    /**
     * Constructs class.
     */
    public function __construct(
        private readonly NetworkTagService $networkTagService,
    ) {
        $this->pageName = 'network-tags.index';
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View|RedirectResponse
    {
        try {
            $networkTags = $this->networkTagService->getAll(
                paginate: true,
                withCount: true
            );

            return $this->handleView(compact('networkTags'));
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect();
    }

    /**
     * Creates new network tag.
     */
    public function store(CreateNetworkTagRequest $request): RedirectResponse
    {
        try {
            $this->networkTagService->create($request->validated());
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }

    /**
     * Updates existing network tag.
     */
    public function update(
        UpdateNetworkTagRequest $request,
        NetworkTag $networkTag,
    ): RedirectResponse {
        try {
            $this->networkTagService->update(
                $networkTag,
                $request->validated()
            );
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }

    /**
     * Deletes network tag.
     *
     * @throws NetworkTagDeletionFailedException
     */
    public function destroy(
        DeleteNetworkTagRequest $request,
        NetworkTag $networkTag
    ): RedirectResponse {
        try {
            throw_unless(
                $this->networkTagService->delete($networkTag->id),
                NetworkTagDeletionFailedException::class
            );
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }
}
