<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One register's day (or shift) at a location, from float to count (design §15.2). */
class TillSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'opening_float' => 'integer', 'expected_cash' => 'integer', 'counted_cash' => 'integer', 'variance' => 'integer',
        'opened_at' => 'datetime', 'closed_at' => 'datetime', 'open_marker' => 'boolean',
    ];

    public function movements()
    {
        return $this->hasMany(CashMovement::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
