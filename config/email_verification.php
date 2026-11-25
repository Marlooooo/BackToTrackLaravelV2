<?php

return [

    // Turn the check on with EMAIL_VERIFICATION=true in .env
    'enabled' => (bool) env('EMAIL_VERIFICATION', false),

    // How long a code stays valid
    'code_ttl_minutes' => 10,

    // Wrong guesses allowed before the code is thrown away
    'max_attempts' => 5,

    // Minimum wait before another code can be sent
    'resend_seconds' => 60,

];