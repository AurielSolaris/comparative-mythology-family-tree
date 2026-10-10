<?php

namespace App\Enums;

/**
 * A relation as seen from one entity: "Zeus is the parent of Apollo" reads
 * as ParentOf from Zeus and ChildOf from Apollo.
 */
enum RelationRole: string
{
    case ParentOf = 'parent_of';
    case ChildOf = 'child_of';
    case ConsortOf = 'consort_of';
    case SiblingOf = 'sibling_of';
    case CouncilMemberOf = 'council_member_of';
    case CouncilHeadOf = 'council_head_of';
    case EquivalentOf = 'equivalent_of';

    public function kind(): RelationKind
    {
        return match ($this) {
            self::ParentOf, self::ChildOf => RelationKind::Parent,
            self::ConsortOf => RelationKind::Consort,
            self::SiblingOf => RelationKind::Sibling,
            self::CouncilMemberOf, self::CouncilHeadOf => RelationKind::Council,
            self::EquivalentOf => RelationKind::Equivalent,
        };
    }

    /**
     * Whether "$entity <role> $other" stores $entity on the entity_id side
     * of a directional relation.
     */
    public function isSuperior(): bool
    {
        return in_array($this, [self::ParentOf, self::CouncilHeadOf], true);
    }

    /**
     * The phrase linking an entity to the other one, worded for the entity's
     * own gender when it is male or female: "Father of", "Daughter of",
     * "Wife of", "Brother of". Otherwise the neutral "Parent of", etc.
     */
    public function label(?Gender $gender = null): string
    {
        $male = $gender === Gender::Male;
        $female = $gender === Gender::Female;

        return match ($this) {
            self::ParentOf => $male ? 'Father of' : ($female ? 'Mother of' : 'Parent of'),
            self::ChildOf => $male ? 'Son of' : ($female ? 'Daughter of' : 'Child of'),
            self::ConsortOf => $male ? 'Husband of' : ($female ? 'Wife of' : 'Consort of'),
            self::SiblingOf => $male ? 'Brother of' : ($female ? 'Sister of' : 'Sibling of'),
            self::CouncilMemberOf => 'Member of the council/court of',
            self::CouncilHeadOf => 'Council/court includes',
            self::EquivalentOf => 'Equivalent of',
        };
    }

    /**
     * Display order: parents, siblings, consorts, children, council, equivalents.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::ChildOf => 0,
            self::SiblingOf => 1,
            self::ConsortOf => 2,
            self::ParentOf => 3,
            self::CouncilMemberOf => 4,
            self::CouncilHeadOf => 5,
            self::EquivalentOf => 6,
        };
    }
}
