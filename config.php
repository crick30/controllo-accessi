<?php
return [
    // Set environment to 'local' to bypass AD group enforcement.
    'environment' => getenv('APP_ENV') ?: 'local',
    // Placeholder lists for AD groups (used when environment is not local).
    'operator_groups' => [
        'CN=AccessOperators,OU=Security,DC=example,DC=com',
    ],
    'admin_groups' => [
        'CN=AccessAdmins,OU=Security,DC=example,DC=com',
    ],
    // Mocked user group membership for demonstration; replace with real group lookup.
    'current_user_groups' => explode(';', getenv('USER_GROUPS') ?: ''),
    // Testing only: simulate role => 'user' | 'operator' | 'admin' (adds matching groups).
    'simulate_role' => getenv('APP_SIMULATE_ROLE') ?: null,
    'database_path' => __DIR__ . '/storage/database.sqlite',
    // UI theme: 'dark', 'light', or 'auto' (time-based using light_* hours).
    'theme_mode' => getenv('APP_THEME') ?: 'auto',
    'light_start_hour' => getenv('APP_LIGHT_START') ?: 7,
    'light_end_hour' => getenv('APP_LIGHT_END') ?: 19,
    // User performing actions (for audit logging, e.g., SSO username).
    'app_user' => getenv('APP_USER') ?: (getenv('USER') ?: 'system'),
    // Log configuration: file path (outside project root by default) and verbosity (error, warning, info, debug).
    'log_path' => getenv('APP_LOG_PATH') ?: dirname(__DIR__) . '/controllo-accessi-logs/app.log',
    'log_level' => getenv('APP_LOG_LEVEL') ?: 'info',
];
