<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\RolesWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/announcements', [AnnouncementController::class, 'store'])
    ->middleware('verify.webhook.signature');

Route::post('/webhooks/roles', RolesWebhookController::class)
    ->middleware('verify.webhook.signature');
