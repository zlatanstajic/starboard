<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NetworkProfile;
use App\Repositories\NetworkProfileRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class NetworkProfileService
{
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
}
