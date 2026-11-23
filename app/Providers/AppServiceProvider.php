<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Authentication\Enums\LoginEventType;
use App\Modules\Authentication\Services\LoginHistory;
use App\Modules\Companies\Services\CurrentCompany;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentCompany::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every sign-out, whichever screen triggered it, goes to the sign-in history.
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                app(LoginHistory::class)->record($event->user, LoginEventType::SignedOut);
            }
        });
    }
}
