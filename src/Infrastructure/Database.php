<?php

namespace Infrastructure;

use Config\AppConfig;
use PDO;
use RuntimeException;

class Database
{
    private PDO $pdo;

    public function __construct(AppConfig $config)
    {
        $databasePath = $config->databasePath;
        if (!is_dir(dirname($databasePath))) {
            mkdir(dirname($databasePath), 0777, true);
        }

        $this->pdo = new PDO('sqlite:' . $databasePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->migrate();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function migrate(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            company TEXT,
            host_last_name TEXT NOT NULL,
            entry_time TEXT NOT NULL,
            exit_time TEXT,
            entry_signature TEXT,
            exit_signature TEXT
        )');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            visit_id INTEGER,
            action TEXT NOT NULL,
            details TEXT,
            performed_by TEXT,
            ip_address TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(visit_id) REFERENCES visits(id)
        )');

        $this->ensureColumn('audit_logs', 'performed_by', 'TEXT');
        $this->ensureColumn('audit_logs', 'ip_address', 'TEXT');
    }

    private function ensureColumn(string $table, string $column, string $type): void
    {
        $stmt = $this->pdo->prepare("PRAGMA table_info($table)");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($columns, 'name');
        if (!in_array($column, $names, true)) {
            $this->pdo->exec("ALTER TABLE $table ADD COLUMN $column $type");
        }
    }
}
