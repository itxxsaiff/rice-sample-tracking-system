<?php

/*
| Business vocabulary for the rice trade. Edit these lists to change the
| options shown in forms; stored values are plain strings so history stays
| readable even if an option is later removed from the list.
*/

return [

    // Units for the small physical sample collected at the mill.
    'sample_units' => [
        'g' => 'Grams (g)',
        'kg' => 'Kilograms (kg)',
        'packet' => 'Packets',
        'pouch' => 'Pouches',
    ],

    // Units for confirmed purchase and actual loaded quantities.
    'quantity_units' => [
        'maund' => 'Maund (40 kg)',
        'kg' => 'Kilograms (kg)',
        'ton' => 'Metric tons',
        'bag' => 'Bags',
    ],

    // What the agreed rate is quoted against.
    'rate_units' => [
        'per_maund' => 'per Maund (40 kg)',
        'per_kg' => 'per kg',
        'per_ton' => 'per Ton',
        'per_bag' => 'per Bag',
    ],

    'packing_types' => [
        'PP bag' => 'PP bag',
        'Jute bag' => 'Jute bag',
        'Non-woven bag' => 'Non-woven bag',
        'BOPP bag' => 'BOPP bag',
        'Cotton bag' => 'Cotton bag',
        'Bulk / loose' => 'Bulk / loose',
        'Buyer packing' => "Buyer's own packing",
        'Other' => 'Other',
    ],

    // Suggestions only (rice-belt and trading cities); any city can be typed.
    'cities' => [
        'Lahore', 'Gujranwala', 'Sheikhupura', 'Hafizabad', 'Sialkot', 'Narowal', 'Kamoke', 'Muridke',
        'Mandi Bahauddin', 'Gujrat', 'Faisalabad', 'Okara', 'Kasur', 'Nankana Sahib', 'Pakpattan',
        'Sahiwal', 'Multan', 'Bahawalpur', 'Karachi', 'Hyderabad', 'Larkana', 'Sukkur', 'Shikarpur',
        'Jacobabad', 'Dera Murad Jamali', 'Islamabad', 'Rawalpindi', 'Quetta', 'Peshawar',
    ],

    // Reference number prefixes.
    'prefixes' => [
        'sample' => 'SMP',
        'bundle' => 'BND',
        'dispatch' => 'DSP',
        'loading' => 'LD',
    ],

    // Upload limits (kilobytes). Photos are compressed in the browser first.
    'max_photo_kb' => 12288,
    'max_photos_per_upload' => 10,
];
