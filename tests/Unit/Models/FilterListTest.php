<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\FilterList;
use App\Models\Scopes\UserScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class FilterListTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    #[Override]
    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_model_casts_and_relationship_are_configured(): void
    {
        $user = User::withoutEvents(fn (): User => User::factory()->create());
        $list = FilterList::factory()->create(['user_id' => $user->id]);

        $this->assertIsArray($list->filters);
        $this->assertIsBool($list->is_published);
        $this->assertNotNull($list->published_at);
        $this->assertInstanceOf(BelongsTo::class, $list->user());
        $this->assertTrue(FilterList::hasGlobalScope(UserScope::class));
    }

    public function test_model_soft_deletes(): void
    {
        $user = User::withoutEvents(fn (): User => User::factory()->create());
        $list = FilterList::factory()->create(['user_id' => $user->id]);

        $list->delete();

        $this->assertSoftDeleted('filter_lists', ['id' => $list->id]);
    }

    public function test_public_url_is_built_from_the_hash_alone(): void
    {
        $user = User::withoutEvents(fn (): User => User::factory()->create());
        $list = FilterList::factory()->create(['user_id' => $user->id, 'hash' => 'BareHash1234']);

        $this->assertSame(url('/lists/BareHash1234'), $list->publicUrl());
    }
}
