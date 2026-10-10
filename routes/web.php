<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ExplorerController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GraphController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\SearchSuggestionController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/home', LandingController::class);
Route::get('/explore', [ExplorerController::class, 'home'])->name('explore');
Route::get('/entities/{entity:slug}', [ExplorerController::class, 'entity'])->name('entities.show');
Route::get('/religions/{religion:slug}', [ExplorerController::class, 'religion'])->name('religions.show');
Route::get('/compare', [ExplorerController::class, 'compare'])->name('compare');

Route::prefix('graph')->name('graph.')->group(function () {
    Route::get('/entities/{entity:slug}', [GraphController::class, 'entity'])->name('entities');
    Route::get('/religions/{religion:slug}', [GraphController::class, 'religion'])->name('religions');
    Route::get('/compare', [GraphController::class, 'compare'])->name('compare');
});

Route::get('/export/{format}', ExportController::class)
    ->whereIn('format', ['json', 'sqlite', 'db'])
    ->middleware('throttle:20,1')
    ->name('export');

Route::get('/search/suggestions', SearchSuggestionController::class)
    ->middleware('throttle:120,1')
    ->name('search.suggestions');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::resource('religions', Admin\ReligionController::class)->except('show');
    Route::patch('entities/order', [Admin\EntityController::class, 'reorder'])->name('entities.reorder');
    Route::resource('entities', Admin\EntityController::class)->except('show');
    Route::resource('entities.relations', Admin\EntityRelationController::class)->shallow()->only(['store', 'destroy']);
    Route::patch('relations/{relation}/sides', [Admin\EntityRelationController::class, 'swapSides'])->name('relations.sides');

    Route::post('entities/{entity}/sources', [Admin\SourceController::class, 'storeForEntity'])->name('entities.sources.store');
    Route::get('relations/{relation}/sources', [Admin\SourceController::class, 'relation'])->name('relations.sources');
    Route::post('relations/{relation}/sources', [Admin\SourceController::class, 'storeForRelation'])->name('relations.sources.store');
    Route::get('sources/{source}/file', [Admin\SourceController::class, 'file'])->name('sources.file');
    Route::resource('sources', Admin\SourceController::class)->only(['edit', 'update', 'destroy']);
});
