<?php

namespace App\Enums;

/**
 * How a relation is stored.
 *
 * Family and rank: Parent (entity_id is the parent) and Council
 * (entity_id heads the council or court, related_entity_id is a member)
 * are directional; consort, sibling and co-ruler are symmetric.
 *
 * Syncretism and identity: equivalent, cognate and analogue are symmetric.
 * The other syncretism kinds are directional and always point the way the
 * identity flowed, from the source (entity_id) to the result
 * (related_entity_id): the component to the fused God, the absorbed God to
 * the one that absorbed it, the God to its avatar, the older form to the
 * later one, and so on.
 */
enum RelationKind: string
{
    case Parent = 'parent';
    case Consort = 'consort';
    case Sibling = 'sibling';
    case Council = 'council';
    case CoRuler = 'co_ruler';

    case Equivalent = 'equivalent';
    case Cognate = 'cognate';
    case Analogue = 'analogue';
    case Fusion = 'fusion';
    case Absorption = 'absorption';
    case Avatar = 'avatar';
    case Aspect = 'aspect';
    case Derivation = 'derivation';
    case Adoption = 'adoption';
    case Interpretation = 'interpretation';
    case Supplanting = 'supplanting';
    case Fission = 'fission';
    case Demonization = 'demonization';
    case Deification = 'deification';

    public function isSymmetric(): bool
    {
        return in_array($this, [
            self::Consort, self::Sibling, self::CoRuler,
            self::Equivalent, self::Cognate, self::Analogue,
        ], true);
    }

    /**
     * Whether the relation is about identity across or within traditions
     * (one God becoming, merging with or standing for another) rather than
     * family or rank. These never place entities in generations.
     */
    public function isSyncretic(): bool
    {
        return ! in_array($this, [self::Parent, self::Consort, self::Sibling, self::Council, self::CoRuler], true);
    }

    /**
     * What this way of relating means, for admins choosing one.
     */
    public function description(): string
    {
        return match ($this) {
            self::Parent => 'One is the parent of the other.',
            self::Consort => 'Partners.',
            self::Sibling => 'Siblings.',
            self::Council => 'One heads a council or court the other belongs to.',
            self::CoRuler => 'They rule together.',
            self::Equivalent => 'Identified as the same God, e.g. Vishnu and Nārāyaṇa.',
            self::Cognate => 'Descended from a common older God, without borrowing, e.g. Dyaus and Zeus.',
            self::Analogue => 'Comparable role or myth with no known historical link.',
            self::Fusion => 'Two or more Gods merged into a new compound God, e.g. Amun + Ra → Amun-Ra.',
            self::Absorption => 'One God absorbed another, which lives on as its name, epithet or form.',
            self::Avatar => 'One God incarnates as another, e.g. Vishnu → Krishna.',
            self::Aspect => 'A form, emanation or hypostasis of a God, e.g. Vāsudeva → Saṃkarṣaṇa (Vyūha).',
            self::Derivation => 'The same God at a later stage within a tradition, e.g. Vedic Rudra → Shiva.',
            self::Adoption => 'Borrowed into another culture with its name or traits, e.g. Reshef in Egypt.',
            self::Interpretation => 'Outsiders called a local God by their own God’s name, e.g. Herakles for Vāsudeva.',
            self::Supplanting => 'Took over the other God’s role or rank, e.g. Marduk supplanting Enlil.',
            self::Fission => 'One God split into separate Gods.',
            self::Demonization => 'Turned into a demon or lesser being by a later or rival tradition.',
            self::Deification => 'A human or hero raised to a God.',
        };
    }
}
