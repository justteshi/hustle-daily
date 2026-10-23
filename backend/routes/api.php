<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsumptionController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\FoodController;
use App\Http\Controllers\Api\MealCategoryController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\TodayController;
use App\Http\Middleware\VerifyBrowserPassword;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::middleware(['auth:sanctum', VerifyBrowserPassword::class])->group(function () {
        Route::get('/account/export', [AccountController::class, 'export']);
        Route::delete('/account', [AccountController::class, 'destroy']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/devices/{device}', [DeviceController::class, 'show']);
        Route::get('/dashboard/today', TodayController::class);
        Route::get('/meal-categories', [MealCategoryController::class, 'index']);
        Route::get('/foods', [FoodController::class, 'index']);
        Route::post('/foods', [FoodController::class, 'store']);
        Route::get('/foods/{food}', [FoodController::class, 'show']);
        Route::patch('/foods/{food}', [FoodController::class, 'update']);
        Route::get('/consumptions', [ConsumptionController::class, 'index']);
        Route::get('/consumptions/dates', [ConsumptionController::class, 'dates']);
        Route::post('/consumptions', [ConsumptionController::class, 'store']);
        Route::patch('/consumptions/{consumption}', [ConsumptionController::class, 'update']);
        Route::delete('/consumptions/{consumption}', [ConsumptionController::class, 'destroy']);
        Route::get('/recipes', [RecipeController::class, 'index']);
        Route::post('/recipes', [RecipeController::class, 'store']);
        Route::get('/recipes/{recipe}', [RecipeController::class, 'show']);
        Route::patch('/recipes/{recipe}', [RecipeController::class, 'update']);
    });
});
