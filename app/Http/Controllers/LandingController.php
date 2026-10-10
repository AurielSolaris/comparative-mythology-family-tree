<?php

namespace App\Http\Controllers;

use App\Models\Religion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public front page. Admins have no use for it and go straight to
 * their dashboard.
 */
class LandingController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()?->can('access-admin')) {
            return redirect()->route('admin.dashboard');
        }

        return view('landing', [
            'religions' => Religion::query()->withMemberCount()->orderBy('name')->get(),
        ]);
    }
}
