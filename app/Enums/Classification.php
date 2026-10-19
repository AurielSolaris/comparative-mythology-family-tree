<?php

namespace App\Enums;

/**
 * What an entity is within one tradition. The same entity can be a God in
 * one tradition and a demon in another: Indra is a deva in the Veda and a
 * daēva in the Avesta. The tradition's own word goes in the separate
 * classification term ("deva", "daēva", "šiqqûṣ").
 */
enum Classification: string
{
    case God = 'god';
    case RejectedGod = 'rejected_god';
    case Demon = 'demon';
    case Spirit = 'spirit';

    public function label(): string
    {
        return match ($this) {
            self::God => 'God',
            self::RejectedGod => 'Rejected God',
            self::Demon => 'Demon',
            self::Spirit => 'Spirit',
        };
    }

    /**
     * What the classification means, for admins choosing one.
     */
    public function description(): string
    {
        return match ($this) {
            self::God => 'Worshipped as a God in this tradition.',
            self::RejectedGod => 'Acknowledged as someone else’s God and condemned, e.g. Chemosh as an “abomination” in the Hebrew Bible.',
            self::Demon => 'An evil or hostile being, e.g. Indra as a daēva in the Avesta.',
            self::Spirit => 'A spirit, angel or other being who is neither worshipped nor evil here.',
        };
    }
}
