<?php

return [
    /*
    | How many days of samples and dispatches are copied to each device for
    | offline use (counted back from today). Open work (responses still
    | awaited, orders still being loaded) is always included, whatever its
    | age. Mills, buyers and varieties are always copied in full.
    */
    'history_days' => (int) env('OFFLINE_HISTORY_DAYS', 10),

    /*
    | A device that has not reached the server with a valid session for this
    | many days stops showing offline data until the user signs in again.
    */
    'max_days_without_server' => (int) env('OFFLINE_MAX_DAYS', 30),
];
