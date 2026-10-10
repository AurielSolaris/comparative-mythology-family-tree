<?php

namespace App\Models;

use App\Enums\SourceKind;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * A citation backing an entity or an entity relation: either an uploaded
 * file (kept on the local disk under sources/) or a link, plus an optional
 * line / verse / page reference.
 */
#[Fillable(['kind', 'url', 'path', 'original_name', 'ref'])]
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    public const DISK = 'local';

    public const DIRECTORY = 'sources';

    /**
     * Uploaded files go with their source.
     */
    protected static function booted(): void
    {
        static::deleted(function (Source $source) {
            if ($source->kind === SourceKind::File && $source->path) {
                Storage::disk(self::DISK)->delete($source->path);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SourceKind::class,
        ];
    }

    /**
     * The entity or entity relation this source backs.
     *
     * @return MorphTo<Model, $this>
     */
    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether this is a file source whose file is actually in storage.
     */
    public function hasStoredFile(): bool
    {
        return $this->kind === SourceKind::File
            && $this->path !== null
            && Storage::disk(self::DISK)->exists($this->path);
    }

    /**
     * Where the file sits inside an export archive, e.g. "sources/abc123.pdf".
     */
    public function exportPath(): ?string
    {
        return $this->kind === SourceKind::File && $this->path !== null
            ? self::DIRECTORY.'/'.basename($this->path)
            : null;
    }
}
