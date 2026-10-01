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
];
