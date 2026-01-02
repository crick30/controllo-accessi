<?php

namespace Domain\Repositories;

use PDO;

class AuditLogRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function log(?int $visitId, string $action, ?string $details, string $performedBy, string $ipAddress): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO audit_logs (visit_id, action, details, performed_by, ip_address) VALUES (:visit_id, :action, :details, :performed_by, :ip_address)');
        $stmt->execute([
            ':visit_id' => $visitId,
            ':action' => $action,
            ':details' => $details,
            ':performed_by' => $performedBy,
            ':ip_address' => $ipAddress,
        ]);
    }

    public function latest(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_logs ORDER BY created_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
