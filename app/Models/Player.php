<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'team_id',
        'first_name',
        'last_name',
        'jersey_number',
        'document_id',
        'birth_date',
        'status',
        'yellow_cards_count',
        'red_cards_count',
        'suspension_matches_left',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'jersey_number' => 'integer',
            'yellow_cards_count' => 'integer',
            'red_cards_count' => 'integer',
            'suspension_matches_left' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(PlayerSanction::class);
    }
}
