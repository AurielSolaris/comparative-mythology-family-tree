<?php

namespace App\Models\Concerns;

use App\Models\Source;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Lets a model carry citations. Its sources (and their stored files) are
 * deleted along with it.
 */
trait HasSources
{
    protected static function bootHasSources(): void
    {
        static::deleting(function (Model $model) {
            $model->sources()->get()->each->delete();
        });
    }

    /**
     * @return MorphMany<Source, $this>
     */
    public function sources(): MorphMany
    {
        return $this->morphMany(Source::class, 'sourceable')->orderBy('id');
    }
}
