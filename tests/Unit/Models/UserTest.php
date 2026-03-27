<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_password_is_automatically_hashed(): void
    {
        $user = User::factory()->create(['password' => 'secret_password']);

        $this->assertTrue(Hash::check('secret_password', $user->password));
    }

    public function test_sensitive_attributes_are_hidden_from_serialization(): void
    {
        $user = User::factory()->create();

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    public function test_user_supports_soft_deletes(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertSoftDeleted($user);
        $this->assertNull(User::query()->find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }

    public function test_email_verified_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
    }

    public function test_user_has_many_network_sources(): void
    {
        $user = User::factory()->create();
        NetworkSource::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertInstanceOf(HasMany::class, $user->networkSources());
        $this->assertCount(3, $user->networkSources);
        $this->assertInstanceOf(NetworkSource::class, $user->networkSources->first());
    }

    public function test_user_has_many_network_profiles(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id]);
        NetworkProfile::factory()->count(2)->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $user->networkProfiles());
        $this->assertCount(2, $user->networkProfiles);
        $this->assertInstanceOf(NetworkProfile::class, $user->networkProfiles->first());
    }

    public function test_user_has_many_network_tags(): void
    {
        $user = User::factory()->create();
        NetworkTag::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertInstanceOf(HasMany::class, $user->networkTags());
        $this->assertCount(2, $user->networkTags);
        $this->assertInstanceOf(NetworkTag::class, $user->networkTags->first());
    }

    public function test_deleting_user_soft_deletes_related_models(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id]);
        $profile = NetworkProfile::factory()->create(['user_id' => $user->id]);
        $tag = NetworkTag::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertSoftDeleted($user);
        $this->assertSoftDeleted('network_sources', ['id' => $source->id]);
        $this->assertSoftDeleted('network_profiles', ['id' => $profile->id]);
        $this->assertSoftDeleted('network_tags', ['id' => $tag->id]);
    }
}
