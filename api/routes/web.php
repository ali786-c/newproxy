<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/verify-email-link', [\App\Http\Controllers\AuthController::class, 'verifyEmailLink'])->name('verification.verify');

