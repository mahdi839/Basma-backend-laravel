<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBadge extends Model
{
    public const TITLES = [
        'black_list_customer' => 'Black List Customer',
        'very_annoying_customer' => 'Very Annoying Customer',
        'tester' => 'Tester',
        'owner' => 'Owner',
        'silver_customer' => 'Silver Customer',
        'golden_customer' => 'Golden Customer',
    ];

    protected $fillable = [
        'customer_id',
        'badge_title',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function label(?string $title): ?string
    {
        if (!$title) {
            return null;
        }

        return self::TITLES[$title] ?? $title;
    }

    public static function payload(?string $title): ?array
    {
        if (!$title) {
            return null;
        }

        return [
            'title' => $title,
            'label' => self::label($title),
        ];
    }
}
