<?php

namespace App\Providers;

use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
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
        Gate::define('access-admin', fn (User $user) => $user->is_admin);

        Model::preventLazyLoading(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'entity' => Entity::class,
            'entity_relation' => EntityRelation::class,
            'user' => User::class,
        ]);

        Paginator::defaultView('pagination.uikit');
    }
}
