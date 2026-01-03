<?php

namespace Infrastructure;

use DateTimeImmutable;
use RuntimeException;

class Logger
{
    private const LEVELS = [
        'error' => 0,
        'warning' => 1,
        'info' => 2,
        'debug' => 3,
    ];

    private string $logPath;
    private string $level;

    public function __construct(string $logPath, string $level = 'info')
    {
        $this->logPath = $logPath;
        $normalizedLevel = strtolower($level);
        $this->level = array_key_exists($normalizedLevel, self::LEVELS) ? $normalizedLevel : 'info';

        $directory = dirname($this->logPath);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossibile creare la cartella di log: ' . $directory);
        }
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $now = new DateTimeImmutable('now');
        $timestamp = $now->format('Y-m-d H:i:s');
        $line = sprintf(
            "%s [%s] %s%s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $this->formatContext($context)
        );

        $dailyPath = $this->resolveDailyPath($now);
        file_put_contents($dailyPath, $line, FILE_APPEND | LOCK_EX);
    }

    private function shouldLog(string $level): bool
    {
        $normalizedLevel = strtolower($level);
        if (!array_key_exists($normalizedLevel, self::LEVELS)) {
            return false;
        }

        return self::LEVELS[$normalizedLevel] <= self::LEVELS[$this->level];
    }

    private function formatContext(array $context): string
    {
        if ($context === []) {
            return '';
        }

        return ' | context=' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function resolveDailyPath(DateTimeImmutable $date): string
    {
        $directory = dirname($this->logPath);
        $info = pathinfo($this->logPath);
        $base = $info['filename'] ?? 'app';
        $extension = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';

        return $directory . DIRECTORY_SEPARATOR . $base . '_' . $date->format('Ymd') . $extension;
    }
}
