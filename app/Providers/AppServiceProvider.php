<?php

namespace App\Providers;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\DeliveryRun;
use App\Models\InventoryCount;
use App\Models\InventoryDocument;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Policies\CustomerAccountEntryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DeliveryRunPolicy;
use App\Policies\InventoryCountPolicy;
use App\Policies\InventoryDocumentPolicy;
use App\Policies\InventoryStockPolicy;
use App\Policies\OrderPolicy;
use App\Policies\SalesRoutePolicy;
use App\Policies\VehiclePolicy;
use App\Policies\WarehousePolicy;
use App\Services\OfflineSync;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as ViewContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(OfflineSync::class);
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
        Gate::define('view-customer-credit', fn (User $user): bool => $user->canViewCustomerCredit());
        Gate::define('configure-customer-credit', fn (User $user): bool => $user->canConfigureCustomerCredit());
        Gate::define('record-customer-payments', fn (User $user): bool => $user->canRecordCustomerPayments());
        Gate::define('reverse-customer-payments', fn (User $user): bool => $user->canReverseCustomerPayments());
        Gate::define('view-routes', fn (User $user): bool => $user->canViewRoutes());
        Gate::define('manage-routes', fn (User $user): bool => $user->canManageRoutes());
        Gate::define('view-orders', fn (User $user): bool => $user->canViewOrders());
        Gate::define('manage-orders', fn (User $user): bool => $user->canManageOrders());
        Gate::define('view-inventory', fn (User $user): bool => $user->canViewInventory());
        Gate::define('operate-inventory', fn (User $user): bool => $user->canOperateInventory());
        Gate::define('adjust-inventory', fn (User $user): bool => $user->canAdjustInventory());
        Gate::define('configure-inventory', fn (User $user): bool => $user->canConfigureInventory());
        Gate::define('view-deliveries', fn (User $user): bool => $user->canViewDeliveries());
        Gate::define('view-reports', fn (User $user): bool => $user->canViewReports());
        Gate::define('view-route-reports', fn (User $user): bool => $user->canViewRouteReports());
        Gate::define('manage-deliveries', fn (User $user): bool => $user->canManageDeliveries());
        Gate::define('prepare-deliveries', fn (User $user): bool => $user->canPrepareDeliveries());
        Gate::define('execute-deliveries', fn (User $user): bool => $user->canExecuteDeliveries());
        Gate::define('settle-deliveries', fn (User $user): bool => $user->canSettleDeliveries());
        Gate::define('manage-vehicles', fn (User $user): bool => $user->canManageVehicles());
        Gate::define('manage-users', fn (User $user): bool => $user->canManageUsers());
        Gate::define('manage-company-profile', fn (User $user): bool => $user->canManageCompanyProfile());
        Gate::policy(SalesRoute::class, SalesRoutePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerAccountEntry::class, CustomerAccountEntryPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(InventoryStock::class, InventoryStockPolicy::class);
        Gate::policy(InventoryDocument::class, InventoryDocumentPolicy::class);
        Gate::policy(InventoryCount::class, InventoryCountPolicy::class);
        Gate::policy(DeliveryRun::class, DeliveryRunPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);

        // Short enough for anyone to remember; a letter and a number keep it from being a plain word.
        Password::defaults(fn (): Password => Password::min(8)
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
            if ($request->attributes->get('sync_operation') === true) {
                return Limit::none();
            }

            $key = $request->user() === null
                ? 'ip:'.$request->ip()
                : 'user:'.$request->user()->getAuthIdentifier();

            return Limit::perMinute(120)->by($key);
        });

        View::composer('layouts.app', function (ViewContract $view): void {
            $view->with('companyProfile', CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID));
        });

        View::composer('layouts.app', function (ViewContract $view): void {
            /** @var User|null $user */
            $user = auth()->user();

            $view->with('notificationSummary', $user === null ? null : [
                'unread_count' => $user->unreadNotifications()->count(),
                'notifications' => $user->notifications()->latest()->limit(8)->get(),
            ]);
        });
    }
}
