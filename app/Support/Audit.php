<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Writes the append-only audit trail (design N3). */
final class Audit
{
    public static function record(string $action, ?Model $subject, array $before = [], array $after = [], ?string $reason = null, ?Actor $actor = null): AuditLog
    {
        $actor ??= Actor::current();

        return AuditLog::create([
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'reason' => $reason,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * The fields of $model that $changes would actually change, as [before, after].
     * Numbers compare by value ("5000.00" equals 5000), so a save that changes
     * nothing records nothing.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diff(Model $model, array $changes): array
    {
        $before = [];
        $after = [];
        foreach ($changes as $key => $new) {
            $old = $model->getAttribute($key);
            $same = is_numeric($old) && is_numeric($new)
                ? (float) $old === (float) $new
                : (is_bool($old) || is_bool($new) ? (bool) $old === (bool) $new : (string) $old === (string) $new && ($old === null) === ($new === null));
            if (! $same) {
                $before[$key] = $old;
                $after[$key] = $new;
            }
        }

        return [$before, $after];
    }

    /** Record an update only when it changes something. */
    public static function changes(string $action, Model $model, array $changes, ?string $reason = null): void
    {
        [$before, $after] = self::diff($model, $changes);
        if ($after !== []) {
            self::record($action, $model, $before, $after, $reason);
        }
    }
}
