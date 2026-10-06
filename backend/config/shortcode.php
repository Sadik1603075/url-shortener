<?php

return [
    /*
     | Secret used to key the reversible obfuscation (Feistel) that scrambles the
     | monotonic counter before encoding (ADR-0001). Leaking it makes codes
     | enumerable, so treat it as a secret. Falls back to APP_KEY if unset.
     */
    'key' => env('SHORTCODE_KEY', ''),

    /*
     | Minimum rendered code length; shorter codes are left-padded so early ids
     | aren't 1–2 characters.
     */
    'min_length' => (int) env('SHORTCODE_MIN_LENGTH', 7),

    /*
     | Public base URL that short links are built from (scheme + host [+ port]),
     | WITHOUT a trailing slash. This is the *redirect* host, which is deliberately
     | distinct from the admin/API host (the k8s ingress routes them separately), so
     | it must be configured explicitly rather than derived from the request host.
     | Falls back to APP_URL. Example (local minikube): http://linkforge.local:8090
     */
    'url_base' => rtrim(env('SHORT_URL_BASE', env('APP_URL', 'http://localhost')), '/'),
];
