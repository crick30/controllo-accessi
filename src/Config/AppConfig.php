<?php

namespace Config;

class AppConfig
{
    public string $environment;
    public array $operatorGroups;
    public array $adminGroups;
    public array $currentUserGroups;
    public ?string $simulateRole;
    public string $databasePath;
    public string $themeMode;
    public int $lightStartHour;
    public int $lightEndHour;
    public string $appUser;
    public string $logPath;
    public string $logLevel;

    public function __construct(array $data)
    {
        $this->environment = $data['environment'] ?? 'local';
        $this->operatorGroups = $data['operator_groups'] ?? [];
        $this->adminGroups = $data['admin_groups'] ?? [];
        $this->currentUserGroups = $data['current_user_groups'] ?? [];
        $this->simulateRole = $data['simulate_role'] ?? null;
        $this->databasePath = $data['database_path'] ?? __DIR__ . '/storage/database.sqlite';
        $this->themeMode = $data['theme_mode'] ?? 'auto';
        $this->lightStartHour = (int) ($data['light_start_hour'] ?? 7);
        $this->lightEndHour = (int) ($data['light_end_hour'] ?? 19);
        $this->appUser = $data['app_user'] ?? 'system';
        $this->logPath = $data['log_path'] ?? dirname(__DIR__, 2) . '/../controllo-accessi-logs/app.log';
        $this->logLevel = $data['log_level'] ?? 'info';
    }

    public function isLocal(): bool
    {
        return $this->environment === 'local';
    }
}
