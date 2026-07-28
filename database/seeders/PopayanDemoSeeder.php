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
use Illuminate\Support\Str;

class PopayanDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->updateOrCreate(
            ['email' => 'organizador@ligapopayan.com'],
            [
                'name' => 'Organizador Liga Popayán',
                'password' => Hash::make('password123'),
            ]
        );

        if (! $owner->hasRole('organizador')) {
            $owner->assignRole('organizador');
        }

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'liga-municipal-popayan'],
            [
                'name' => 'Liga Municipal de Popayán',
                'is_active' => true,
            ]
        );

        if (! $tenant->users()->where('users.id', $owner->id)->exists()) {
            $tenant->users()->attach($owner->id, ['is_owner' => true]);
        }

        $venues = collect([
            [
                'name' => 'Estadio Ciro López',
                'address' => 'Calle 5 #12-45, barrio Alfonso López',
                'city' => 'Popayán, Cauca',
            ],
            [
                'name' => 'Coliseo Cubierto Municipal',
                'address' => 'Carrera 9 #4-20, centro',
                'city' => 'Popayán, Cauca',
            ],
            [
                'name' => 'Cancha Sintética Universidad del Cauca',
                'address' => 'Calle 5 #4-70, campus Tulcán',
                'city' => 'Popayán, Cauca',
            ],
            [
                'name' => 'Polideportivo La Estancia',
                'address' => 'Vía al norte, sector La Estancia',
                'city' => 'Popayán, Cauca',
            ],
        ])->map(function (array $data) use ($tenant) {
            return Venue::query()->updateOrCreate(
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

        $tournaments = [
            [
                'sport' => 'football',
                'slug' => 'liga-futbol-popayan-2026',
                'name' => 'Liga de Fútbol Popayán 2026',
                'season' => 'Apertura 2026',
                'teams' => [
                    'Atlético Popayán',
                    'Deportivo Cauca',
                    'Real Morinda',
                    'Unicauca FC',
                    'Barrio Bolívar',
                    'Yanaconas FC',
                    'La Pamba United',
                    'Coconuco Sport',
                ],
                'generateFixture' => true,
            ],
            [
                'sport' => 'futsal',
                'slug' => 'copa-futsal-cauca-2026',
                'name' => 'Copa Futsal Cauca 2026',
                'season' => 'Copa 2026',
                'teams' => [
                    'Popayán Futsal',
                    'Cauca FS',
                    'Tulcán Indoor',
                    'Calibío FS',
                    'Timbío Sala',
                    'Silvia Unidos',
                ],
                'generateFixture' => true,
            ],
            [
                'sport' => 'volleyball',
                'slug' => 'torneo-voley-popayan-2026',
                'name' => 'Torneo de Vóley Popayán 2026',
                'season' => 'Copa 2026',
                'teams' => [
                    'Vóley Unicauca',
                    'Águilas del Cauca',
                    'Club Morinda',
                    'Estancia Volley',
                    'Popayán VC',
                    'Norte Alta',
                ],
                'generateFixture' => true,
            ],
        ];

        foreach ($tournaments as $meta) {
            $sport = Sport::query()->where('code', $meta['sport'])->firstOrFail();

            $tournament = Tournament::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'slug' => $meta['slug'],
                ],
                [
                    'sport_id' => $sport->id,
                    'name' => $meta['name'],
                    'format' => 'league',
                    'status' => 'active',
                    'season_label' => $meta['season'],
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

            foreach ($meta['teams'] as $index => $teamName) {
                $short = strtoupper(Str::substr(preg_replace('/\s+/', '', Str::ascii($teamName)) ?: 'EQP', 0, 3));

                $team = Team::query()->updateOrCreate(
                    [
                        'tournament_id' => $tournament->id,
                        'name' => $teamName,
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'short_name' => $short,
                    ]
                );

                $playerCount = $meta['sport'] === 'volleyball' ? 10 : 12;
                for ($n = 1; $n <= $playerCount; $n++) {
                    Player::query()->updateOrCreate(
                        [
                            'team_id' => $team->id,
                            'jersey_number' => $n,
                        ],
                        [
                            'tenant_id' => $tenant->id,
                            'first_name' => 'Jugador',
                            'last_name' => $short.' '.$n,
                            'document_id' => sprintf('POP%s%02d%02d', $tournament->id, $index + 1, $n),
                            'status' => 'enabled',
                        ]
                    );
                }
            }

            if ($meta['generateFixture']) {
                app(FixtureGenerator::class)->generate($tournament->fresh(), [
                    'startDate' => now()->addDays(7)->toDateString(),
                    'kickoffTime' => $meta['sport'] === 'football' ? '15:00' : '19:00',
                    'daysBetweenMatchdays' => 7,
                    'legs' => 1,
                    'venueIds' => $venues->pluck('id')->all(),
                    'clearExisting' => true,
                    'matchIntervalMinutes' => $meta['sport'] === 'football' ? 120 : 75,
                ]);
            }
        }

        $this->command?->info('Liga Municipal de Popayán lista (organizador@ligapopayan.com / password123)');
        $this->command?->info('Torneos: Fútbol, Futsal y Vóley · sedes en Popayán, Cauca');
    }
}
