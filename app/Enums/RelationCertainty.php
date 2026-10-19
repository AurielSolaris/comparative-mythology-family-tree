<?php

namespace App\Enums;

/**
 * Who stands behind a relation and how firmly: the tradition itself says
 * so, scholars broadly agree, a scholar has proposed it, or it is disputed
 * (e.g. Bhandarkar and Raychaudhuri disagreeing about Vāsudeva and Krishna).
 */
enum RelationCertainty: string
{
    case Attested = 'attested';
    case Consensus = 'consensus';
    case Hypothesis = 'hypothesis';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Attested => 'Attested in the tradition',
            self::Consensus => 'Scholarly consensus',
            self::Hypothesis => 'Scholarly hypothesis',
            self::Disputed => 'Disputed',
        };
    }
}
