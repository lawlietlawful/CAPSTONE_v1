<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | The Python FastAPI risk-assessment engine (ml_engine/main.py).
    |
    | The URL was hardcoded in three places. `key`, when set, is sent as an
    | X-API-Key header and enforced by the engine — necessary if you ever bind it
    | to 0.0.0.0 (e.g. to reach it from a phone), since /predict and /retrain are
    | otherwise open to anyone on the network. Leave blank for a localhost-only
    | development setup.
    */
    'ml' => [
        'url' => rtrim(env('ML_ENGINE_URL', 'http://127.0.0.1:8001'), '/'),
        'key' => env('ML_ENGINE_KEY'),

        // Guards the admin "Retrain" and CSV-upload actions. OFF by default:
        // the DB retrain trains on the system's own output (priority, which the
        // model set) — a feedback loop — and the CSV path replaces the validated
        // 3,000-row model with an unvalidated upload. Either silently degrades a
        // known-good model. Retraining is an offline, reviewed process
        // (ml_engine/generate_dataset.py + train_model.py). Flip to true only if
        // you deliberately want in-app retraining back.
        'retrain_enabled' => env('ML_RETRAIN_ENABLED', false),
    ],

];
