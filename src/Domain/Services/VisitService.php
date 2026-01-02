<?php

namespace Domain\Services;

use Domain\Repositories\AuditLogRepository;
use Domain\Repositories\VisitRepository;
use InvalidArgumentException;

class VisitService
{
    public function __construct(
        private VisitRepository $visits,
        private AuditLogRepository $auditLogs,
        private string $performedBy,
        private string $ipAddress
    ) {
    }

    public function registerEntry(array $payload): void
    {
        $this->assertRequired($payload, ['first_name', 'last_name', 'host_last_name', 'entry_signature']);
        $this->assertSignature($payload['entry_signature']);

        $data = [
            'first_name' => trim($payload['first_name']),
            'last_name' => trim($payload['last_name']),
            'company' => trim($payload['company'] ?? ''),
            'host_last_name' => trim($payload['host_last_name']),
            'entry_time' => date('Y-m-d H:i:s'),
            'entry_signature' => $payload['entry_signature'],
        ];

        $visitId = $this->visits->create($data);
        $this->auditLogs->log($visitId, 'Ingresso registrato', 'Entrata alle ' . $data['entry_time'], $this->performedBy, $this->ipAddress);
    }

    public function registerExit(array $payload): string
    {
        $this->assertRequired($payload, ['visit_id', 'exit_signature']);
        $this->assertSignature($payload['exit_signature']);

        $visitId = (int) $payload['visit_id'];
        $visit = $this->visits->findById($visitId);
        if (!$visit) {
            throw new InvalidArgumentException('Visitatore non trovato.');
        }

        $exitTime = date('Y-m-d H:i:s');
        $this->visits->registerExit($visitId, $exitTime, $payload['exit_signature']);
        $this->auditLogs->log($visitId, 'Uscita registrata', 'Uscita alle ' . $exitTime, $this->performedBy, $this->ipAddress);

        return $exitTime;
    }

    public function activeVisits(array $filters): array
    {
        return $this->visits->findActive($filters);
    }

    private function assertRequired(array $payload, array $keys): void
    {
        foreach ($keys as $key) {
            if (!isset($payload[$key]) || trim((string) $payload[$key]) === '') {
                throw new InvalidArgumentException('Campo obbligatorio mancante: ' . $key);
            }
        }
    }

    private function assertSignature(string $signature): void
    {
        if (!str_starts_with($signature, 'data:image/png;base64,')) {
            throw new InvalidArgumentException('Firma non valida.');
        }

        $data = substr($signature, strlen('data:image/png;base64,'));
        if (strlen($data) < 100) {
            throw new InvalidArgumentException('Firma troppo corta o mancante.');
        }
    }
}
