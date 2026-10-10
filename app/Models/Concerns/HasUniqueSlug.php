<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Keeps a unique, URL-friendly "slug" column in sync with "name".
 */
trait HasUniqueSlug
{
    protected static function bootHasUniqueSlug(): void
    {
        static::saving(function (Model $model) {
            if (blank($model->slug) || $model->isDirty('name')) {
                $model->slug = static::uniqueSlugFor($model);
            }
        });
    }

    protected static function uniqueSlugFor(Model $model): string
    {
        $base = Str::slug($model->name) ?: Str::lower(class_basename($model));
        $slug = $base;

        for ($suffix = 2; static::query()->where('slug', $slug)->whereKeyNot($model->getKey())->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
