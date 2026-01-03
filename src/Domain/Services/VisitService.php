<?php

namespace Domain\Services;

use Domain\Repositories\AuditLogRepository;
use Domain\Repositories\VisitRepository;
use Infrastructure\Logger;
use InvalidArgumentException;

class VisitService
{
    public function __construct(
        private VisitRepository $visits,
        private AuditLogRepository $auditLogs,
        private string $performedBy,
        private string $ipAddress,
        private Logger $logger
    ) {
    }

    public function registerEntry(array $payload): void
    {
        $this->assertRequired($payload, ['first_name', 'last_name', 'host_last_name', 'entry_signature']);
        $this->assertSignature($payload['entry_signature']);

        $this->logger->debug('Avvio registrazione ingresso', [
            'performed_by' => $this->performedBy,
            'ip' => $this->ipAddress,
            'host_last_name' => $payload['host_last_name'] ?? null,
        ]);

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
        $this->logger->info('Ingresso registrato', [
            'visit_id' => $visitId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company' => $data['company'] ?: null,
            'host_last_name' => $data['host_last_name'],
            'entry_time' => $data['entry_time'],
            'performed_by' => $this->performedBy,
            'ip' => $this->ipAddress,
        ]);
    }

    public function registerExit(array $payload): string
    {
        $this->assertRequired($payload, ['visit_id', 'exit_signature']);
        $this->assertSignature($payload['exit_signature']);

        $visitId = (int) $payload['visit_id'];
        $visit = $this->visits->findById($visitId);
        if (!$visit) {
            $this->logger->warning('Tentativo di registrare uscita per visitatore inesistente', [
                'visit_id' => $visitId,
                'performed_by' => $this->performedBy,
                'ip' => $this->ipAddress,
            ]);
            throw new InvalidArgumentException('Visitatore non trovato.');
        }

        $exitTime = date('Y-m-d H:i:s');
        $this->visits->registerExit($visitId, $exitTime, $payload['exit_signature']);
        $this->auditLogs->log($visitId, 'Uscita registrata', 'Uscita alle ' . $exitTime, $this->performedBy, $this->ipAddress);
        $this->logger->info('Uscita registrata', [
            'visit_id' => $visitId,
            'performed_by' => $this->performedBy,
            'ip' => $this->ipAddress,
            'exit_time' => $exitTime,
        ]);

        return $exitTime;
    }

    public function activeVisits(array $filters): array
    {
        $this->logger->debug('Recupero lista presenti attivi', $this->safeFilters($filters));
        return $this->visits->findActive($filters);
    }

    public function historyVisits(array $filters): array
    {
        $this->logger->debug('Recupero storico visite', $this->safeFilters($filters));
        return $this->visits->findHistory($filters);
    }

    private function assertRequired(array $payload, array $keys): void
    {
        foreach ($keys as $key) {
            if (!isset($payload[$key]) || trim((string) $payload[$key]) === '') {
                $this->logger->warning('Campo obbligatorio mancante', [
                    'field' => $key,
                    'performed_by' => $this->performedBy,
                    'ip' => $this->ipAddress,
                ]);
                throw new InvalidArgumentException('Campo obbligatorio mancante: ' . $key);
            }
        }
    }

    private function assertSignature(string $signature): void
    {
        if (!str_starts_with($signature, 'data:image/png;base64,')) {
            $this->logger->warning('Formato firma non valido', [
                'performed_by' => $this->performedBy,
                'ip' => $this->ipAddress,
            ]);
            throw new InvalidArgumentException('Firma non valida.');
        }

        $data = substr($signature, strlen('data:image/png;base64,'));
        if (strlen($data) < 100) {
            $this->logger->warning('Firma troppo corta o mancante', [
                'performed_by' => $this->performedBy,
                'ip' => $this->ipAddress,
            ]);
            throw new InvalidArgumentException('Firma troppo corta o mancante.');
        }
    }

    private function safeFilters(array $filters): array
    {
        $clean = [];
        foreach ($filters as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $clean[$key] = $value;
        }

        if ($clean === []) {
            return ['filters' => 'none'];
        }

        return $clean;
    }
}
