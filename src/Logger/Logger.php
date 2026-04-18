<?php

declare(strict_types=1);

namespace App\Logger;

/**
 * File-based application logger for runtime observability without external services.
 *
 * Writes structured lines to logs/error.log (directory and file are created on demand).
 */
final class Logger
{
    private const LOG_DIR = 'logs';

    private const LOG_FILE = 'logs/error.log';

    /**
     * Append an ERROR-level line to the error log.
     *
     * @param string $message Human-readable description of the event
     * @param array<string, mixed> $context Optional structured data serialized as JSON
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * Append a WARNING-level line to the error log.
     *
     * @param string $message Human-readable description of the event
     * @param array<string, mixed> $context Optional structured data serialized as JSON
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /**
     * Append an INFO-level line to the error log.
     *
     * @param string $message Human-readable description of the event
     * @param array<string, mixed> $context Optional structured data serialized as JSON
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function write(string $level, string $message, array $context): void
    {
        $root = PROJECT_ROOT;
        $dir = $root . DIRECTORY_SEPARATOR . self::LOG_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $path = $root . DIRECTORY_SEPARATOR . self::LOG_FILE;
        $timestamp = date('Y-m-d H:i:s');
        $uri = $_SERVER['REQUEST_URI'] ?? '-';

        $line = sprintf('[%s] [%s] %s — %s', $timestamp, $level, $uri, $message);
        if ($context !== []) {
            try {
                $line .= ' ' . json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } catch (\JsonException) {
                $line .= ' {"context":"unserializable"}';
            }
        }
        $line .= PHP_EOL;

        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
