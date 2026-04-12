<?php

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TotpChallengeController;
use App\Http\Controllers\Auth\TotpSetupController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\GeneratorController;
use App\Http\Controllers\GraphApiController;
use App\Http\Controllers\GraphController;
use App\Http\Controllers\InvestigationJobController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SlaveController;
use App\Http\Controllers\SlaveSetupScriptController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TransformationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ---- SDK docs (public, no auth) ----
Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('/docs/search.json', [DocsController::class, 'searchIndex']);
Route::get('/docs/{page}', [DocsController::class, 'show'])->name('docs.show');

// ---- setup ----
Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

// ---- auth ----
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'attempt']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ---- TOTP challenge (mid-login, no auth) ----
Route::get('/auth/totp-challenge', [TotpChallengeController::class, 'showChallenge'])->name('totp.challenge');
Route::post('/auth/totp-challenge', [TotpChallengeController::class, 'verifyChallenge'])->middleware('throttle:5,1');
Route::post('/auth/totp-cancel', [TotpChallengeController::class, 'cancel']);

// ---- invites (public) ----
Route::get('/invite/{token}', [InviteController::class, 'show'])->name('invite.show');
Route::post('/invite/{token}', [InviteController::class, 'accept'])->name('invite.accept');

// ---- authenticated ----
Route::middleware(['auth', 'totp.verified'])->group(function () {
    Route::get('/', fn () => redirect('/projects'));

    // profile / security
    Route::get('/profile/security', [TotpSetupController::class, 'show'])->name('profile.security');
    Route::get('/profile/security/totp/enable', [TotpSetupController::class, 'create']);
    Route::post('/profile/security/totp/enable', [TotpSetupController::class, 'store']);
    Route::post('/profile/security/totp/disable', [TotpSetupController::class, 'destroy']);
    Route::post('/profile/security/totp/recovery-codes', [TotpSetupController::class, 'regenerateRecoveryCodes']);

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
        Route::post('/report/flag', [\App\Http\Controllers\ReportController::class, 'flag']);
        Route::post('/report/flag-all', [\App\Http\Controllers\ReportController::class, 'flagAll']);
        Route::post('/report/generate', [\App\Http\Controllers\ReportController::class, 'generate']);
    });

    // Report polling + download
    Route::get('/api/reports/{report_job}', [\App\Http\Controllers\ReportController::class, 'poll']);
    Route::get('/api/reports/{report_job}/download', [\App\Http\Controllers\ReportController::class, 'download']);

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

    // generators (code editor)
    Route::get('/generators', [GeneratorController::class, 'index'])->name('generators.index');
    Route::get('/generators/new', [GeneratorController::class, 'create'])->name('generators.create');
    Route::post('/generators', [GeneratorController::class, 'store']);
    Route::get('/generators/{name}/edit', [GeneratorController::class, 'edit'])
        ->where('name', '[A-Za-z0-9._\-]+');
    Route::put('/generators/{name}', [GeneratorController::class, 'update'])
        ->where('name', '[A-Za-z0-9._\-]+');
    Route::delete('/generators/{name}', [GeneratorController::class, 'destroy'])
        ->where('name', '[A-Za-z0-9._\-]+');

    // file manager
    Route::get('/files', [FileManagerController::class, 'index'])->name('files.index');
    Route::get('/api/files/list', [FileManagerController::class, 'apiList']);
    Route::get('/api/files', [FileManagerController::class, 'apiAll']);
    Route::post('/api/files/upload', [FileManagerController::class, 'apiUpload']);
    Route::post('/api/files/folder', [FileManagerController::class, 'apiCreateFolder']);
    Route::patch('/api/files/{uploaded_file}/rename', [FileManagerController::class, 'apiRenameFile']);
    Route::patch('/api/files/{uploaded_file}/move', [FileManagerController::class, 'apiMoveFile']);
    Route::delete('/api/files/{uploaded_file}', [FileManagerController::class, 'apiDeleteFile']);
    Route::post('/api/files/folder/rename', [FileManagerController::class, 'apiRenameFolder']);
    Route::post('/api/files/folder/delete', [FileManagerController::class, 'apiDeleteFolder']);

    // api keys
    Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::delete('/api-keys/{api_key}', [ApiKeyController::class, 'destroy']);

    // slaves
    Route::get('/slaves', [SlaveController::class, 'index'])->name('slaves.index');
    Route::get('/slaves/create', [SlaveController::class, 'create']);
    Route::post('/slaves', [SlaveController::class, 'store']);
    Route::get('/slaves/{slave}/edit', [SlaveController::class, 'edit']);
    Route::put('/slaves/{slave}', [SlaveController::class, 'update']);
    Route::delete('/slaves/{slave}', [SlaveController::class, 'destroy']);
    Route::post('/slaves/{slave}/test', [SlaveController::class, 'test']);
    Route::get('/slaves/{slave}/setup', [SlaveController::class, 'showSetup']);
    Route::post('/slaves/{slave}/setup', [SlaveController::class, 'runSetup']);
    Route::get('/slaves/{slave}/setup/runs/{run}', [SlaveController::class, 'showRun']);
    Route::get('/api/slaves/{slave}/setup/runs/{run}', [SlaveController::class, 'pollRun']);

    // slave setup scripts
    Route::get('/slaves/scripts', [SlaveSetupScriptController::class, 'index'])->name('slaves.scripts');
    Route::post('/slaves/scripts', [SlaveSetupScriptController::class, 'store']);
    Route::get('/slaves/scripts/{script}/edit', [SlaveSetupScriptController::class, 'edit']);
    Route::put('/slaves/scripts/{script}', [SlaveSetupScriptController::class, 'update']);
    Route::delete('/slaves/scripts/{script}', [SlaveSetupScriptController::class, 'destroy']);
    Route::post('/slaves/scripts/{script}/default', [SlaveSetupScriptController::class, 'setDefault']);

    // users + invites
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users/invite', [UserController::class, 'invite']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});
