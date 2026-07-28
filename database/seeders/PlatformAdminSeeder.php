<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'edwinsuperadmin@matchday.com'],
            [
                'name' => 'Edwin Super Admin',
                'password' => Hash::make('Matchday2026!'),
            ]
        );

        if (! $admin->hasRole('super-admin')) {
            $admin->assignRole('super-admin');
        }

        // Limpia el admin demo anterior si existía.
        User::query()
            ->where('email', 'admin@torneos.test')
            ->where('id', '!=', $admin->id)
            ->delete();
    }
}
