<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\DatabaseTableNamesEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatabaseTableNamesEnumTest extends TestCase
{
    /**
     * @return array<string, array{DatabaseTableNamesEnum, string}>
     */
    public static function caseValueProvider(): array
    {
        return [
            'personal_access_tokens' => [DatabaseTableNamesEnum::personal_access_tokens, 'personal_access_tokens'],
            'users' => [DatabaseTableNamesEnum::users, 'users'],
            'network_sources' => [DatabaseTableNamesEnum::network_sources, 'network_sources'],
            'network_profiles' => [DatabaseTableNamesEnum::network_profiles, 'network_profiles'],
            'network_tags' => [DatabaseTableNamesEnum::network_tags, 'network_tags'],
            'network_profile_network_tag' => [DatabaseTableNamesEnum::network_profile_network_tag, 'network_profile_network_tag'],
        ];
    }

    #[DataProvider('caseValueProvider')]
    public function test_case_has_expected_value(DatabaseTableNamesEnum $case, string $expectedValue): void
    {
        $this->assertSame($expectedValue, $case->value);
    }

    public function test_cases_count_matches_expected(): void
    {
        $this->assertCount(6, DatabaseTableNamesEnum::cases());
    }

    public function test_can_be_created_from_string_value(): void
    {
        $this->assertSame(DatabaseTableNamesEnum::users, DatabaseTableNamesEnum::from('users'));
        $this->assertSame(DatabaseTableNamesEnum::network_sources, DatabaseTableNamesEnum::from('network_sources'));
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(DatabaseTableNamesEnum::tryFrom('nonexistent_table'));
    }
}
