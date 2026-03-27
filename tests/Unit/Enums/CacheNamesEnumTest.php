<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\CacheNamesEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CacheNamesEnumTest extends TestCase
{
    /**
     * @return array<string, array{CacheNamesEnum, string}>
     */
    public static function caseValueProvider(): array
    {
        return [
            'network_sources_index' => [CacheNamesEnum::network_sources_index, 'network_sources_index'],
        ];
    }

    #[DataProvider('caseValueProvider')]
    public function test_case_has_expected_value(CacheNamesEnum $case, string $expectedValue): void
    {
        $this->assertSame($expectedValue, $case->value);
    }

    public function test_cases_count_matches_expected(): void
    {
        $this->assertCount(1, CacheNamesEnum::cases());
    }

    public function test_can_be_created_from_string_value(): void
    {
        $this->assertSame(CacheNamesEnum::network_sources_index, CacheNamesEnum::from('network_sources_index'));
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(CacheNamesEnum::tryFrom('nonexistent_cache'));
    }
}
