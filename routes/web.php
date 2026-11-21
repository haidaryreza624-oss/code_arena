<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Route;



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
            Route::view('/', 'admin.problems.index')->name('index');
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
