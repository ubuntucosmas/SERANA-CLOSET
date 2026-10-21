<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A place that holds stock (design §13.1). One shop today; the model takes
 * more. Online orders are filled from locations that fulfil online, in sort
 * order; pickup is offered at locations that accept it.
 */
class Location extends Model
{
    protected $guarded = [];

    protected $casts = [
        'fulfils_online' => 'boolean',
        'accepts_pickup' => 'boolean',
        'active' => 'boolean',
        'sort' => 'integer',
    ];

    public function scopePickupAvailable($query)
    {
        return $query->where('active', true)->where('accepts_pickup', true)->whereNotNull('address')->whereRaw("LENGTH(TRIM(address)) > 0");
    }

    public function scopeOnline($query)
    {
        return $query->where('active', true)->where('fulfils_online', true)->orderBy('sort')->orderBy('id');
    }
}
