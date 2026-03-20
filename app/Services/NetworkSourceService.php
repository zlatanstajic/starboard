<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NetworkSource;
use App\Repositories\NetworkSourceRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class NetworkSourceService
{
    /**
     * Constructs class.
     */
    public function __construct(
        public readonly NetworkSourceRepository $networkSourceRepository
    ) {
        //
    }

    /**
     * Gets all network sources.
     */
    public function getAll(
        bool $paginate = false,
        bool $withCount = false
    ): LengthAwarePaginator {
        return $this->networkSourceRepository->getAll($paginate, 'name', $withCount);
    }

    /**
     * Creates new network source.
     */
    public function create(array $data): NetworkSource
    {
        return $this->networkSourceRepository->upsert($data);
    }

    /**
     * Updates existing network source.
     */
    public function update(
        NetworkSource $networkSource,
        array $data
    ): NetworkSource {
        return $this->networkSourceRepository->upsert($data, $networkSource);
    }

    /**
     * Deletes network source for given id.
     */
    public function delete(int $id): bool
    {
        return $this->networkSourceRepository->delete($id);
    }
}
