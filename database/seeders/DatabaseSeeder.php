<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed users
        User::factory(100)->create();

        User::factory()->create([
            'username' => 'itoktoni',
            'name' => 'itok toni laksono',
            'email' => 'itok.toni@gmail.com',
            'password' => bcrypt(env('APP_PASSWORD', 'password')),
        ]);

        // Seed crypto symbols from Binance
        $this->command->info('🌱 Seeding crypto symbols from Binance...');
        $this->call(CryptoSymbolSeeder::class);
    }
}
