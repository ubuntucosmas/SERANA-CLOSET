<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOperation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['completed_at' => 'datetime'];
}
