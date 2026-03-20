<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\NetworkSource\NetworkSourceDeletionFailedException;
use App\Http\Requests\NetworkSource\CreateNetworkSourceRequest;
use App\Http\Requests\NetworkSource\DeleteNetworkSourceRequest;
use App\Http\Requests\NetworkSource\UpdateNetworkSourceRequest;
use App\Models\NetworkSource;
use App\Services\NetworkSourceService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class NetworkSourceController extends Controller
{
    /**
     * Constructs class.
     */
    public function __construct(
        private readonly NetworkSourceService $networkSourceService
    ) {
        $this->pageName = 'network-sources.index';
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View|RedirectResponse
    {
        try {
            $networkSources = $this->networkSourceService->getAll(
                paginate: true,
                withCount: true
            );

            return $this->handleView(compact('networkSources'));
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect();
    }

    /**
     * Creates new network source.
     */
    public function store(CreateNetworkSourceRequest $request): RedirectResponse
    {
        try {
            $this->networkSourceService->create($request->validated());
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }

    /**
     * Updates existing network source.
     */
    public function update(
        UpdateNetworkSourceRequest $request,
        NetworkSource $networkSource,
    ): RedirectResponse {
        try {
            $this->networkSourceService->update(
                $networkSource,
                $request->validated()
            );
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }

    /**
     * Deletes network source
     *
     * @throws NetworkSourceDeletionFailedException
     */
    public function destroy(
        DeleteNetworkSourceRequest $request,
        NetworkSource $networkSource
    ): RedirectResponse {
        try {
            throw_unless(
                $this->networkSourceService->delete($networkSource->id),
                NetworkSourceDeletionFailedException::class
            );
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }
}
