<?php

class Logger
{
    private string $logDir;
    private string $logLevel;

    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
    ];

    public function __construct()
    {
        $config = require __DIR__ . '/../config/config.php';
        $this->logDir = __DIR__ . '/../logs';
        $this->logLevel = $config['global']['log_level'] ?? 'info';

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (self::LEVELS[$level] < self::LEVELS[$this->logLevel]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$timestamp}] [{$level}] {$message}" . ($contextStr ? " | {$contextStr}" : '') . PHP_EOL;

        $logFile = $this->logDir . '/' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
