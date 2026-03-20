<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NetworkTag\NetworkTagDuplicationException;
use App\Models\NetworkTag;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;

class NetworkTagRepository extends Repository
{
    /**
     * Gets all network tags and returns a unified Paginator instance.
     */
    public function getAll(
        bool $paginate = false,
        string $defaultSort = 'name',
        bool $withCount = false
    ): LengthAwarePaginator {
        $query = $this->buildStandardQuery(NetworkTag::class)
            ->defaultSort($defaultSort);

        if ($withCount) {
            $query = $query->withCount('networkProfiles');
        }

        if (! $paginate) {
            $items = $query->get();
            $count = $items->count();

            return new LengthAwarePaginator(
                $items,
                $count,
                max($count, 1),
                1,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        }

        return $query->paginate($this->itemsPerPage)->withQueryString();
    }

    /**
     * Updates network tag if provided, otherwise
     * inserts new tag with given data.
     *
     * @throws Exception
     */
    public function upsert(
        array $data,
        ?NetworkTag $networkTag = null
    ): NetworkTag {
        try {
            return $networkTag
                ? $this->update($networkTag, $data)
                : $this->create($data);
        } catch (Exception $e) {
            if ($e->getCode() === '23000') {
                throw new NetworkTagDuplicationException(
                    $data['name']
                );
            }

            throw $e;
        }
    }

    /**
     * Deletes network tag for given id.
     */
    public function delete(int $id): bool
    {
        return (bool) NetworkTag::destroy($id);
    }

    /**
     * Gets network tag by name from trashed ones.
     */
    private function getByName(
        string $name
    ): ?NetworkTag {
        $query = NetworkTag::onlyTrashed()
            ->where('name', $name);

        return $query->first();
    }

    /**
     * Creates new network tag or restores
     * softly deleted one if exists.
     */
    private function create(array $data): NetworkTag
    {
        if (! empty($data['name'])) {
            $trashedNetworkTag = $this->getByName(
                $data['name']
            );

            if ($trashedNetworkTag) {
                $trashedNetworkTag->networkProfiles()->detach();
                $trashedNetworkTag->restore();
                $trashedNetworkTag->update($data);

                return $trashedNetworkTag;
            }
        }

        return NetworkTag::query()->create($data);
    }

    /**
     * Updates network tag with given data.
     */
    private function update(
        NetworkTag $networkTag,
        array $data
    ): NetworkTag {
        $networkTag->update($data);

        return $networkTag;
    }
}
