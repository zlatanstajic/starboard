<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\FetchYouTubeNewItemsJob;
use App\Models\NetworkProfile;
use App\Repositories\NetworkProfileRepository;
use Illuminate\Bus\Batch;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Date;

class NetworkProfileService
{
    /**
     * The number of seconds to stagger each YouTube fetch job by within a
     * batch, so profiles aren't all requested from YouTube back-to-back
     * (which makes the traffic look bot-like and risks being blocked).
     */
    private const int FETCH_STAGGER_SECONDS = 5;

    /**
     * Constructs class.
     */
    public function __construct(
        private readonly NetworkProfileRepository $networkProfileRepository
    ) {
        //
    }

    /**
     * Gets all network profiles.
     */
    public function getAll(): LengthAwarePaginator
    {
        return $this->networkProfileRepository->getAll();
    }

    /**
     * Creates new network profile.
     */
    public function create(array $data): NetworkProfile
    {
        return $this->networkProfileRepository->upsert($data);
    }

    /**
     * Updates existing network profile.
     */
    public function update(
        NetworkProfile $networkProfile,
        array $data
    ): NetworkProfile {
        return $this->networkProfileRepository->upsert($data, $networkProfile);
    }

    /**
     * Deletes network profile for given id.
     */
    public function delete(int $id): bool
    {
        return $this->networkProfileRepository->delete($id);
    }

    /**
     * Records network profile visit by incrementing
     * visit by one and storing current timestamp.
     */
    public function recordVisit(NetworkProfile $networkProfile): NetworkProfile
    {
        return $this->networkProfileRepository->increment($networkProfile);
    }

    /**
     * Dispatches a batch of background jobs (one per YouTube-video profile) to
     * fetch and count videos published since the last visit. Returns the batch
     * so its progress can be tracked, or null when there are no such profiles.
     * When $onlyMatchingFilters is true, only profiles matching the current
     * request's dashboard filters are fetched instead of all of them.
     */
    public function fetchNewItems(bool $onlyMatchingFilters = false): ?Batch
    {
        $jobs = $this->networkProfileRepository->getYouTubeVideoProfiles($onlyMatchingFilters)
            ->map(fn (NetworkProfile $networkProfile, int $index): FetchYouTubeNewItemsJob => (new FetchYouTubeNewItemsJob($networkProfile))
                ->delay(Date::now()->addSeconds($index * self::FETCH_STAGGER_SECONDS)))
            ->all();

        if ($jobs === []) {
            return null;
        }

        return Bus::batch($jobs)
            ->name('fetch-new-items')
            ->dispatch();
    }

    /**
     * Sums the currently flagged new items across the user's YouTube profiles.
     */
    public function newItemsTotal(): int
    {
        return (int) $this->networkProfileRepository->getYouTubeVideoProfiles()
            ->sum('new_items');
    }
}
