<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\NetworkSourcesEnum;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SourceIconComponentTest extends TestCase
{
    public function test_renders_brand_svg_for_known_slug(): void
    {
        $output = Blade::render('<x-source-icon slug="youtube" />');

        $this->assertStringContainsString('<svg', $output);
        $this->assertStringContainsString(NetworkSourcesEnum::YouTube->brandIconPath(), $output);
        $this->assertStringContainsString(NetworkSourcesEnum::YouTube->brandColor(), $output);
    }

    public function test_renders_github_brand_svg(): void
    {
        $output = Blade::render('<x-source-icon slug="github" />');

        $this->assertStringContainsString('<svg', $output);
        $this->assertStringContainsString(NetworkSourcesEnum::GitHub->brandIconPath(), $output);
        $this->assertStringContainsString('fill="'.NetworkSourcesEnum::GitHub->brandColor().'"', $output);
    }

    public function test_renders_nothing_for_null_slug(): void
    {
        $output = Blade::render('<x-source-icon :slug="null" />');

        $this->assertStringNotContainsString('<svg', $output);
        $this->assertSame('', trim($output));
    }

    public function test_renders_nothing_for_unknown_slug(): void
    {
        $output = Blade::render('<x-source-icon slug="myspace" />');

        $this->assertStringNotContainsString('<svg', $output);
        $this->assertSame('', trim($output));
    }

    public function test_uses_title_as_aria_label(): void
    {
        $output = Blade::render('<x-source-icon slug="x" title="X Profile" />');

        $this->assertStringContainsString('aria-label="X Profile"', $output);
        $this->assertStringContainsString('title="X Profile"', $output);
    }

    public function test_renders_fallback_svg_for_unknown_slug_when_fallback_enabled(): void
    {
        $output = Blade::render('<x-source-icon slug="myspace" :fallback="true" />');

        $this->assertStringContainsString('<svg', $output);
        $this->assertStringContainsString('fill="currentColor"', $output);
    }

    public function test_renders_fallback_svg_for_null_slug_when_fallback_enabled(): void
    {
        $output = Blade::render('<x-source-icon :slug="null" :fallback="true" />');

        $this->assertStringContainsString('<svg', $output);
        $this->assertStringContainsString('fill="currentColor"', $output);
    }

    public function test_prefers_brand_icon_over_fallback_for_known_slug(): void
    {
        $output = Blade::render('<x-source-icon slug="youtube" :fallback="true" />');

        $this->assertStringContainsString(NetworkSourcesEnum::YouTube->brandColor(), $output);
        $this->assertStringNotContainsString('fill="currentColor"', $output);
    }

    public function test_fallback_uses_title_as_aria_label(): void
    {
        $output = Blade::render('<x-source-icon :slug="null" :fallback="true" title="Custom Source" />');

        $this->assertStringContainsString('aria-label="Custom Source"', $output);
        $this->assertStringContainsString('title="Custom Source"', $output);
    }
}
