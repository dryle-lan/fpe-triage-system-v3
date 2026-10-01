<?php

return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'fpe_triage_system',
        'user'    => 'root',
        'pass'    => '',          // XAMPP's default MySQL root password is blank
        'charset' => 'utf8mb4',
    ],

    'gemini' => [
        'api_key' => 'AQ.Ab8RN6It2XrfkJE-XV3t7mmytHJeN0exNH4vkDdbNqEqJ9DwAw', // get one at https://aistudio.google.com/apikey
        'model'   => 'gemini-3.5-flash-lite', // free-tier model names change over time —
                                          // check https://ai.google.dev/gemini-api/docs/pricing
                                          // if generation starts failing with a 404
    ],
];
