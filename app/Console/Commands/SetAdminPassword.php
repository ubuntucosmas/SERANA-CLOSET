<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Create a staff account (owner by default), or give an existing one a new password, without
 * seeders or tinker. The password is typed hidden (never in shell history)
 * or generated and shown once. Signing out everywhere else is part of the
 * reset: an old session must not outlive the old password.
 */
class SetAdminPassword extends Command
{
    protected $signature = 'serana:admin {email : The admin\'s sign-in email}
        {--name= : Display name when creating the account}
        {--role= : owner (default for new accounts), manager or cashier}
        {--generate : Generate a strong password and show it once}';

    protected $description = 'Create a staff account (owner, manager, cashier) or reset a staff password';

    public const MIN_LENGTH = 12;

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        $role = $this->option('role');
        if ($role !== null && ! in_array($role, \App\Support\Role::STAFF, true)) {
            $this->error('Role must be owner, manager or cashier.');

            return self::FAILURE;
        }

        if ($user && ! $user->isStaff()) {
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
        // A reset keeps the account's role unless --role says otherwise.
        $user->role = $role ?? ($user->exists ? $user->role : \App\Support\Role::OWNER);
        $user->remember_token = Str::random(60);
        $user->save();
        \App\Support\Audit::record('staff.saved', $user, [], ['email' => $email, 'role' => $user->role, 'password_changed' => true]);

        // Database sessions: end every existing sign-in for this account.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $this->info(($user->wasRecentlyCreated ? "Created {$user->role} " : 'New password set for ') . $email . '. Other sign-ins were ended.');
        if ($this->option('generate')) {
            $this->line("Password (shown once, store it in a password manager): {$password}");
        }

        return self::SUCCESS;
    }
}
