<?php

namespace App\Providers;

use App\Cache\CitronStore;
use App\Citron\CitronDB;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
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
        Cache::extend('citron', function ($app, array $config) {
            File::ensureDirectoryExists(dirname($config['path']));

            return $this->repository(new CitronStore(
                CitronDB::open($config['path']),
                $this->getPrefix($config),
                $this->getSerializableClasses($config),
            ), $config);
        });

        Gate::define('access-admin', fn (User $user) => $user->is_admin);

        Model::preventLazyLoading(! $this->app->isProduction());

        Paginator::defaultView('pagination.uikit');
    }
}
