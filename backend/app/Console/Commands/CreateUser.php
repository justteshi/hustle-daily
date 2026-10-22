<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateUser extends Command
{
    protected $signature = 'hustle:create-user {email} {--admin}';

    protected $description = 'Create a private user with an interactively entered password';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
            $this->error('Enter a valid, unused email address.');

            return self::FAILURE;
        }

        $name = $this->ask('Name');
        $password = $this->secret('Password (at least 12 characters)');
        $confirm = $this->secret('Confirm password');
        if (mb_strlen((string) $password) < 12 || $password !== $confirm) {
            $this->error('Passwords must match and contain at least 12 characters.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->is_admin = (bool) $this->option('admin');
        $user->save();
        $user->settings()->create([]);
        $this->info('Created user '.$user->id);

        return self::SUCCESS;
    }
}
