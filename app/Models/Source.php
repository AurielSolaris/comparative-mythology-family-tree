<?php

namespace App\Models;

use App\Enums\SourceKind;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
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
     * File types admins can upload: documents and images.
     *
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = ['pdf', 'txt', 'md', 'doc', 'docx', 'odt', 'rtf', 'epub', 'png', 'jpg', 'jpeg', 'webp', 'gif'];

    public const MAX_UPLOAD_KILOBYTES = 20 * 1024;

    /**
     * The largest upload that will actually get through: the app's limit,
     * or PHP's upload_max_filesize / post_max_size if lower.
     */
    public static function maxUploadKilobytes(): int
    {
        $phpLimits = array_filter(array_map(
            fn (string $setting) => (int) (ini_parse_quantity((string) ini_get($setting)) / 1024),
            ['upload_max_filesize', 'post_max_size'],
        ));

        return min(self::MAX_UPLOAD_KILOBYTES, ...($phpLimits ?: [self::MAX_UPLOAD_KILOBYTES]));
    }

    /**
     * The upload limit for help text, e.g. "20 MB" or "512 KB".
     */
    public static function maxUploadLabel(): string
    {
        $kilobytes = self::maxUploadKilobytes();

        return $kilobytes >= 1024 ? round($kilobytes / 1024, 1).' MB' : $kilobytes.' KB';
    }

    /**
     * Stores an upload on the source disk and returns the attributes of a
     * file source for it.
     *
     * @return array{kind: SourceKind, url: null, path: string, original_name: string}
     */
    public static function fileAttributes(UploadedFile $file): array
    {
        return [
            'kind' => SourceKind::File,
            'url' => null,
            'path' => Storage::disk(self::DISK)->putFile(self::DIRECTORY, $file),
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Swaps the stored file for a new upload, deleting the old one once
     * the source points at the new file.
     */
    public function replaceFile(UploadedFile $file): void
    {
        $oldPath = $this->path;

        $this->update(self::fileAttributes($file));

        if ($oldPath && $oldPath !== $this->path) {
            Storage::disk(self::DISK)->delete($oldPath);
        }
    }

    /**
     * What admins see in lists: the uploaded file's name, or the link
     * without its scheme.
     */
    public function label(): string
    {
        return $this->kind === SourceKind::File
            ? ($this->original_name ?? basename((string) $this->path))
            : preg_replace('#^https?://(www\.)?#', '', (string) $this->url);
    }

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
