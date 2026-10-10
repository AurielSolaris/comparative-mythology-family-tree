<?php

namespace App\Http\Controllers;

use App\Models\Religion;
use Illuminate\View\View;

/**
 * The public front page, shown to everyone whether or not they are logged in.
 */
class LandingController extends Controller
{
    public function __invoke(): View
    {
        return view('landing', [
            'religions' => Religion::query()->withMemberCount()->orderBy('name')->get(),
        ]);
    }
}
