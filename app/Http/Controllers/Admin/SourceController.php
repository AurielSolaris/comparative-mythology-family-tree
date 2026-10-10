<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SourceKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSourceRequest;
use App\Http\Requests\Admin\UpdateSourceRequest;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Source;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Citations on entities (listed on the entity's edit page) and on
 * relations (listed on a page of their own).
 */
class SourceController extends Controller
{
    /**
     * The sources of one relation, with a form to add more.
     */
    public function relation(EntityRelation $relation): View
    {
        return view('admin.sources.relation', [
            'relation' => $relation->load(['entity', 'relatedEntity', 'tradition', 'sources']),
        ]);
    }

    public function storeForEntity(StoreSourceRequest $request, Entity $entity): RedirectResponse
    {
        $this->store($request, $entity);

        return redirect()->to(route('admin.entities.edit', $entity).'#sources')->with('status', 'Source added.');
    }

    public function storeForRelation(StoreSourceRequest $request, EntityRelation $relation): RedirectResponse
    {
        $this->store($request, $relation);

        return redirect()->route('admin.relations.sources', $relation)->with('status', 'Source added.');
    }

    public function edit(Source $source): View
    {
        return view('admin.sources.edit', [
            'source' => $source,
            'backUrl' => $this->ownerUrl($source),
        ]);
    }

    public function update(UpdateSourceRequest $request, Source $source): RedirectResponse
    {
        if ($source->kind === SourceKind::Link) {
            $source->update($request->safe()->only(['url', 'ref']));
        } else {
            $source->update(['ref' => $request->validated('ref')]);

            if ($request->hasFile('file')) {
                $source->replaceFile($request->file('file'));
            }
        }

        return redirect()->to($this->ownerUrl($source))->with('status', 'Source saved.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        $backUrl = $this->ownerUrl($source);
        $source->delete();

        return redirect()->to($backUrl)->with('status', 'Source removed.');
    }

    /**
     * Downloads an uploaded file under its original name. Files are kept
     * on the private disk, so this is the only way to reach them.
     */
    public function file(Source $source): StreamedResponse
    {
        abort_unless($source->hasStoredFile(), 404);

        return Storage::disk(Source::DISK)->download($source->path, $source->original_name ?? basename($source->path));
    }

    protected function store(StoreSourceRequest $request, Model $sourceable): void
    {
        $attributes = $request->validated('kind') === SourceKind::File->value
            ? Source::fileAttributes($request->file('file'))
            : ['kind' => SourceKind::Link, 'url' => $request->validated('url')];

        $sourceable->sources()->create([...$attributes, 'ref' => $request->validated('ref')]);
    }

    /**
     * Where the source is listed: its entity's edit page, or its relation's page.
     */
    protected function ownerUrl(Source $source): string
    {
        return $source->sourceable_type === 'entity_relation'
            ? route('admin.relations.sources', $source->sourceable_id)
            : route('admin.entities.edit', $source->sourceable_id).'#sources';
    }
}
