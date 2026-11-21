<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\ProblemController;
use App\Http\Controllers\Admin\TestCaseController;
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
            Route::view('/', 'admin.contests.index')->name('index');
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
            Route::view('/', 'admin.teams.index')->name('index');
        });

        Route::prefix('submissions')->name('submissions.')->group(function () {
            Route::view('/', 'admin.submissions.index')->name('index');
        });

        Route::prefix('questions')->name('questions.')->group(function () {
            Route::view('/', 'admin.questions.index')->name('index');
        });
    });
