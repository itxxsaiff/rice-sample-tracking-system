<?php

/*
| Business vocabulary for the rice trade. Edit these lists to change the
| options shown in forms; stored values are plain strings so history stays
| readable even if an option is later removed from the list.
*/

return [

    // Every sample is recorded as this amount (the form no longer asks for it).
    'sample_default' => ['quantity' => 2, 'unit' => 'kg'],

    // Units for the small physical sample collected at the mill (older samples may use any of these).
    'sample_units' => [
        'g' => 'Grams (g)',
        'kg' => 'Kilograms (kg)',
        'packet' => 'Packets',
        'pouch' => 'Pouches',
    ],

    // Units for confirmed purchase and actual loaded quantities (1 maund = 40 kg).
    'quantity_units' => [
        'maund' => 'Maund',
        'kg' => 'Kg',
        'ton' => 'Ton',
        'bag' => 'Bags',
    ],

    // Kilograms per unit, to work out the number of bags (1000 maund in 25 kg bags = 1600 bags).
    'unit_kg' => [
        'maund' => 40,
        'kg' => 1,
        'ton' => 1000,
    ],

    // What the agreed rate is quoted against.
    'rate_units' => [
        'per_maund' => '/ Maund',
        'per_kg' => '/ Kg',
        'per_ton' => '/ Ton',
        'per_bag' => '/ Bag',
    ],

    // Confirmed orders are always in maunds at a rate per maund.
    'order_unit' => 'maund',
    'order_rate_unit' => 'per_maund',

    // Packing = bag size in kg. Older orders keep whatever text they were saved with.
    'packing_types' => [
        '5 kg' => '5 kg', '10 kg' => '10 kg', '15 kg' => '15 kg', '20 kg' => '20 kg',
        '25 kg' => '25 kg', '40 kg' => '40 kg', '50 kg' => '50 kg', '100 kg' => '100 kg',
    ],

    // Suggestions only (rice-belt and trading cities); any city can be typed.
    'cities' => [
        'Lahore', 'Gujranwala', 'Sheikhupura', 'Hafizabad', 'Sialkot', 'Narowal', 'Kamoke', 'Muridke',
        'Mandi Bahauddin', 'Gujrat', 'Faisalabad', 'Okara', 'Kasur', 'Nankana Sahib', 'Pakpattan',
        'Sahiwal', 'Multan', 'Bahawalpur', 'Karachi', 'Hyderabad', 'Larkana', 'Sukkur', 'Shikarpur',
        'Jacobabad', 'Dera Murad Jamali', 'Islamabad', 'Rawalpindi', 'Quetta', 'Peshawar',
    ],

    // First number of each series (numbers already used are never repeated).
    'number_start' => [
        'sample' => (int) env('SAMPLE_NUMBER_START', 255),
    ],

    // Reference number prefixes.
    'prefixes' => [
        'sample' => 'SMP',
        'dispatch' => 'DSP',
        'loading' => 'LD',
    ],

    // Upload limits (kilobytes). Photos are compressed in the browser first.
    'max_photo_kb' => 12288,
    'max_photos_per_upload' => 10,
];
