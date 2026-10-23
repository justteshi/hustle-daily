<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

#[Signature('hustle:reset-user-password {email}')]
#[Description('Reset a private account password from the server console')]
class ResetUserPassword extends Command
{
    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error('Account not found.');

            return self::FAILURE;
        }

        $password = $this->secret('New password (at least 12 characters)');
        $confirm = $this->secret('Confirm new password');
        if (mb_strlen((string) $password) < 12 || $password !== $confirm) {
            $this->error('Passwords must match and contain at least 12 characters.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $password): void {
            $user->password = Hash::make($password);
            $user->save();
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
        $this->info('Password changed. Existing mobile tokens were revoked; browser sessions with an old password hash will sign out on their next request.');

        return self::SUCCESS;
    }
}
