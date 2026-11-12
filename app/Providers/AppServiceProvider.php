<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        spl_autoload_register(function ($class) {
            if (str_starts_with($class, 'Smalot\\PdfParser\\')) {
                $relative = str_replace('Smalot\\PdfParser\\', '', $class);
                $file = base_path('vendor/smalot/pdfparser/src/Smalot/PdfParser/' . str_replace('\\', '/', $relative) . '.php');
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
{
    ResetPassword::createUrlUsing(function ($user, string $token) {
        return config('app.frontend_url') . '/auth/reset-password?token=' . $token . '&email=' . urlencode($user->email);
    });
}
}
