<?php

namespace Config;

class AppConfig
{
    public string $environment;
    public array $operatorGroups;
    public array $adminGroups;
    public array $currentUserGroups;
    public string $databasePath;
    public string $themeMode;
    public int $lightStartHour;
    public int $lightEndHour;
    public string $appUser;

    public function __construct(array $data)
    {
        $this->environment = $data['environment'] ?? 'local';
        $this->operatorGroups = $data['operator_groups'] ?? [];
        $this->adminGroups = $data['admin_groups'] ?? [];
        $this->currentUserGroups = $data['current_user_groups'] ?? [];
        $this->databasePath = $data['database_path'] ?? __DIR__ . '/storage/database.sqlite';
        $this->themeMode = $data['theme_mode'] ?? 'auto';
        $this->lightStartHour = (int) ($data['light_start_hour'] ?? 7);
        $this->lightEndHour = (int) ($data['light_end_hour'] ?? 19);
        $this->appUser = $data['app_user'] ?? 'system';
    }

    public function isLocal(): bool
    {
        return $this->environment === 'local';
    }
}
