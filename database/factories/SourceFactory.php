<?php

namespace Database\Factories;

use App\Enums\SourceKind;
use App\Models\Entity;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    /**
     * Define the model's default state: a link source on an entity.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sourceable_type' => 'entity',
            'sourceable_id' => Entity::factory(),
            'kind' => SourceKind::Link,
            'url' => fake()->url(),
            'ref' => 'line '.fake()->numberBetween(1, 200),
        ];
    }

    /**
     * A file source with a small text file stored on the source disk.
     */
    public function file(string $originalName = 'tablet.txt'): static
    {
        return $this->state(fn () => [
            'kind' => SourceKind::File,
            'url' => null,
            'path' => Storage::disk(Source::DISK)->putFile(Source::DIRECTORY, UploadedFile::fake()->createWithContent($originalName, fake()->paragraph())),
            'original_name' => $originalName,
        ]);
    }
}
