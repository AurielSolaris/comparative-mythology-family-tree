<?php

namespace App\Enums;

/**
 * A relation as seen from one entity: "Zeus is the parent of Apollo" reads
 * as ParentOf from Zeus and ChildOf from Apollo, and "Amun fused into
 * Amun-Ra" as FusedInto from Amun and FusionOf from Amun-Ra.
 */
enum RelationRole: string
{
    case ParentOf = 'parent_of';
    case ChildOf = 'child_of';
    case ConsortOf = 'consort_of';
    case SiblingOf = 'sibling_of';
    case CouncilMemberOf = 'council_member_of';
    case CouncilHeadOf = 'council_head_of';
    case CoRulerOf = 'co_ruler_of';

    case EquivalentOf = 'equivalent_of';
    case CognateOf = 'cognate_of';
    case AnalogueOf = 'analogue_of';
    case FusedInto = 'fused_into';
    case FusionOf = 'fusion_of';
    case AbsorbedInto = 'absorbed_into';
    case Absorbed = 'absorbed';
    case HasAvatar = 'has_avatar';
    case AvatarOf = 'avatar_of';
    case HasAspect = 'has_aspect';
    case AspectOf = 'aspect_of';
    case DevelopedInto = 'developed_into';
    case LaterFormOf = 'later_form_of';
    case AdoptedAs = 'adopted_as';
    case AdoptedFrom = 'adopted_from';
    case NameUsedFor = 'name_used_for';
    case InterpretedAs = 'interpreted_as';
    case SupplantedBy = 'supplanted_by';
    case Supplanted = 'supplanted';
    case SplitInto = 'split_into';
    case SplitFrom = 'split_from';
    case DemonizedAs = 'demonized_as';
    case DemonizedFormOf = 'demonized_form_of';
    case DeifiedAs = 'deified_as';
    case DeificationOf = 'deification_of';

    public function kind(): RelationKind
    {
        return match ($this) {
            self::ParentOf, self::ChildOf => RelationKind::Parent,
            self::ConsortOf => RelationKind::Consort,
            self::SiblingOf => RelationKind::Sibling,
            self::CouncilMemberOf, self::CouncilHeadOf => RelationKind::Council,
            self::CoRulerOf => RelationKind::CoRuler,
            self::EquivalentOf => RelationKind::Equivalent,
            self::CognateOf => RelationKind::Cognate,
            self::AnalogueOf => RelationKind::Analogue,
            self::FusedInto, self::FusionOf => RelationKind::Fusion,
            self::AbsorbedInto, self::Absorbed => RelationKind::Absorption,
            self::HasAvatar, self::AvatarOf => RelationKind::Avatar,
            self::HasAspect, self::AspectOf => RelationKind::Aspect,
            self::DevelopedInto, self::LaterFormOf => RelationKind::Derivation,
            self::AdoptedAs, self::AdoptedFrom => RelationKind::Adoption,
            self::NameUsedFor, self::InterpretedAs => RelationKind::Interpretation,
            self::SupplantedBy, self::Supplanted => RelationKind::Supplanting,
            self::SplitInto, self::SplitFrom => RelationKind::Fission,
            self::DemonizedAs, self::DemonizedFormOf => RelationKind::Demonization,
            self::DeifiedAs, self::DeificationOf => RelationKind::Deification,
        };
    }

    /**
     * Whether "$entity <role> $other" stores $entity on the entity_id side
     * of a directional relation: the parent, the council head, or the
     * source of a syncretism.
     */
    public function isSuperior(): bool
    {
        return in_array($this, [
            self::ParentOf, self::CouncilHeadOf,
            self::FusedInto, self::AbsorbedInto, self::HasAvatar, self::HasAspect, self::DevelopedInto,
            self::AdoptedAs, self::NameUsedFor, self::SupplantedBy, self::SplitInto, self::DemonizedAs, self::DeifiedAs,
        ], true);
    }

    /**
     * The role read from the other end of the same relation.
     */
    public function inverse(): self
    {
        return match ($this) {
            self::ParentOf => self::ChildOf,
            self::ChildOf => self::ParentOf,
            self::CouncilHeadOf => self::CouncilMemberOf,
            self::CouncilMemberOf => self::CouncilHeadOf,
            self::FusedInto => self::FusionOf,
            self::FusionOf => self::FusedInto,
            self::AbsorbedInto => self::Absorbed,
            self::Absorbed => self::AbsorbedInto,
            self::HasAvatar => self::AvatarOf,
            self::AvatarOf => self::HasAvatar,
            self::HasAspect => self::AspectOf,
            self::AspectOf => self::HasAspect,
            self::DevelopedInto => self::LaterFormOf,
            self::LaterFormOf => self::DevelopedInto,
            self::AdoptedAs => self::AdoptedFrom,
            self::AdoptedFrom => self::AdoptedAs,
            self::NameUsedFor => self::InterpretedAs,
            self::InterpretedAs => self::NameUsedFor,
            self::SupplantedBy => self::Supplanted,
            self::Supplanted => self::SupplantedBy,
            self::SplitInto => self::SplitFrom,
            self::SplitFrom => self::SplitInto,
            self::DemonizedAs => self::DemonizedFormOf,
            self::DemonizedFormOf => self::DemonizedAs,
            self::DeifiedAs => self::DeificationOf,
            self::DeificationOf => self::DeifiedAs,
            default => $this,
        };
    }

    /**
     * The role an entity plays in a relation of this kind: the superior
     * (entity_id) side or the other one.
     */
    public static function for(RelationKind $kind, bool $isSuperior): self
    {
        $role = collect(self::cases())->first(fn (self $role) => $role->kind() === $kind);

        return $role->isSuperior() === $isSuperior || $kind->isSymmetric() ? $role : $role->inverse();
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
            self::CoRulerOf => 'Co-ruler with',
            self::EquivalentOf => 'Equivalent of',
            self::CognateOf => 'Cognate of',
            self::AnalogueOf => 'Analogue of',
            self::FusedInto => 'Fused into',
            self::FusionOf => 'Fusion of',
            self::AbsorbedInto => 'Absorbed into',
            self::Absorbed => 'Absorbed',
            self::HasAvatar => 'Has as avatar',
            self::AvatarOf => 'Avatar of',
            self::HasAspect => 'Has as a form',
            self::AspectOf => 'Form or aspect of',
            self::DevelopedInto => 'Developed into',
            self::LaterFormOf => 'Later form of',
            self::AdoptedAs => 'Adopted elsewhere as',
            self::AdoptedFrom => 'Adopted from',
            self::NameUsedFor => 'Name used for',
            self::InterpretedAs => 'Interpreted as',
            self::SupplantedBy => 'Supplanted by',
            self::Supplanted => 'Supplanted',
            self::SplitInto => 'Split into',
            self::SplitFrom => 'Split from',
            self::DemonizedAs => 'Demonized as',
            self::DemonizedFormOf => 'Demonized form of',
            self::DeifiedAs => 'Deified as',
            self::DeificationOf => 'Deification of',
        };
    }

    /**
     * Display order: family and rank first (parents, siblings, consorts,
     * co-rulers, children, council), then syncretism in declaration order.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::ChildOf => 0,
            self::SiblingOf => 1,
            self::ConsortOf => 2,
            self::CoRulerOf => 3,
            self::ParentOf => 4,
            self::CouncilMemberOf => 5,
            self::CouncilHeadOf => 6,
            default => 10 + array_search($this, self::cases(), true),
        };
    }

    /**
     * The roles grouped for the admin form: family and rank, then syncretism.
     *
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $roles = collect(self::cases());

        return [
            'Family and rank' => $roles->reject(fn (self $role) => $role->kind()->isSyncretic())->values()->all(),
            'Syncretism and identity' => $roles->filter(fn (self $role) => $role->kind()->isSyncretic())->values()->all(),
        ];
    }
}
