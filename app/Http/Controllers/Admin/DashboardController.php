<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Religion;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'entityCount' => Entity::query()->count(),
            'religionCount' => Religion::query()->count(),
            'relationCount' => EntityRelation::query()->count(),
            'recentEntities' => Entity::query()->with('religion')->latest('updated_at')->limit(8)->get(),
            'religions' => Religion::query()->withMemberCount()->get()->sortByDesc->memberCount()->take(8),
        ]);
    }
}
