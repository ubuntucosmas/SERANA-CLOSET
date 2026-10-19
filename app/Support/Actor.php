<?php

namespace App\Support;

use App\Models\User;

/** Who caused a money, stock or audited action: a user, the system, or Safaricom. */
final class Actor
{
    public const USER = 'user';
    public const SYSTEM = 'system';
    public const SAFARICOM = 'safaricom';

    private function __construct(public readonly string $type, public readonly ?int $id = null)
    {
    }

    public static function user(User $user): self
    {
        return new self(self::USER, $user->id);
    }

    public static function system(): self
    {
        return new self(self::SYSTEM);
    }

    public static function safaricom(): self
    {
        return new self(self::SAFARICOM);
    }

    /** The signed-in user for web requests; the system for CLI, jobs and the scheduler. */
    public static function current(): self
    {
        $user = auth()->user();

        return $user instanceof User ? self::user($user) : self::system();
    }

    public function isHuman(): bool
    {
        return $this->type === self::USER;
    }
}
