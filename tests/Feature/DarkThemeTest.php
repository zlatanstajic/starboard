<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class DarkThemeTest extends TestCase
{
    public function test_every_page_layout_forces_dark_theme(): void
    {
        $viewPaths = [
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/layouts/guest.blade.php'),
            resource_path('views/layouts/public.blade.php'),
            resource_path('views/welcome.blade.php'),
        ];

        foreach ($viewPaths as $viewPath) {
            $contents = file_get_contents($viewPath);

            $this->assertIsString($contents);
            $this->assertStringContainsString('class="dark', $contents);
        }
    }

    public function test_tailwind_uses_the_forced_dark_class_instead_of_device_preference(): void
    {
        $config = file_get_contents(base_path('tailwind.config.js'));
        $stylesheet = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($config);
        $this->assertStringContainsString("darkMode: 'class'", $config);
        $this->assertIsString($stylesheet);
        $this->assertStringContainsString('color-scheme: dark', $stylesheet);
    }
}
