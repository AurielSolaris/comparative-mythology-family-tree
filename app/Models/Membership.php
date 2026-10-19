<?php

namespace App\Models;

use App\Enums\Classification;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * An entity's place in one of its additional religions: what it is there
 * (a God, a demon…) and the tradition's own word for it.
 */
class Membership extends Pivot
{
    protected $table = 'entity_religion';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => Classification::class,
        ];
    }
}
