<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameMatch extends Model
{
    use BelongsToTenant;

    protected $table = 'matches';

    protected $fillable = [
        'tenant_id',
        'tournament_id',
        'tournament_group_id',
        'venue_id',
        'home_team_id',
        'away_team_id',
        'home_from_match_id',
        'away_from_match_id',
        'home_from_result',
        'away_from_result',
        'matchday',
        'round_name',
        'stage',
        'bracket_slot',
        'bracket_code',
        'scheduled_at',
        'status',
        'home_score',
        'away_score',
        'winner_team_id',
        'notes',
        'referee_name',
        'banner_path',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'matchday' => 'integer',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'bracket_slot' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'tournament_group_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function homeFromMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'home_from_match_id');
    }

    public function awayFromMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'away_from_match_id');
    }

    public function sheets(): HasMany
    {
        return $this->hasMany(MatchSheet::class, 'match_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'match_id');
    }
}
