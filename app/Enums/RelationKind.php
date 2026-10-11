<?php

namespace App\Enums;

/**
 * How a relation is stored. Parent (entity_id is the parent) and Council
 * (entity_id heads the council or court, related_entity_id is a member) are
 * directional; the other kinds (consort, sibling, equivalent, co-ruler) are
 * symmetric.
 */
enum RelationKind: string
{
    case Parent = 'parent';
    case Consort = 'consort';
    case Sibling = 'sibling';
    case Equivalent = 'equivalent';
    case Council = 'council';
    case CoRuler = 'co_ruler';

    public function isSymmetric(): bool
    {
        return ! in_array($this, [self::Parent, self::Council], true);
    }
}
