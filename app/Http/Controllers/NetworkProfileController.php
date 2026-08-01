<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\NetworkProfile\NetworkProfileDeletionFailedException;
use App\Http\Requests\NetworkProfile\CreateNetworkProfileRequest;
use App\Http\Requests\NetworkProfile\DeleteNetworkProfileRequest;
use App\Http\Requests\NetworkProfile\FetchYouTubeProfilesRequest;
use App\Http\Requests\NetworkProfile\UpdateNetworkProfileRequest;
use App\Models\NetworkProfile;
use App\Models\YouTubeFetchBatch;
use App\Services\NetworkProfileService;
use App\Services\NetworkSourceService;
use App\Services\NetworkTagService;
use App\Services\YouTube\YouTubeRequestBudget;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;

class NetworkProfileController extends Controller
{
    /**
     * Constructs class.
     */
    public function __construct(
        private readonly NetworkProfileService $networkProfileService,
        private readonly NetworkSourceService $networkSourceService,
        private readonly NetworkTagService $networkTagService,
        private readonly YouTubeRequestBudget $youtubeRequestBudget,
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
            $youtubeFetchEnabled = (bool) config('youtube.execution_enabled') && (bool) config('youtube.ui_enabled');
            $youtubeFetchAvailability = $this->youtubeRequestBudget->availability();

            return $this->handleView(
                compact('networkSources', 'networkProfiles', 'networkTags', 'youtubeFetchEnabled', 'youtubeFetchAvailability')
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
     * Fetching is always scoped to the profiles matching the current dashboard
     * filters. Redirects back to the current dashboard URL, preserving the
     * active query string.
     */
    public function fetch(FetchYouTubeProfilesRequest $request): RedirectResponse|Response
    {
        if (! config('youtube.execution_enabled')) {
            return response(__('messages.network_profile.fetch.disabled'), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $batch = $this->networkProfileService->fetchNewItems(true, $request->validated());

            if ($batch === null) {
                Alert::info(
                    __('messages.network_profile.fetch.started'),
                    __('messages.network_profile.fetch.nothing_to_fetch')
                );

                return $this->handleRedirect($request);
            }

            session(['fetch_batch_id' => $batch->id]);
        } catch (Exception $e) {
            $this->handleException($e);
        }

        return $this->handleRedirect($request);
    }

    /**
     * Reports the progress of the current fetch batch for polling. When the
     * batch has finished it clears the tracked batch id.
     */
    public function fetchStatus(): JsonResponse
    {
        if (! config('youtube.execution_enabled')) {
            return response()->json(['active' => false, 'disabled' => true], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $batchId = session('fetch_batch_id');

        if (! is_string($batchId) || $batchId === '') {
            return response()->json(['active' => false]);
        }

        $batch = Bus::findBatch($batchId);
        $auditBatch = YouTubeFetchBatch::query()
            ->where('user_id', auth()->id())
            ->where('laravel_batch_id', $batchId)
            ->first();

        if ($batch === null || $auditBatch === null) {
            session()->forget('fetch_batch_id');

            return response()->json(['active' => false]);
        }

        $finished = $batch->finished() || $batch->cancelled();
        $outcomes = $auditBatch->runs()->whereNotNull('outcome')->selectRaw('outcome, COUNT(*) as aggregate')->groupBy('outcome')->pluck('aggregate', 'outcome');

        if ($finished) {
            $auditBatch->update(['state' => $batch->cancelled() ? 'canceled' : 'finished', 'finished_at' => now()]);
            session()->forget('fetch_batch_id');
        }

        return response()->json([
            'active' => true,
            'finished' => $finished,
            'canceled' => $batch->cancelled(),
            'total' => $batch->totalJobs,
            'processed' => $batch->processedJobs(),
            'pending' => $batch->pendingJobs,
            'failed' => $batch->failedJobs,
            'retrying' => (int) ($outcomes['retrying'] ?? 0),
            'outcomes' => $outcomes,
            'progress' => $batch->progress(),
        ]);
    }
}
