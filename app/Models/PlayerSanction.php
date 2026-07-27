<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerSanction extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'player_id',
        'tournament_id',
        'source_match_id',
        'reason',
        'matches_banned',
        'matches_served',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'matches_banned' => 'integer',
            'matches_served' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function sourceMatch(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'source_match_id');
    }
}
