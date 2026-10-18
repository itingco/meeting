<?php
namespace App\Providers;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];
    public function boot(): void
    {
        Gate::before(function (User $user) {
            return $user->is_active && $user->is_admin ? true : null;
        });
        Gate::define('manage-users', fn(User $user) => $user->is_active && $user->is_admin);
        Gate::define('view-history', fn(User $user) => $user->is_active && $user->can_view_history);
        Gate::define('manage-settings', fn(User $user) => $user->is_active && $user->can_manage_settings);
    }
}
