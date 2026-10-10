<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Androgynous = 'androgynous';
    case Genderless = 'genderless';
    case Varies = 'varies';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Androgynous => 'Androgynous',
            self::Genderless => 'Genderless',
            self::Varies => 'Varies / shifts',
        };
    }

    /**
     * Short mark shown next to the religion on tree nodes.
     */
    public function symbol(): string
    {
        return match ($this) {
            self::Male => '♂',
            self::Female => '♀',
            self::Androgynous => '⚥',
            self::Genderless => '∅',
            self::Varies => '⚧',
        };
    }
}
