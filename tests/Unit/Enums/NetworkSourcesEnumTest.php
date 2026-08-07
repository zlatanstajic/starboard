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
            'googlesheets' => [NetworkSourcesEnum::GoogleSheets, 'https://docs.google.com/spreadsheets/d/{hash}'],
            'loom' => [NetworkSourcesEnum::Loom, 'https://www.loom.com/share/{username}'],
            'wikipedia' => [NetworkSourcesEnum::Wikipedia, 'https://en.wikipedia.org/wiki/{id}'],
            'imdb' => [NetworkSourcesEnum::Imdb, 'https://www.imdb.com/list/{id}'],
            'github' => [NetworkSourcesEnum::GitHub, 'https://github.com/{username}'],
        ];
    }

    /**
     * @return array<string, array{string, ?NetworkSourcesEnum}>
     */
    public static function fromUrlProvider(): array
    {
        return [
            'youtube.com' => ['https://youtube.com/@channel/videos', NetworkSourcesEnum::YouTube],
            'youtu.be' => ['https://youtu.be/abc123', NetworkSourcesEnum::YouTube],
            'rumble.com' => ['https://rumble.com/c/channel/videos', NetworkSourcesEnum::Rumble],
            'instagram.com' => ['https://instagram.com/handle', NetworkSourcesEnum::Instagram],
            'tiktok.com' => ['https://tiktok.com/@handle', NetworkSourcesEnum::TikTok],
            'facebook.com' => ['https://facebook.com/page', NetworkSourcesEnum::Facebook],
            'fb.com' => ['https://fb.com/page', NetworkSourcesEnum::Facebook],
            'x.com' => ['https://x.com/handle', NetworkSourcesEnum::X],
            'twitter.com' => ['https://twitter.com/handle', NetworkSourcesEnum::X],
            'uppercase scheme is case-insensitive' => ['HTTPS://YOUTUBE.COM/@channel', NetworkSourcesEnum::YouTube],
            'subdomain matches domain suffix' => ['https://www.youtube.com/watch?v=x', NetworkSourcesEnum::YouTube],
            'unknown domain' => ['https://example.com/profile', null],
            'partial host is not a false positive' => ['https://box.com/foo', null],
            'domain in query string is not a false positive' => ['https://example.com/?ref=youtube.com', null],
            'malformed url with no host' => ['not a url', null],
            'docs.google.com' => ['https://docs.google.com/spreadsheets/d/abc123', NetworkSourcesEnum::GoogleSheets],
            'www.loom.com' => ['https://www.loom.com/share/abc123', NetworkSourcesEnum::Loom],
            'loom.com' => ['https://loom.com/share/abc123', NetworkSourcesEnum::Loom],
            'en.wikipedia.org' => ['https://en.wikipedia.org/wiki/Article', NetworkSourcesEnum::Wikipedia],
            'sh.wikipedia.org subdomain matches' => ['https://sh.wikipedia.org/wiki/Article', NetworkSourcesEnum::Wikipedia],
            'imdb.com' => ['https://www.imdb.com/list/abc123', NetworkSourcesEnum::Imdb],
            'github.com' => ['https://github.com/laravel/framework', NetworkSourcesEnum::GitHub],
            'www.github.com subdomain matches' => ['https://www.github.com/laravel/framework', NetworkSourcesEnum::GitHub],
            'gist.github.com subdomain matches' => ['https://gist.github.com/username/id', NetworkSourcesEnum::GitHub],
            'github.io' => ['https://github.io/', NetworkSourcesEnum::GitHub],
            'github.io subdomain matches' => ['https://laravel.github.io/framework', NetworkSourcesEnum::GitHub],
            'github host is case-insensitive' => ['https://GITHUB.COM/laravel/framework', NetworkSourcesEnum::GitHub],
            'github partial host is not a false positive' => ['https://notgithub.com/laravel/framework', null],
            'github parent domain is not a false positive' => ['https://github.com.example.com/laravel/framework', null],
            'github.io partial host is not a false positive' => ['https://notgithub.io/framework', null],
            'github.io parent domain is not a false positive' => ['https://github.io.example.com/framework', null],
            'github in path is not a false positive' => ['https://example.com/github.com/laravel/framework', null],
            'github in query string is not a false positive' => ['https://example.com/?ref=github.com', null],
        ];
    }

    #[DataProvider('urlTemplateProvider')]
    public function test_url_template_returns_expected_value(NetworkSourcesEnum $case, string $expectedUrl): void
    {
        $this->assertSame($expectedUrl, $case->urlTemplate());
    }

    public function test_all_cases_have_https_url_template_with_placeholder(): void
    {
        foreach (NetworkSourcesEnum::cases() as $case) {
            $template = $case->urlTemplate();

            $this->assertStringStartsWith('https://', $template, "Case {$case->name} URL must start with https://");
            $this->assertMatchesRegularExpression('/\{[a-z]+\}/', $template, "Case {$case->name} URL must contain a {placeholder}");
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
        $this->assertCount(11, NetworkSourcesEnum::cases());
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

    #[DataProvider('fromUrlProvider')]
    public function test_from_url_matches_expected_case(string $url, ?NetworkSourcesEnum $expected): void
    {
        $this->assertSame($expected, NetworkSourcesEnum::fromUrl($url));
    }

    public function test_icon_map_contains_path_and_color_for_every_case(): void
    {
        $map = NetworkSourcesEnum::iconMap();

        $this->assertCount(11, $map);

        foreach (NetworkSourcesEnum::cases() as $case) {
            $this->assertArrayHasKey($case->value, $map);
            $this->assertArrayHasKey('path', $map[$case->value]);
            $this->assertArrayHasKey('color', $map[$case->value]);
            $this->assertNotEmpty($map[$case->value]['path']);
            $this->assertStringStartsWith('#', $map[$case->value]['color']);
        }
    }
}
