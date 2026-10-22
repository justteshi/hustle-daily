<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $defaults = ['breakfast', 'lunch', 'dinner', 'snack', 'pre-workout', 'post-workout', 'drinks', 'cooking-ingredients'];
        foreach ($defaults as $order => $key) {
            $request->user()->mealCategories()->firstOrCreate(
                ['key' => $key],
                ['name' => ucwords(str_replace('-', ' ', $key)), 'sort_order' => $order],
            );
        }

        return response()->json(['data' => $request->user()->mealCategories()->orderBy('sort_order')->get()]);
    }
}
