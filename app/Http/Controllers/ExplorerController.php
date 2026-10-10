<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Http\Requests\CompareReligionsRequest;
use App\Models\Entity;
use App\Models\Religion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

/**
 * The public canvas explorer. Every page is the same canvas; they differ
 * only in which graph is loaded first.
 */
class ExplorerController extends Controller
{
    public function home(): View
    {
        return $this->explorer(title: null, graphUrl: null);
    }

    public function entity(Entity $entity): View
    {
        return $this->explorer($entity->name, route('graph.entities', $entity));
    }

    public function religion(Religion $religion): View
    {
        return $this->explorer($religion->name, route('graph.religions', $religion), new Collection([$religion]));
    }

    public function compare(CompareReligionsRequest $request): View
    {
        $religions = $request->religions();

        return $this->explorer(
            $religions->pluck('name')->join(' · '),
            route('graph.compare', ['religions' => $religions->pluck('slug')->join(',')]),
            $religions,
        );
    }

    /**
     * @param  Collection<int, Religion>|null  $selected
     */
    protected function explorer(?string $title, ?string $graphUrl, ?Collection $selected = null): View
    {
        return view('explorer', [
            'title' => $title,
            'graphUrl' => $graphUrl,
            'selectedReligions' => $selected?->pluck('slug')->all() ?? [],
            'religions' => Religion::query()->withMemberCount()->orderBy('name')->get(),
            'genders' => Gender::cases(),
        ]);
    }
}
