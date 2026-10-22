<?php

use App\Http\Controllers\WebSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/session/csrf', [WebSessionController::class, 'csrf']);
Route::post('/session/login', [WebSessionController::class, 'login'])->middleware('throttle:5,1');
Route::post('/session/logout', [WebSessionController::class, 'logout'])->middleware('auth:web');

Route::get('/', function () {
    return view('welcome');
});
