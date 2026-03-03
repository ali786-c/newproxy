<?php

use Illuminate\Support\Facades\Route;

Route::get('/test-route', function () {
    return response()->json(['message' => 'Web route is working (no prefix)']);
});

Route::get('/api/test-route', function () {
    return response()->json(['message' => 'Web route is working (with api prefix)']);
});

Route::get('/', function () {
    return view('welcome');
});
