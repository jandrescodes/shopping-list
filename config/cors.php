<?php

// The frontend is served from the same origin as the API, so no third-party
// origin needs to read it: an empty allowlist sends no CORS headers instead
// of the framework's wildcard default.
return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
