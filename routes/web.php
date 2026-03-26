<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LanCoreAuthController;
use App\Http\Controllers\Chat\ChatSettingsController;
use App\Http\Controllers\Chat\ModerationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessageController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('chat');
    }

    if (config('lancore.enabled')) {
        return redirect()->route('lancore.redirect');
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
    ->middleware(['auth', 'verified'])
    ->name('chat');

Route::get('/messages', [MessageController::class, 'index'])
    ->name('messages.index');

Route::post('/messages', [MessageController::class, 'store'])
    ->middleware(['auth', 'can.chat'])
    ->name('messages.store');

Route::post('/chat/mark-read', [MessageController::class, 'markRead'])
    ->middleware(['auth'])
    ->name('chat.mark-read');

// Chat moderation & settings routes (moderator/admin only)
Route::middleware(['auth', 'moderator'])->prefix('chat')->name('chat.')->group(function () {
    Route::get('/settings', [ChatSettingsController::class, 'index'])->name('settings');

    // Filter chain management
    Route::post('/filters', [ChatSettingsController::class, 'storeFilter'])->name('filters.store');
    Route::put('/filters/{filterChain}', [ChatSettingsController::class, 'updateFilter'])->name('filters.update');
    Route::delete('/filters/{filterChain}', [ChatSettingsController::class, 'destroyFilter'])->name('filters.destroy');

    // Slow mode
    Route::put('/slow-mode', [ChatSettingsController::class, 'updateSlowMode'])->name('slow-mode.update');

    // User moderation
    Route::get('/moderation/users', [ModerationController::class, 'users'])->name('moderation.users');
    Route::post('/moderation/users/{user}/timeout', [ModerationController::class, 'timeout'])->name('moderation.timeout');
    Route::delete('/moderation/users/{user}/timeout', [ModerationController::class, 'relieveTimeout'])->name('moderation.relieve-timeout');
    Route::post('/moderation/users/{user}/block', [ModerationController::class, 'block'])->name('moderation.block');
    Route::delete('/moderation/users/{user}/block', [ModerationController::class, 'unblock'])->name('moderation.unblock');
});

// LanCore SSO routes
Route::prefix('auth/lancore')->name('lancore.')->group(function () {
    Route::get('/redirect', [LanCoreAuthController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [LanCoreAuthController::class, 'callback'])->name('callback');
    Route::get('/status', [LanCoreAuthController::class, 'status'])->name('status');
});

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
