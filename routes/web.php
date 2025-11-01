<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Models\User;
use App\Models\Message;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('chat');
    }

    return Inertia::render('Landing', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('dashboard/statistics', [DashboardController::class, 'statistics'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard.statistics');

// Chat routes (MVP)
Route::get('/chat', [MessageController::class, 'page'])
    ->middleware(['auth','verified'])
    ->name('chat');

Route::get('/messages', [MessageController::class, 'index'])
    ->name('messages.index');

Route::post('/messages', [MessageController::class, 'store'])
    ->middleware(['auth'])
    ->name('messages.store');

// Admin area routes
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Admin dashboard (accessible to moderator, admin, super_admin)
    Route::get('/', function () {
        abort_unless(auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'admin', 'moderator']), 403);
        return Inertia::render('admin/Index');
    })->name('index');

    // User management routes (Admin and Super Admin only - authorization in controller)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
});

require __DIR__.'/settings.php';
