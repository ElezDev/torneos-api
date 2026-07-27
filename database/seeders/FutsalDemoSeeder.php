<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\Sport;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use App\Services\FixtureGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FutsalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $sport = Sport::query()->where('code', 'futsal')->firstOrFail();

        $user = User::query()->firstOrCreate(
            ['email' => 'org@torneos.test'],
            [
                'name' => 'Organizador Demo',
                'password' => Hash::make('password123'),
            ]
        );

        if (! $user->hasRole('organizador')) {
            $user->assignRole('organizador');
        }

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'club-demo'],
            [
                'name' => 'Club Demo',
                'is_active' => true,
            ]
        );

        if (! $tenant->users()->where('users.id', $user->id)->exists()) {
            $tenant->users()->attach($user->id, ['is_owner' => true]);
        }

        $venues = collect([
            ['name' => 'Polideportivo Central', 'city' => 'Asunción', 'address' => 'Av. Principal 100'],
            ['name' => 'Cancha Norte', 'city' => 'Asunción', 'address' => 'Calle 12'],
        ])->map(function (array $data) use ($tenant) {
            return Venue::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                ],
                [
                    'city' => $data['city'],
                    'address' => $data['address'],
                ]
            );
        });

        $tournament = Tournament::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'slug' => 'copa-futsal-apertura',
            ],
            [
                'sport_id' => $sport->id,
                'name' => 'Copa Futsal Apertura',
                'format' => 'league',
                'status' => 'registration',
                'season_label' => 'Apertura 2026',
                'starts_on' => now()->addDays(7)->toDateString(),
                'is_public' => true,
                'points_config' => ['win' => 3, 'draw' => 1, 'loss' => 0],
                'sanction_rules' => [
                    'yellowsForSuspension' => 2,
                    'redDirectSuspension' => true,
                    'suspensionMatches' => 1,
                ],
                'tiebreaker_rules' => ['points', 'goalDifference', 'goalsFor', 'headToHead'],
                'format_config' => ['legs' => 1],
            ]
        );

        $teamNames = [
            'Tigres Futsal',
            'Leones FS',
            'Halcones',
            'Náutico',
            'Barrio Sur',
            'Atenas',
        ];

        $teams = collect();
        foreach ($teamNames as $index => $name) {
            $team = Team::query()->updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'name' => $name,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'short_name' => strtoupper(substr(preg_replace('/\s+/', '', $name), 0, 3)),
                ]
            );
            $teams->push($team);

            for ($n = 1; $n <= 8; $n++) {
                Player::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'jersey_number' => $n,
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'first_name' => 'Jugador',
                        'last_name' => $team->short_name.' '.$n,
                        'document_id' => sprintf('CC%s%02d%02d', $team->id, $index + 1, $n),
                        'status' => 'enabled',
                    ]
                );
            }
        }

        app(FixtureGenerator::class)->generate($tournament->fresh(), [
            'startDate' => now()->addDays(7)->toDateString(),
            'kickoffTime' => '20:00',
            'daysBetweenMatchdays' => 7,
            'legs' => 1,
            'venueIds' => $venues->pluck('id')->all(),
            'clearExisting' => true,
            'matchIntervalMinutes' => 75,
        ]);

        $this->command?->info('Demo futsal listo: Copa Futsal Apertura (Club Demo / org@torneos.test)');
    }
}
