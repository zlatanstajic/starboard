<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\NetworkProfile\NetworkProfileDeletionFailedException;
use App\Http\Requests\NetworkProfile\CreateNetworkProfileRequest;
use App\Http\Requests\NetworkProfile\DeleteNetworkProfileRequest;
use App\Http\Requests\NetworkProfile\UpdateNetworkProfileRequest;
use App\Models\NetworkProfile;
use App\Services\NetworkProfileService;
use App\Services\NetworkSourceService;
use App\Services\NetworkTagService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class NetworkProfileController extends Controller
{
    /**
     * Constructs class.
     */
    public function __construct(
        private readonly NetworkProfileService $networkProfileService,
        private readonly NetworkSourceService $networkSourceService,
        private readonly NetworkTagService $networkTagService
    ) {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View|RedirectResponse
    {
        try {
            $networkSources = $this->networkSourceService->getAll();
            $networkTags = $this->networkTagService->getAll();
            $networkProfiles = $this->networkProfileService->getAll();

            return $this->handleView(
                compact('networkSources', 'networkProfiles', 'networkTags')
            );
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect();
    }

    /**
     * Creates new network profile.
     */
    public function store(CreateNetworkProfileRequest $request): RedirectResponse
    {
        try {
            $this->networkProfileService->create($request->validated());
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }

    /**
     * Updates existing network profile.
     */
    public function update(
        UpdateNetworkProfileRequest $request,
        NetworkProfile $networkProfile,
    ): RedirectResponse {
        try {
            $this->networkProfileService->update(
                $networkProfile,
                $request->validated()
            );
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }

    /**
     * Deletes network profile
     *
     * @throws NetworkProfileDeletionFailedException
     */
    public function destroy(
        DeleteNetworkProfileRequest $request,
        NetworkProfile $networkProfile
    ): RedirectResponse {
        try {
            throw_unless(
                $this->networkProfileService->delete($networkProfile->id),
                NetworkProfileDeletionFailedException::class
            );
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }

    /**
     * Records visit of network profile.
     */
    public function recordVisit(NetworkProfile $networkProfile): RedirectResponse
    {
        try {
            $this->networkProfileService->recordVisit($networkProfile);
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect();
    }
}
