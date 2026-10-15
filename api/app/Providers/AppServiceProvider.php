<?php

namespace App\Providers;

use App\Services\Ai\AiServiceClient;
use App\Services\Documents\PdfTextExtractor;
use App\Services\Documents\TextExtractor;
use App\Underwriting\Policies\PolicyV1;
use App\Underwriting\Policies\UnderwritingPolicy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TextExtractor::class, PdfTextExtractor::class);

        // The policy new decisions are made under. Old decisions keep their recorded policy_version.
        $this->app->bind(UnderwritingPolicy::class, PolicyV1::class);

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
