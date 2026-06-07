<?php

namespace App\Providers;

use App\Actions\Sale\RecalculateSaleTotal;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchasePolicy;
use App\Policies\SalePolicy;
use App\Policies\ShiftPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RecalculateSaleTotal::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Carbon::setLocale('id');

        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers()->symbols());

        // Force HTTPS in local development in production alerdy force HTTPS
        if (app()->isLocal()) {
            URL::forceScheme('https');
        }

        $this->registerPolicies();
        $this->registerSuperAdminGate();
        $this->registerActivityLogTenantScope();
    }

    /**
     * Register model policies.
     *
     * Best practice: Use Laravel Model Policies for access control.
     */
    private function registerPolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Purchase::class, PurchasePolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
    }

    /**
     * Register Super Admin Gate.
     *
     * Best practice: Use Gate::before to implicitly grant super_admin
     * all permissions. This avoids needing to assign all permissions
     * to the super_admin role and works with can(), @can(), authorize().
     */
    private function registerSuperAdminGate(): void
    {
        Gate::before(function ($user, $ability) {
            if ($ability === 'view_activity_logs') {
                return null;
            }

            if ($user->hasRole(Role::SuperAdmin->value)) {
                return true;
            }

            return null;
        });
    }

    /**
     * Auto-set company_id on activity log entries.
     */
    private function registerActivityLogTenantScope(): void
    {
        Activity::creating(function (Activity $activity) {
            if ($activity->company_id) {
                return;
            }

            if ($activity->subject && $activity->subject->company_id) {
                $activity->company_id = $activity->subject->company_id;

                return;
            }

            if ($causer = $activity->causer) {
                if ($causer->company_id) {
                    $activity->company_id = $causer->company_id;
                }
            }
        });
    }
}
