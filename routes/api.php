<?php

use App\Http\Controllers\AnnouncementController;
use Illuminate\Support\Facades\Route;

Route::post('/announcements', [AnnouncementController::class, 'store'])
    ->middleware('verify.webhook.signature');
