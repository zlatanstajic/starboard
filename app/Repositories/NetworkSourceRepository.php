<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NetworkSource\NetworkSourceDuplicationException;
use App\Models\NetworkSource;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;

class NetworkSourceRepository extends Repository
{
    /**
     * Gets all network sources and returns a unified Paginator instance.
     */
    public function getAll(
        bool $paginate = false,
        string $defaultSort = 'name',
        bool $withCount = false
    ): LengthAwarePaginator {
        $query = $this->buildStandardQuery(NetworkSource::class)
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
     * Updates network source if provided, otherwise
     * inserts new source with given data.
     *
     * @throws Exception
     */
    public function upsert(
        array $data,
        ?NetworkSource $networkSource = null
    ): NetworkSource {
        try {
            return $networkSource
                ? $this->update($networkSource, $data)
                : $this->create($data);
        } catch (Exception $e) {
            if ($e->getCode() === '23000') {
                throw new NetworkSourceDuplicationException(
                    $data['name'] ?? $networkSource->name ?? '',
                    $data['url'] ?? $networkSource->url ?? ''
                );
            }

            throw $e;
        }
    }

    /**
     * Deletes network source for given id.
     */
    public function delete(int $id): bool
    {
        return (bool) NetworkSource::destroy($id);
    }

    /**
     * Gets network source by name and url from trashed ones.
     */
    private function getByUnique(
        string $name,
        string $url
    ): ?NetworkSource {
        $query = NetworkSource::onlyTrashed()
            ->where('name', $name)
            ->where('url', $url);

        return $query->first();
    }

    /**
     * Creates new network source or restores
     * softly deleted one if exists.
     */
    private function create(array $data): NetworkSource
    {
        if (($data['name'] ?? false) && ($data['url'] ?? false)) {
            $trashedNetworkSource = $this->getByUnique(
                $data['name'],
                $data['url']
            );

            if ($trashedNetworkSource) {
                $trashedNetworkSource->restore();

                if (array_key_exists('exclude_from_dashboard', $data)) {
                    $trashedNetworkSource->update([
                        'exclude_from_dashboard' => $data['exclude_from_dashboard'],
                    ]);
                }

                return $trashedNetworkSource;
            }
        }

        return NetworkSource::query()->create($data);
    }

    /**
     * Updates network source with given data.
     */
    private function update(
        NetworkSource $networkSource,
        array $data
    ): NetworkSource {
        $networkSource->update($data);

        return $networkSource;
    }
}
