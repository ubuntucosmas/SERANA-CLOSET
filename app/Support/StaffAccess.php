<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Revoke account and shared-tablet access independently of the session driver. */
final class StaffAccess
{
    public const SESSION_VERSION = 'auth.session_version';

    public static function updateDetails(User $user, array $details, string $reason): void
    {
        DB::transaction(function () use ($user, $details, $reason) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $details = array_intersect_key($details, array_flip(['name', 'email']));
            Audit::changes('staff.details_changed', $locked, $details, $reason);
            $emailChanged = isset($details['email']) && $locked->email !== $details['email'];
            if ($emailChanged) Password::broker()->deleteToken($locked);
            $locked->forceFill($details + ($emailChanged ? ['email_verified_at' => null] : []))->save();
            if ($emailChanged) {
                Password::broker()->deleteToken($locked);
                self::revoke($locked);
            }
            $user->refresh();
        });
    }

    public static function revoke(User $user): void
    {
        DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'session_version' => (int) $locked->session_version + 1,
                'remember_token' => Str::random(60),
            ])->save();
            $user->refresh();
        });
    }
}
