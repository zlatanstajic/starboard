<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\YouTube\YouTubeTransport;
use App\Exceptions\YouTube\YouTubeDisabledException;
use App\Models\User;
use App\Observers\UserObserver;
use App\Services\YouTube\LaravelHttpYouTubeTransport;
use Illuminate\Support\ServiceProvider;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(YouTubeTransport::class, function (): YouTubeTransport {
            $transport = config('youtube.transport');

            throw_if($transport !== 'laravel-http', YouTubeDisabledException::class, "Unsupported YouTube transport [{$transport}].");

            return new LaravelHttpYouTubeTransport;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only observe User events in production, not during tests
        if (! app()->runningUnitTests()) {
            User::observe(UserObserver::class);
        }
    }
}
