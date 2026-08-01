<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NetworkProfile;
use App\Models\YouTubeFetchRun;
use App\Services\YouTube\YouTubeVideoFetchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('youtube:probe {--profile= : Network profile ID} {--confirm-live : Confirm that outbound YouTube access is intended}')]
#[Description('Perform an operator-approved live YouTube transport probe for one profile')]
class YouTubeProbeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(YouTubeVideoFetchService $service): int
    {
        if (! $this->option('confirm-live')) {
            $this->error('Refusing live access without --confirm-live.');

            return self::FAILURE;
        }

        $profileId = filter_var($this->option('profile'), FILTER_VALIDATE_INT);
        $profile = $profileId === false ? null : NetworkProfile::query()->withoutGlobalScopes()->find($profileId);

        if ($profile === null) {
            $this->error('A valid --profile ID is required.');

            return self::FAILURE;
        }

        $uuid = (string) Str::uuid();
        YouTubeFetchRun::query()->create([
            'uuid' => $uuid,
            'network_profile_id' => $profile->id,
            'user_id' => $profile->user_id,
            'stage' => 'probe',
        ]);
        $result = $service->fetch((int) $profile->id, (int) $profile->user_id, $uuid);

        $this->table(['outcome', 'status', 'transport', 'duration_ms', 'requests'], [[
            $result->outcome->value,
            $result->status ?? '-',
            $result->transport ?? config('youtube.transport'),
            $result->durationMilliseconds,
            $result->requestCount,
        ]]);

        return $result->outcome->value === 'success' ? self::SUCCESS : self::FAILURE;
    }
}
