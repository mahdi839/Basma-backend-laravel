<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PathaoToken extends Model
{
     protected $fillable = [
        'token_type',
        'access_token',
        'refresh_token',
        'expires_in',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public static function getValidToken(): ?string
    {
        $token = self::latest()->first();

        if (!$token || $token->isExpired()) {
            return null;
        }

        return $token->access_token;
    }
}
