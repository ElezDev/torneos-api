<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'sport_id',
        'name',
        'slug',
        'format',
        'status',
        'season_label',
        'starts_on',
        'ends_on',
        'is_public',
        'banner_path',
        'points_config',
        'sanction_rules',
        'tiebreaker_rules',
        'format_config',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_public' => 'boolean',
            'points_config' => 'array',
            'sanction_rules' => 'array',
            'tiebreaker_rules' => 'array',
            'format_config' => 'array',
        ];
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TournamentGroup::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(TournamentPost::class);
    }
}
