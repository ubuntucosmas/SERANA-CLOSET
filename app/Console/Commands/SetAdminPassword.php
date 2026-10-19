<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Create a Studio admin, or give an existing one a new password, without
 * seeders or tinker. The password is typed hidden (never in shell history)
 * or generated and shown once. Signing out everywhere else is part of the
 * reset: an old session must not outlive the old password.
 */
class SetAdminPassword extends Command
{
    protected $signature = 'serana:admin {email : The admin\'s sign-in email}
        {--name= : Display name when creating the account}
        {--generate : Generate a strong password and show it once}';

    protected $description = 'Create a Studio admin or reset an admin password';

    public const MIN_LENGTH = 12;

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if ($user && ! $user->isAdmin()) {
            $this->error("{$email} is a customer account. Use a separate email for the Studio.");

            return self::FAILURE;
        }

        if ($this->option('generate')) {
            $password = Str::password(20, symbols: false);
        } else {
            $password = (string) $this->secret('New password (at least ' . self::MIN_LENGTH . ' characters)');
            if ($password !== (string) $this->secret('Repeat the password')) {
                $this->error('The passwords do not match. Nothing was changed.');

                return self::FAILURE;
            }
        }
        if (mb_strlen($password) < self::MIN_LENGTH || in_array(Str::lower($password), ['password', 'password123', 'admin12345678'], true)) {
            $this->error('Use at least ' . self::MIN_LENGTH . ' characters, not a common password. Nothing was changed.');

            return self::FAILURE;
        }

        $user ??= new User(['email' => $email, 'name' => $this->option('name') ?: 'Studio Admin']);
        $user->password = Hash::make($password);
        $user->role = 'admin';
        $user->remember_token = Str::random(60);
        $user->save();

        // Database sessions: end every existing sign-in for this account.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $this->info(($user->wasRecentlyCreated ? 'Created admin ' : 'New password set for ') . $email . '. Other sign-ins were ended.');
        if ($this->option('generate')) {
            $this->line("Password (shown once, store it in a password manager): {$password}");
        }

        return self::SUCCESS;
    }
}
