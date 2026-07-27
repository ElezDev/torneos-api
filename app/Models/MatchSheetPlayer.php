<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchSheetPlayer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'match_sheet_id',
        'player_id',
        'jersey_number',
        'is_starter',
    ];

    protected function casts(): array
    {
        return [
            'jersey_number' => 'integer',
            'is_starter' => 'boolean',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(MatchSheet::class, 'match_sheet_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
