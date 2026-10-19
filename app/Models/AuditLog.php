<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One audited action. Append-only: the model refuses updates and deletes, so
 * the trail can't be rewritten through the app (App\Support\Audit writes it).
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit log entries are append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit log entries are append-only.'));
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
