<?php

namespace Domain\Repositories;

use PDO;

class VisitRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO visits (first_name, last_name, company, host_last_name, entry_time, entry_signature) VALUES (:first_name, :last_name, :company, :host_last_name, :entry_time, :entry_signature)');
        $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':company' => $data['company'] !== '' ? $data['company'] : null,
            ':host_last_name' => $data['host_last_name'],
            ':entry_time' => $data['entry_time'],
            ':entry_signature' => $data['entry_signature'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function registerExit(int $id, string $exitTime, string $exitSignature): void
    {
        $stmt = $this->pdo->prepare('UPDATE visits SET exit_time = :exit_time, exit_signature = :exit_signature WHERE id = :id');
        $stmt->execute([
            ':exit_time' => $exitTime,
            ':exit_signature' => $exitSignature,
            ':id' => $id,
        ]);
    }

    public function findActive(array $filters = []): array
    {
        $query = 'SELECT * FROM visits WHERE exit_time IS NULL';
        $params = [];

        if (!empty($filters['search'])) {
            $query .= ' AND (first_name LIKE :term OR last_name LIKE :term OR company LIKE :term OR host_last_name LIKE :term)';
            $params[':term'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['from'])) {
            $query .= ' AND entry_time >= :from';
            $params[':from'] = $filters['from'] . ' 00:00:00';
        }

        if (!empty($filters['to'])) {
            $query .= ' AND entry_time <= :to';
            $params[':to'] = $filters['to'] . ' 23:59:59';
        }

        $query .= ' ORDER BY entry_time DESC';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM visits WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}
