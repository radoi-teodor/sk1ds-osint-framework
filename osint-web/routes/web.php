<?php

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\GraphApiController;
use App\Http\Controllers\GraphController;
use App\Http\Controllers\InvestigationJobController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TransformationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ---- setup ----
Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

// ---- auth ----
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'attempt']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ---- invites (public) ----
Route::get('/invite/{token}', [InviteController::class, 'show'])->name('invite.show');
Route::post('/invite/{token}', [InviteController::class, 'accept'])->name('invite.accept');

// ---- authenticated ----
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect('/projects'));

    // projects
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{project}/graphs', [ProjectController::class, 'storeGraph']);

    // graphs (view + delete)
    Route::get('/graphs/{graph}', [GraphController::class, 'show'])->name('graphs.show');
    Route::delete('/graphs/{graph}', [GraphController::class, 'destroy']);

    // graph JSON api
    Route::prefix('api/graphs/{graph}')->group(function () {
        Route::get('/', [GraphApiController::class, 'index']);
        Route::post('/nodes', [GraphApiController::class, 'storeNode']);
        Route::patch('/nodes/{cyId}', [GraphApiController::class, 'updateNode']);
        Route::delete('/nodes/{cyId}', [GraphApiController::class, 'destroyNode']);
        Route::post('/edges', [GraphApiController::class, 'storeEdge']);
        Route::delete('/edges/{cyId}', [GraphApiController::class, 'destroyEdge']);
        Route::post('/run-transform', [GraphApiController::class, 'runTransform']);
        Route::post('/run-template', [TemplateController::class, 'run']);
        Route::get('/jobs', fn (\App\Models\Graph $graph) => app(InvestigationJobController::class)->indexForGraph($graph->id));
    });

    // Job polling (graph-agnostic)
    Route::get('/api/jobs/{job}', [InvestigationJobController::class, 'show']);

    // templates
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [TemplateController::class, 'store']);

    // transformations (code editor)
    Route::get('/transformations', [TransformationController::class, 'index'])->name('transformations.index');
    Route::get('/transformations/new', [TransformationController::class, 'create'])->name('transformations.create');
    Route::post('/transformations', [TransformationController::class, 'store']);
    Route::get('/transformations/{name}/edit', [TransformationController::class, 'edit'])
        ->where('name', '[A-Za-z0-9._\-]+')
        ->name('transformations.edit');
    Route::put('/transformations/{name}', [TransformationController::class, 'update'])
        ->where('name', '[A-Za-z0-9._\-]+');
    Route::delete('/transformations/{name}', [TransformationController::class, 'destroy'])
        ->where('name', '[A-Za-z0-9._\-]+');
    Route::post('/api/transformations/validate', [TransformationController::class, 'validateSource']);
    Route::post('/api/transformations/reload', [TransformationController::class, 'reload']);

    // api keys
    Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::delete('/api-keys/{api_key}', [ApiKeyController::class, 'destroy']);

    // users + invites
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users/invite', [UserController::class, 'invite']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});
