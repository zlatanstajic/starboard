<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\NetworkSourcesEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NetworkSourcesEnumTest extends TestCase
{
    /**
     * @return array<string, array{NetworkSourcesEnum, string}>
     */
    public static function urlTemplateProvider(): array
    {
        return [
            'instagram' => [NetworkSourcesEnum::Instagram, 'https://instagram.com/{username}'],
            'tiktok' => [NetworkSourcesEnum::TikTok, 'https://tiktok.com/@{username}'],
            'facebook' => [NetworkSourcesEnum::Facebook, 'https://facebook.com/{username}'],
            'x' => [NetworkSourcesEnum::X, 'https://x.com/{username}'],
            'youtube' => [NetworkSourcesEnum::YouTube, 'https://youtube.com/@{username}/videos'],
            'rumble' => [NetworkSourcesEnum::Rumble, 'https://rumble.com/c/{username}/videos'],
        ];
    }

    #[DataProvider('urlTemplateProvider')]
    public function test_url_template_returns_expected_value(NetworkSourcesEnum $case, string $expectedUrl): void
    {
        $this->assertSame($expectedUrl, $case->urlTemplate());
    }

    public function test_all_cases_have_https_url_template_with_username_placeholder(): void
    {
        foreach (NetworkSourcesEnum::cases() as $case) {
            $template = $case->urlTemplate();

            $this->assertStringStartsWith('https://', $template, "Case {$case->name} URL must start with https://");
            $this->assertStringContainsString('{username}', $template, "Case {$case->name} URL must contain {username} placeholder");
        }
    }

    public function test_all_cases_have_string_backing_values(): void
    {
        foreach (NetworkSourcesEnum::cases() as $case) {
            $this->assertIsString($case->value);
            $this->assertNotEmpty($case->value);
        }
    }

    public function test_cases_count_matches_expected(): void
    {
        $this->assertCount(6, NetworkSourcesEnum::cases());
    }

    public function test_can_be_created_from_string_value(): void
    {
        $this->assertSame(NetworkSourcesEnum::Instagram, NetworkSourcesEnum::from('instagram'));
        $this->assertSame(NetworkSourcesEnum::TikTok, NetworkSourcesEnum::from('tiktok'));
        $this->assertSame(NetworkSourcesEnum::X, NetworkSourcesEnum::from('x'));
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(NetworkSourcesEnum::tryFrom('invalid_source'));
    }
}
