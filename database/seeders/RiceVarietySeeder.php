<?php

namespace Database\Seeders;

use App\Models\RiceVariety;
use Illuminate\Database\Seeder;

class RiceVarietySeeder extends Seeder
{
    /** Common Pakistani varieties. Safe to run repeatedly. */
    public function run(): void
    {
        $varieties = [
            '1121' => '1121 Basmati (extra long grain)',
            '1509' => '1509 Basmati',
            'Super Basmati' => 'Traditional Super Basmati',
            'Super Kernel' => 'Super Kernel Basmati',
            '386' => 'PK-386 non-basmati long grain',
            'PK-198' => 'PK-198 Basmati',
            'IRRI-6' => 'IRRI-6 long grain white',
            'IRRI-9' => 'IRRI-9',
            'KSK-133' => 'KSK-133',
            'C-9' => 'C-9 sella / steam',
        ];

        foreach ($varieties as $name => $description) {
            RiceVariety::firstOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
