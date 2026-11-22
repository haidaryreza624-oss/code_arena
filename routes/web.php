<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSubmissionController;
use App\Http\Controllers\Admin\ContestController;
use App\Http\Controllers\Admin\ContestQuestionController;
use App\Http\Controllers\Admin\ContestSubmissionController;
use App\Http\Controllers\Admin\ProblemController;
use App\Http\Controllers\Admin\TestCaseController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest:web')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('admin.login.store');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:web', 'admin'])
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');

        Route::prefix('contests')->name('contests.')->group(function () {
            Route::get('/', [ContestController::class, 'index'])->name('index');
            Route::get('/create', [ContestController::class, 'create'])->name('create');
            Route::post('/', [ContestController::class, 'store'])->name('store');
            Route::get('/{contest}', [ContestController::class, 'show'])->name('show');
            Route::put('/{contest}', [ContestController::class, 'update'])->name('update');
            Route::delete('/{contest}', [ContestController::class, 'destroy'])->name('destroy');

            Route::post('/{contest}/problems', [ContestController::class, 'assignProblem'])->name('problems.assign');
            Route::delete('/{contest}/problems/{contestProblem}', [ContestController::class, 'unassignProblem'])
                ->name('problems.unassign');
            Route::post('/{contest}/teams', [ContestController::class, 'assignTeams'])->name('teams.assign');
            Route::delete('/{contest}/teams/{participation}', [ContestController::class, 'unassignTeam'])
                ->name('teams.unassign');

            Route::post('/{contest}/approve', [ContestController::class, 'approve'])->name('approve');
            Route::post('/{contest}/start', [ContestController::class, 'start'])->name('start');
            Route::post('/{contest}/end', [ContestController::class, 'end'])->name('end');
            Route::put('/{contest}/freeze', [ContestController::class, 'updateFreeze'])->name('freeze');
            Route::post('/{contest}/freeze-now', [ContestController::class, 'freezeNow'])->name('freeze-now');

            Route::post('/{contest}/credentials', [ContestController::class, 'generateCredentials'])
                ->name('credentials');
            Route::post('/{contest}/teams/{participation}/credentials', [ContestController::class, 'generateCredentials'])
                ->name('teams.credentials');

            Route::get('/{contest}/problems/print', [ContestController::class, 'printProblems'])
                ->name('problems.print');
            Route::get('/{contest}/questions/{question}', [ContestQuestionController::class, 'show'])
                ->name('questions.show');
            Route::put('/{contest}/questions/{question}', [ContestQuestionController::class, 'answer'])
                ->name('questions.answer');

            Route::get('/{contest}/submissions', [ContestSubmissionController::class, 'index'])
                ->name('submissions.index');
            Route::get('/{contest}/submissions/{submission}', [ContestSubmissionController::class, 'show'])
                ->name('submissions.show');
            Route::post('/{contest}/submissions/{submission}/rejudge', [ContestSubmissionController::class, 'rejudge'])
                ->name('submissions.rejudge');
        });

        Route::prefix('problems')->name('problems.')->group(function () {
            Route::get('/', [ProblemController::class, 'index'])->name('index');
            Route::get('/create', [ProblemController::class, 'create'])->name('create');
            Route::post('/', [ProblemController::class, 'store'])->name('store');
            Route::get('/{problem}', [ProblemController::class, 'show'])->name('show');
            Route::get('/{problem}/edit', [ProblemController::class, 'edit'])->name('edit');
            Route::put('/{problem}', [ProblemController::class, 'update'])->name('update');
            Route::delete('/{problem}', [ProblemController::class, 'destroy'])->name('destroy');

            Route::post('/{problem}/test-cases', [TestCaseController::class, 'store'])
                ->name('test-cases.store');
            Route::put('/{problem}/test-cases/{testCase}', [TestCaseController::class, 'update'])
                ->name('test-cases.update');
            Route::delete('/{problem}/test-cases/{testCase}', [TestCaseController::class, 'destroy'])
                ->name('test-cases.destroy');
        });

        Route::prefix('teams')->name('teams.')->group(function () {
            Route::get('/', [TeamController::class, 'index'])->name('index');
            Route::post('/', [TeamController::class, 'store'])->name('store');
            Route::post('/import', [TeamController::class, 'import'])->name('import');
            Route::post('/bulk-delete', [TeamController::class, 'bulkDestroy'])->name('bulk-delete');
            Route::get('/{team}', [TeamController::class, 'show'])->name('show');
            Route::put('/{team}', [TeamController::class, 'update'])->name('update');
            Route::delete('/{team}', [TeamController::class, 'destroy'])->name('destroy');

            Route::post('/{team}/members', [TeamMemberController::class, 'store'])->name('members.store');
            Route::put('/{team}/members/{teamMember}', [TeamMemberController::class, 'update'])
                ->name('members.update');
            Route::delete('/{team}/members/{teamMember}', [TeamMemberController::class, 'destroy'])
                ->name('members.destroy');
        });

        Route::prefix('submissions')->name('submissions.')->group(function () {
            Route::get('/', [AdminSubmissionController::class, 'index'])->name('index');
        });

        Route::prefix('questions')->name('questions.')->group(function () {
            Route::view('/', 'admin.questions.index')->name('index');
        });
    });
