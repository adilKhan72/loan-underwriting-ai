<?php

namespace App\Providers;

use App\Services\Ai\AiServiceClient;
use App\Services\Documents\PdfTextExtractor;
use App\Services\Documents\TextExtractor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TextExtractor::class, PdfTextExtractor::class);

        $this->app->singleton(AiServiceClient::class, fn () => new AiServiceClient(
            baseUrl: config('services.ai.url'),
            timeout: config('services.ai.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
