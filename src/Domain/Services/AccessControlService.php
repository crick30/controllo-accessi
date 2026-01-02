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

        return (bool) array_intersect($this->config->currentUserGroups, $requiredGroups);
    }

    public function canViewActiveList(): bool
    {
        if ($this->config->isLocal()) {
            return true;
        }

        return $this->userHasGroup($this->config->operatorGroups) || $this->userHasGroup($this->config->adminGroups);
    }

    public function canViewAuditLogs(): bool
    {
        if ($this->config->isLocal()) {
            return true;
        }

        return $this->userHasGroup($this->config->adminGroups);
    }
}
