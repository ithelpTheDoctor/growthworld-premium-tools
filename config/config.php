<?php
return [
    'app' => [
        'name' => 'GrowthWorld Premium Tools',
        'base_url' => 'https://growthworld.net/xyz',
        'public_email' => 'info@growthworld.net',
        'contact_forward_email' => 'ithelpthedoctor@gmail.com',
        'currency' => 'USD',
        'monthly_price' => 9.99,
        'xor_key' => 'gwt_obf__x9-2026-change-me',
        'csrf_ttl' => 3600,
    ],
    'database' => [
        'host' => 'localhost',
        'name' => 'u335546481_PremiumTools',
        'user' => 'u335546481_premiumtools',
        'pass' => 'Q@:xt32Ub[',
        'charset' => 'utf8mb4',
        'table_prefix' => 'premium_',
    ],
    'paypal' => [
        'environment' => 'live',
        'plan_id_live' => 'P-8GL98556M3929433GNAI4SPQ',
        'plan_id_test' => 'P-00650988UK259205HM7HK4DY',
        'webhook_id_live' => '37Y64950D71078121',
        'webhook_id_test' => '6AN90139W6338880K',
        'client_id_live' => 'AdmiERfejL9fyuwVdZsBr2kO2xhdjjM1BAlMysC1CI-xwQlYbAjhbd9NPm0Jvrt7TfGXEqTwobr2SRnh',
        'secret_live' => 'EC4pNEpvZHKlbbyambyTL_5n70JLONe9tmogVGOJrl0yCH6A9cnatUiLSVexjtIiGGWrkClzCzdczMDs',
        'client_id_test' => 'AaRlZXyyaqIVEwqEUEauoUkdhqlDXqeJZdY2Trb8lmwUq4GTk91uA-SshowufsQTe05jaWqgouSVmLLK',
        'secret_test' => 'ENaCcrV7YvjOF7-Xm5oWeq4ZOr4kJ2vXZzIjSPUE6BY6z3oxXNnkCce2bhmnS2REeJwWDgcGW4NViENZ',
    ],
    'admin' => [
        'username' => 'admin',
        'password' => 'change-this-immediately',
    ],
    'security' => [
        'password_algo' => defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT,
        'session_cookie' => 'growthworld-premium-tools-session',
        'app_token_cookie' => 'growthworld-premium-tools-app-token',
        'cors_allowed_origins' => [
            'https://growthworld.net',
            'https://www.growthworld.net',
            'https://growthworld.net/growthworld-premium-tools-test',
        ],
    ],
];
