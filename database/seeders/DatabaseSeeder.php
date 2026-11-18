<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds reference data only. Create the first administrator with:
     *   php artisan app:create-admin
     */
    public function run(): void
    {
        $this->call(RiceVarietySeeder::class);
    }
}
