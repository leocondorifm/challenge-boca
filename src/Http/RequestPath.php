<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Normalized request path (strip public base, handle PATH_INFO), shared by the router and documentation routes.
 */
final class RequestPath
{
    /**
     * Application path (e.g. /api/products, /docs, /meli/docs/openapi.yaml) without query string.
     *
     * @return non-empty-string
     */
    public static function current(): string
    {
        $pathInfo = $_SERVER['PATH_INFO'] ?? null;
        if (is_string($pathInfo) && $pathInfo !== '') {
            $normalized = '/' . ltrim(str_replace('\\', '/', $pathInfo), '/');

            return rtrim($normalized, '/') ?: '/';
        }

        $raw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($raw) || $raw === '') {
            $raw = '/';
        }
        $raw = str_replace('\\', '/', $raw);

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptName = is_string($scriptName) ? str_replace('\\', '/', $scriptName) : '';

        $base = rtrim(dirname($scriptName), '/');
        if ($base !== '' && $base !== '/' && str_starts_with($raw, $base)) {
            $raw = substr($raw, strlen($base)) ?: '/';
        }

        if (str_contains($raw, '.php/')) {
            $pos = strpos($raw, '.php/');
            if ($pos !== false) {
                $suffix = substr($raw, $pos + strlen('.php/'));
                $raw = '/' . ltrim($suffix, '/');
            }
        }

        return rtrim($raw, '/') ?: '/';
    }
}
