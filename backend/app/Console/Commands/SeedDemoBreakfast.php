<?php

namespace App\Console\Commands;

use App\Models\Food;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('hustle:seed-demo-breakfast {email}')]
#[Description('Add illustrative breakfast foods and a reusable meal to one existing user')]
class SeedDemoBreakfast extends Command
{
    public function handle(): int
    {
        $user = User::where('email', mb_strtolower((string) $this->argument('email')))->first();
        if (! $user) {
            $this->error('No account exists with that email address.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user): void {
            $user->mealCategories()->firstOrCreate(['key' => 'breakfast'], [
                'name' => 'Breakfast', 'sort_order' => 10, 'is_active' => true,
            ]);

            $skyr = $this->food($user, 'skyr', 'Skyr', 'Demo Dairy', 'g', [
                'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0.2,
            ]);
            $milk = $this->food($user, 'milk', 'Milk', 'Demo Dairy', 'ml', [
                'calories' => 50, 'protein' => 3.4, 'carbohydrates' => 5, 'fat' => 2,
            ]);
            $powder = $this->food($user, 'whey', 'Whey protein powder', 'Demo Sports', 'g', [
                'calories' => 390, 'protein' => 78, 'carbohydrates' => 7, 'fat' => 6,
            ]);
            $scoop = $powder->portions()->firstOrCreate(['label' => 'scoop (demo: 30 g)'], [
                'base_amount' => 30, 'base_unit' => 'g',
            ]);

            $recipe = $user->recipes()->firstOrCreate(['idempotency_key' => 'demo-breakfast-v1'], [
                'name' => 'Demo breakfast: skyr + whey + milk',
                'yield_servings' => 1,
                'request_fingerprint' => hash('sha256', 'demo-breakfast-v1'),
            ]);
            if ($recipe->wasRecentlyCreated) {
                $recipe->ingredients()->createMany([
                    ['food_id' => $skyr->id, 'quantity' => 100, 'unit' => 'g', 'sort_order' => 0],
                    ['food_id' => $powder->id, 'food_portion_id' => $scoop->id, 'quantity' => 1, 'unit' => 'portion', 'sort_order' => 1],
                    ['food_id' => $milk->id, 'quantity' => 80, 'unit' => 'ml', 'sort_order' => 2],
                ]);
            }
        });

        $this->info('Demo breakfast is available for '.$user->email.'. Existing entries were left unchanged.');

        return self::SUCCESS;
    }

    /** @param array{calories: int|float, protein: int|float, carbohydrates: int|float, fat: int|float} $nutrition */
    private function food(User $user, string $key, string $name, string $brand, string $basisUnit, array $nutrition): Food
    {
        $idempotencyKey = 'demo-breakfast-'.$key.'-v1';
        $food = $user->foods()->firstOrCreate(['idempotency_key' => $idempotencyKey], [
            'name' => $name, 'brand' => $brand,
            'request_fingerprint' => hash('sha256', $idempotencyKey),
        ]);
        if ($food->wasRecentlyCreated) {
            $food->nutrition()->create([
                'basis_amount' => 100, 'basis_unit' => $basisUnit, ...$nutrition,
            ]);
        }

        return $food;
    }
}
