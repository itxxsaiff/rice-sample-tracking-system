<?php

return [
    /*
    | How much history is copied to each device for offline use. Open work
    | (pending responses, orders still being loaded) is always included,
    | regardless of age.
    */
    'history_days' => (int) env('OFFLINE_HISTORY_DAYS', 365),

    /*
    | A device that has not reached the server with a valid session for this
    | many days stops showing offline data until the user signs in again.
    */
    'max_days_without_server' => (int) env('OFFLINE_MAX_DAYS', 30),
];
