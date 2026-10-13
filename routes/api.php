<?php

declare(strict_types=1);

use App\Http\Controllers\WebhookTransactionController;
use App\Http\Middleware\SetPanelLocale;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/transactions', WebhookTransactionController::class)
    ->middleware([SetPanelLocale::class, 'throttle:60,1']);
