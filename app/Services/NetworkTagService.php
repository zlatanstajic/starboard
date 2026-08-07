<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NetworkTag;
use App\Repositories\NetworkTagRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class NetworkTagService
{
    /**
     * Constructs class.
     */
    public function __construct(
        public readonly NetworkTagRepository $networkTagRepository
    ) {
        //
    }

    /**
     * Gets all network tags.
     */
    public function getAll(
        bool $paginate = false,
        bool $withCount = false,
        bool $filterable = false
    ): LengthAwarePaginator {
        return $this->networkTagRepository->getAll($paginate, 'name', $withCount, $filterable);
    }

    /** @return Collection<int, NetworkTag> */
    public function getAllForOwner(int $ownerId): Collection
    {
        return $this->networkTagRepository->getAllForOwner($ownerId);
    }

    /**
     * Creates new network tag.
     */
    public function create(array $data): NetworkTag
    {
        return $this->networkTagRepository->upsert($data);
    }

    /**
     * Updates existing network tag.
     */
    public function update(
        NetworkTag $networkTag,
        array $data
    ): NetworkTag {
        return $this->networkTagRepository->upsert($data, $networkTag);
    }

    /**
     * Deletes network tag for given id.
     */
    public function delete(int $id): bool
    {
        return $this->networkTagRepository->delete($id);
    }
}
