<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\FetchYouTubeNewItemsJob;
use App\Models\FilterList;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\YouTubeFetchBatch;
use App\Models\YouTubeFetchRun;
use App\Repositories\NetworkProfileRepository;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use LengthException;

class NetworkProfileService
{
    /**
     * The number of seconds to stagger each YouTube fetch job by within a
     * batch, so profiles aren't all requested from YouTube back-to-back
     * (which makes the traffic look bot-like and risks being blocked).
     */
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
     * Gets network profiles for given filter list.
     */
    public function getForFilterList(
        FilterList $filterList,
        ?Request $visitorRequest = null
    ): LengthAwarePaginator {
        return $this->networkProfileRepository->getForFilterList($filterList, $visitorRequest);
    }

    /**
     * Network sources represented in a published list, for its filter dropdown.
     *
     * @return Collection<int, NetworkSource>
     */
    public function getSourcesForFilterList(FilterList $filterList): Collection
    {
        return $this->networkProfileRepository->getSourcesForFilterList($filterList);
    }

    /**
     * Network tags represented in a published list, for its filter dropdown.
     *
     * @return Collection<int, NetworkTag>
     */
    public function getTagsForFilterList(FilterList $filterList): Collection
    {
        return $this->networkProfileRepository->getTagsForFilterList($filterList);
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
    public function fetchNewItems(bool $onlyMatchingFilters = false, array $filters = []): ?Batch
    {
        if (auth()->id() === null) {
            $jobs = $this->networkProfileRepository->getYouTubeVideoProfiles($onlyMatchingFilters)
                ->values()
                ->map(fn (NetworkProfile $profile, int $index): FetchYouTubeNewItemsJob => new FetchYouTubeNewItemsJob($profile)
                    ->delay(Date::now()->addSeconds($index * (int) config('youtube.stagger_seconds'))))
                ->all();

            return $jobs === [] ? null : Bus::batch($jobs)->name('fetch-new-items')->allowFailures()->dispatch();
        }

        $userId = (int) auth()->id();

        return Cache::lock("youtube-fetch-batch:user:{$userId}", 15)->block(
            5,
            fn (): ?Batch => $this->dispatchAuthenticatedFetch($userId, $onlyMatchingFilters, $filters),
        );
    }

    /**
     * Sums the currently flagged new items across the user's YouTube profiles.
     */
    public function newItemsTotal(): int
    {
        return (int) $this->networkProfileRepository->getYouTubeVideoProfiles()
            ->sum('new_items');
    }

    /** @param array<string, mixed> $filters */
    private function dispatchAuthenticatedFetch(int $userId, bool $onlyMatchingFilters, array $filters): ?Batch
    {
        $active = YouTubeFetchBatch::query()->where('user_id', $userId)->whereIn('state', ['preparing', 'active'])->latest()->first();

        if ($active?->laravel_batch_id !== null) {
            $existingBatch = Bus::findBatch($active->laravel_batch_id);

            if ($existingBatch !== null && ! $existingBatch->finished() && ! $existingBatch->cancelled()) {
                return $existingBatch;
            }

            $active->update([
                'state' => $existingBatch?->cancelled() ? 'canceled' : 'finished',
                'finished_at' => now(),
            ]);
        }

        $limit = (int) config('youtube.batch_limit');
        $profiles = $this->networkProfileRepository->getYouTubeVideoProfiles($onlyMatchingFilters, $limit + 1, $filters)->collect();

        throw_if($profiles->count() > $limit, LengthException::class, 'The filtered YouTube profile selection exceeds the configured batch limit.');

        if ($profiles->isEmpty()) {
            return null;
        }

        $auditBatch = YouTubeFetchBatch::query()->create([
            'user_id' => $userId,
            'filters' => $filters,
            'state' => 'preparing',
            'total' => $profiles->count(),
            'started_at' => now(),
        ]);
        $jobs = $profiles->values()->map(function (NetworkProfile $profile, int $index) use ($auditBatch, $userId): FetchYouTubeNewItemsJob {
            $uuid = (string) Str::uuid();
            YouTubeFetchRun::query()->create([
                'uuid' => $uuid,
                'youtube_fetch_batch_id' => $auditBatch->id,
                'network_profile_id' => $profile->id,
                'user_id' => $userId,
                'stage' => 'queued',
            ]);

            return new FetchYouTubeNewItemsJob((int) $profile->id, $userId, $uuid)
                ->delay(Date::now()->addSeconds($index * (int) config('youtube.stagger_seconds')));
        })->all();

        $batch = Bus::batch($jobs)
            ->name('fetch-new-items')
            ->allowFailures()
            ->dispatch();

        $auditBatch->update(['laravel_batch_id' => $batch->id, 'state' => 'active']);

        return $batch;
    }
}
