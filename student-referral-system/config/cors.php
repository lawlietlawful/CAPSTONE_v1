<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| This file did not exist, so Laravel fell back to `allowed_origins => ['*']`
| and every response carried `Access-Control-Allow-Origin: *`. Any web page on
| any domain could call /api/* from a victim's browser.
|
| Note this only affects BROWSER clients. The Flutter app on Android/iOS sends
| no Origin header, so it is unaffected either way. The browser clients that do
| matter are `flutter run -d chrome` (StudentPortal / TeacherPortal on web),
| which binds a RANDOM localhost port each run — hence the pattern below rather
| than a fixed origin list.
|
*/

$explicitOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

// Any localhost port, for local development only. Never in production: a
// malicious page served from 127.0.0.1 is not a threat model we need, but an
// unnecessary allowance in a deployed app is.
$localhostPattern = '#^http://(localhost|127\.0\.0\.1)(:\d+)?$#';

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Set CORS_ALLOWED_ORIGINS=https://portal.mu.edu.ph in production .env
    'allowed_origins' => $explicitOrigins,

    'allowed_origins_patterns' => env('APP_ENV') === 'production'
        ? []
        : [$localhostPattern],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 600,

    // The API authenticates with Bearer tokens, not cookies. Keeping this false
    // means no browser will ever attach session cookies cross-origin.
    'supports_credentials' => false,

];
