<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'user_id',
    ];

    protected $hidden = [
        'password',
    ];

    public function badge(): HasOne
    {
        return $this->hasOne(CustomerBadge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
