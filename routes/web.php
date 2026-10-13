<?php

declare(strict_types=1);

use App\Http\Controllers\ReferralRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/r/{referral_code}', ReferralRedirectController::class)
    ->middleware('throttle:60,1')
    ->name('referral.redirect');
