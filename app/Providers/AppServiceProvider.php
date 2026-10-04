<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\SalesRoute;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Policies\SalesRoutePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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
        Model::preventLazyLoading(! app()->isProduction());

        Gate::define('access-panel', fn (User $user): bool => $user->canAccessPanel());
        Gate::define('manage-catalog', fn (User $user): bool => $user->canManageCatalog());
        Gate::define('manage-customers', fn (User $user): bool => $user->canManageCustomers());
        Gate::define('view-routes', fn (User $user): bool => $user->canViewRoutes());
        Gate::define('manage-routes', fn (User $user): bool => $user->canManageRoutes());
        Gate::define('view-orders', fn (User $user): bool => $user->canViewOrders());
        Gate::define('manage-orders', fn (User $user): bool => $user->canManageOrders());
        Gate::define('manage-users', fn (User $user): bool => $user->canManageUsers());
        Gate::policy(SalesRoute::class, SalesRoutePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);

        Password::defaults(fn (): Password => Password::min(12)
            ->max(128)
            ->letters()
            ->numbers());

        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower(trim($request->string('email')->toString()));

            return [
                Limit::perMinute(5)->by('login:'.$email.'|'.$request->ip()),
                Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->user() === null
                ? 'ip:'.$request->ip()
                : 'user:'.$request->user()->getAuthIdentifier();

            return Limit::perMinute(120)->by($key);
        });
    }
}
