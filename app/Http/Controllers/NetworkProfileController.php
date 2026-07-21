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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use RealRashid\SweetAlert\Facades\Alert;

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

    /**
     * Dispatches a batch of background jobs to fetch new items for YouTube
     * profiles and records the batch id so the dashboard can poll its progress.
     */
    public function fetch(): RedirectResponse
    {
        try {
            $batch = $this->networkProfileService->fetchNewItems();

            if ($batch === null) {
                Alert::info(
                    __('messages.network_profile.fetch.started'),
                    __('messages.network_profile.fetch.nothing_to_fetch')
                );

                return $this->handleRedirect();
            }

            session(['fetch_batch_id' => $batch->id]);

            Alert::info(
                __('messages.network_profile.fetch.started'),
                __('messages.network_profile.fetch.running_background')
            );
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect();
    }

    /**
     * Reports the progress of the current fetch batch for polling. When the
     * batch has finished it flashes a completion alert (shown on the next page
     * load) and clears the tracked batch id.
     */
    public function fetchStatus(): JsonResponse
    {
        $batchId = session('fetch_batch_id');

        if (! is_string($batchId) || $batchId === '') {
            return response()->json(['active' => false]);
        }

        $batch = Bus::findBatch($batchId);

        if ($batch === null) {
            session()->forget('fetch_batch_id');

            return response()->json(['active' => false]);
        }

        if ($batch->finished()) {
            session()->forget('fetch_batch_id');

            Alert::success(
                __('messages.network_profile.fetch.complete'),
                __('messages.network_profile.fetch.found_new_items', [
                    'count' => $this->networkProfileService->newItemsTotal(),
                ])
            );
        }

        return response()->json([
            'active' => true,
            'finished' => $batch->finished(),
            'total' => $batch->totalJobs,
            'processed' => $batch->processedJobs(),
            'progress' => $batch->progress(),
        ]);
    }
}
