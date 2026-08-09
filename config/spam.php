<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spam protection
    |--------------------------------------------------------------------------
    |
    | Lightweight, dependency-free protection for public fill pages. Three
    | independent checks run on every submission:
    |
    |   1. Honeypot  - a hidden field that real humans never see. Bots that
    |                  fill every field get trapped and the submission is
    |                  rejected.
    |   2. Time trap - a human cannot realistically read and fill a form in
    |                  under `min_fill_seconds`. Instantly-submitted forms are
    |                  rejected as bots.
    |   3. Velocity  - IP submissions are counted in a sliding window. Repeat
    |                  offenders over `max_attempts` are still accepted but
    |                  flagged as spam so the owner can review them.
    |
    | Set SPAM_ENABLED=false to disable all checks.
    |
    */

    'enabled' => (bool) env('SPAM_ENABLED', true),

    'honeypot_field' => env('SPAM_HONEYPOT_FIELD', 'website'),

    'min_fill_seconds' => (int) env('SPAM_MIN_FILL_SECONDS', 3),

    'velocity' => [
        'enabled' => (bool) env('SPAM_VELOCITY_ENABLED', true),
        'max_attempts' => (int) env('SPAM_MAX_ATTEMPTS', 5),
        'window_seconds' => (int) env('SPAM_WINDOW_SECONDS', 600),
    ],

];
