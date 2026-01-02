<?php

namespace Domain\Services;

use Config\AppConfig;

class AccessControlService
{
    private AppConfig $config;

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
    }

    private function userHasGroup(array $requiredGroups): bool
    {
        if (empty($requiredGroups)) {
            return true;
        }

        return (bool) array_intersect($this->effectiveGroups(), $requiredGroups);
    }

    private function effectiveGroups(): array
    {
        if ($this->config->simulateRole === 'admin') {
            return array_unique([...$this->config->adminGroups, ...$this->config->operatorGroups]);
        }
        if ($this->config->simulateRole === 'operator') {
            return $this->config->operatorGroups;
        }
        if ($this->config->simulateRole === 'user') {
            return [];
        }

        return $this->config->currentUserGroups;
    }

    public function canViewActiveList(): bool
    {
        // Tutti i ruoli (user/operator/admin) possono vedere la lista presenti.
        if ($this->config->isLocal()) {
            return true;
        }

        if ($this->config->simulateRole !== null) {
            return true;
        }

        return true;
    }

    public function canViewHistory(): bool
    {
        if ($this->config->isLocal()) {
            return true;
        }

        if ($this->config->simulateRole === 'admin' || $this->config->simulateRole === 'operator') {
            return true;
        }

        return $this->userHasGroup($this->config->operatorGroups) || $this->userHasGroup($this->config->adminGroups);
    }

    public function canViewAuditLogs(): bool
    {
        if ($this->config->isLocal()) {
            return true;
        }

        if ($this->config->simulateRole === 'admin') {
            return true;
        }

        return $this->userHasGroup($this->config->adminGroups);
    }
}
