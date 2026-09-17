<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce HTTPS if accessed via HTTPS, reverse proxy (e.g. Herd, Ngrok, Coolify, Cloudflare), or APP_URL is HTTPS
        if (
            str_starts_with(config('app.url'), 'https://') ||
            request()->isSecure() ||
            request()->header('X-Forwarded-Proto') === 'https'
        ) {
            URL::forceScheme('https');
        }

        // Register DocumentPolicy and Workflow Gate Definitions
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Document::class, \App\Policies\DocumentPolicy::class);
        \Illuminate\Support\Facades\Gate::define('view-workflow', [\App\Policies\DocumentPolicy::class, 'viewWorkflow']);
        \Illuminate\Support\Facades\Gate::define('perform-workflow', [\App\Policies\DocumentPolicy::class, 'performWorkflow']);

        // Auto-heal legacy SLA column schema in production if migrations have not run yet
        if (!app()->environment('testing')) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('documents')) {
                    $slaType = \Illuminate\Support\Facades\Schema::getColumnType('documents', 'sla');
                    if ($slaType === 'enum') {
                        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `documents` MODIFY `sla` VARCHAR(100) NOT NULL DEFAULT 'Simple Transaction (3 Working Days)'");
                    }
                }
            } catch (\Throwable $e) {
                // Silently ignore if DB connection or table is unavailable during early bootstrap
            }
        }
    }
}
