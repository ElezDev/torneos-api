<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public const GUARD = 'api';

    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'tenants.view',
        'tenants.create',
        'tenants.update',
        'sports.view',
        'tournaments.view',
        'tournaments.create',
        'tournaments.update',
        'tournaments.delete',
        'venues.view',
        'venues.create',
        'venues.update',
        'venues.delete',
        'teams.view',
        'teams.create',
        'teams.update',
        'teams.delete',
        'players.view',
        'players.create',
        'players.update',
        'players.delete',
        'matches.view',
        'matches.create',
        'matches.update',
        'matches.delete',
        'standings.view',
        'match-sheets.manage',
        'roles.manage',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, self::GUARD);
        }

        $superAdmin = Role::findOrCreate('super-admin', self::GUARD);
        $organizador = Role::findOrCreate('organizador', self::GUARD);
        $arbitro = Role::findOrCreate('arbitro', self::GUARD);
        $delegado = Role::findOrCreate('delegado', self::GUARD);
        $espectador = Role::findOrCreate('espectador', self::GUARD);

        $superAdmin->syncPermissions(Permission::where('guard_name', self::GUARD)->get());

        $organizador->syncPermissions([
            'tenants.view',
            'tenants.create',
            'tenants.update',
            'sports.view',
            'tournaments.view',
            'tournaments.create',
            'tournaments.update',
            'tournaments.delete',
            'venues.view',
            'venues.create',
            'venues.update',
            'venues.delete',
            'teams.view',
            'teams.create',
            'teams.update',
            'teams.delete',
            'players.view',
            'players.create',
            'players.update',
            'players.delete',
            'matches.view',
            'matches.create',
            'matches.update',
            'matches.delete',
            'standings.view',
            'match-sheets.manage',
        ]);

        $arbitro->syncPermissions([
            'sports.view',
            'tournaments.view',
            'venues.view',
            'teams.view',
            'players.view',
            'matches.view',
            'matches.update',
            'standings.view',
            'match-sheets.manage',
        ]);

        $delegado->syncPermissions([
            'sports.view',
            'tournaments.view',
            'tournaments.update',
            'venues.view',
            'venues.create',
            'venues.update',
            'teams.view',
            'teams.create',
            'teams.update',
            'teams.delete',
            'players.view',
            'players.create',
            'players.update',
            'players.delete',
            'matches.view',
            'matches.create',
            'matches.update',
            'matches.delete',
            'standings.view',
            'match-sheets.manage',
        ]);

        $espectador->syncPermissions([
            'sports.view',
            'tournaments.view',
            'venues.view',
            'teams.view',
            'players.view',
            'matches.view',
            'standings.view',
        ]);
    }
}
