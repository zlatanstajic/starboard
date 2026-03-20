<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\NetworkSource;
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
        $this->assertTrue(count($user->networkSources) >= 3);
        $this->assertInstanceOf(NetworkSource::class, $user->networkSources->first());
    }
}
