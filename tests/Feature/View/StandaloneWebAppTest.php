<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\FilterList;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * iOS derives a home-screen app's scope from the launch URL's own path unless a
 * web app manifest pins one. An icon added from `/dashboard` therefore kept only
 * that page full screen and dropped every other page back into Safari with its
 * chrome visible. `site.webmanifest` sets `scope` to `/`, and `x-head-web-app`
 * carries it — plus the Apple meta tags iOS below 16.4 reads instead — into
 * every document that owns a `<head>`.
 */
class StandaloneWebAppTest extends TestCase
{
    /**
     * @return list<array{string}>
     */
    public static function guestPageProvider(): array
    {
        return [
            'landing page' => ['/'],
            'login page' => ['/login'],
        ];
    }

    public function test_the_manifest_scopes_the_installed_app_to_the_whole_site(): void
    {
        $contents = file_get_contents(public_path('site.webmanifest'));

        $this->assertIsString($contents);

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('/', $manifest['scope'] ?? null, 'A narrower scope drops pages outside it back into Safari.');
        $this->assertSame('/', $manifest['start_url'] ?? null);
        $this->assertSame('standalone', $manifest['display'] ?? null);
    }

    public function test_every_head_owning_view_includes_the_shared_web_app_head(): void
    {
        foreach (self::headOwningViews() as $viewPath) {
            $contents = file_get_contents($viewPath);

            $this->assertIsString($contents);
            $this->assertStringContainsString(
                '<x-head-web-app />',
                $contents,
                basename($viewPath).' must carry the installable-web-app head tags.',
            );
        }
    }

    public function test_no_head_owning_view_restates_the_shared_tags_itself(): void
    {
        foreach (self::headOwningViews() as $viewPath) {
            $contents = file_get_contents($viewPath);

            $this->assertIsString($contents);
            $this->assertStringNotContainsString(
                'apple-mobile-web-app-capable',
                $contents,
                basename($viewPath).' duplicates a tag the shared component owns; that drift is what broke the other pages.',
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('guestPageProvider')]
    public function test_a_guest_page_renders_the_manifest_and_apple_tags(string $uri): void
    {
        $response = $this->get($uri);

        $response->assertOk();
        $response->assertSee('rel="manifest"', false);
        $response->assertSee('name="apple-mobile-web-app-capable" content="yes"', false);
    }

    public function test_the_dashboard_and_a_public_list_render_the_manifest_and_apple_tags(): void
    {
        DB::beginTransaction();

        try {
            $user = User::factory()->create();
            $filterList = FilterList::factory()->create(['user_id' => $user->id]);

            foreach ([$this->actingAs($user)->get(route('dashboard')), $this->get($filterList->publicUrl())] as $response) {
                $response->assertOk();
                $response->assertSee('rel="manifest"', false);
                $response->assertSee('name="apple-mobile-web-app-capable" content="yes"', false);
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Every Blade document with its own `<head>`. A new one that forgets the
     * component would ship a page that leaves the installed app.
     *
     * @return list<string>
     */
    private static function headOwningViews(): array
    {
        return [
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/layouts/guest.blade.php'),
            resource_path('views/layouts/public.blade.php'),
            resource_path('views/welcome.blade.php'),
        ];
    }
}
