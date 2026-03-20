<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\NetworkSourcesEnum;
use PHPUnit\Framework\TestCase;

class NetworkSourcesEnumTest extends TestCase
{
    public function test_all_cases_return_valid_url_template(): void
    {
        foreach (NetworkSourcesEnum::cases() as $case) {
            $template = $case->urlTemplate();

            $this->assertIsString($template);
            $this->assertNotEmpty($template);
            $this->assertStringContainsString('{username}', $template);
            $this->assertStringStartsWith('https://', $template);
        }
    }
}
