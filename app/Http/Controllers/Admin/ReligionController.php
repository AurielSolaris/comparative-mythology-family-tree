<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReligionRequest;
use App\Models\Religion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReligionController extends Controller
{
    public function index(): View
    {
        return view('admin.religions.index', [
            'religions' => Religion::query()->withMemberCount()->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.religions.create', ['religion' => new Religion]);
    }

    public function store(ReligionRequest $request): RedirectResponse
    {
        $religion = Religion::create($request->validated());

        return redirect()->route('admin.religions.index')->with('status', "Added {$religion->name}.");
    }

    public function edit(Religion $religion): View
    {
        return view('admin.religions.edit', ['religion' => $religion]);
    }

    public function update(ReligionRequest $request, Religion $religion): RedirectResponse
    {
        $religion->update($request->validated());

        return redirect()->route('admin.religions.index')->with('status', "Saved {$religion->name}.");
    }

    public function destroy(Religion $religion): RedirectResponse
    {
        if ($religion->entities()->exists()) {
            return back()->with('error', "{$religion->name} is still the primary religion of some entities. Move or delete them first.");
        }

        $religion->delete();

        return redirect()->route('admin.religions.index')->with('status', "Deleted {$religion->name}.");
    }
}
