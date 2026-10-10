<?php

namespace App\Enums;

/**
 * Where a source lives: an uploaded file kept in storage, or a link to
 * somewhere on the web.
 */
enum SourceKind: string
{
    case File = 'file';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::File => 'File',
            self::Link => 'Link',
        };
    }
}
