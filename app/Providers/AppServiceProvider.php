<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        Schema::defaultStringLength(255);
        // Force HTTPS if APP_URL is https
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
        // Register anonymous components
        Blade::anonymousComponentPath(resource_path('views/components'));
        // Backward compatibility for themes referencing App\Models\TMail
        if (!class_exists('App\Models\TMail')) {
            class_alias(\App\Services\TMail::class, 'App\Models\TMail');
        }
    }
}
