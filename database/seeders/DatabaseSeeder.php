<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name'     => 'RDU Admin',
            'username' => 'admin',
            'email'    => 'admin@rdu.local',
            'password' => Hash::make('admin'),
        ]);

        $this->call([
            RduIndicatorSeeder::class,
        ]);
    }
}
