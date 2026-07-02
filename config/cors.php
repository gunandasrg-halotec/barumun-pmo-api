<?php

return [
    // Applies CORS settings to all API endpoints
    'paths' => ['api/*'],

    // Allows all HTTP Methods (POST, GET, OPTIONS, PUT, DELETE)
    'allowed_methods' => ['*'],

    // 🚀 ALLOWS ALL ORIGINS (everywhere) safely since credentials are disabled
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    // Allows all incoming headers (Content-Type, Authorization, etc.)
    'allowed_headers' => ['*'],

    // Explicitly expose the Authorization header if your JWT package 
    // uses it to send refreshed tokens back to the frontend
    'exposed_headers' => ['Authorization'],

    'max_age' => 86400, // Cache preflight response for 24 hours to speed up requests

    // 🚀 DISABLED because pure JWT authentication does not use cookies
    'supports_credentials' => false,
];
