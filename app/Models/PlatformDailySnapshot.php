<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformDailySnapshot extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'snapshot_date',
        'total_companies',
        'active_companies',
        'total_users',
        'active_subscriptions',
        'mrr',
        'subscription_breakdown',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'total_companies' => 'integer',
            'active_companies' => 'integer',
            'total_users' => 'integer',
            'active_subscriptions' => 'integer',
            'mrr' => 'float',
            'subscription_breakdown' => 'array',
        ];
    }
}
