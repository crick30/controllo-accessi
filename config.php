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
    'database_path' => __DIR__ . '/storage/database.sqlite',
];
