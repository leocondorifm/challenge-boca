<?php

declare(strict_types=1);

/**
 * Front controller entry point (also usable as router script for PHP's built-in server).
 *
 * La documentación vive en `docs/`: `index.html` (Swagger UI) y `openapi.yaml` se sirven como
 * estáticos por Apache/PHP; la carpeta existe, así el rewrite a este archivo no aplica a `/docs/…`.
 */
require_once __DIR__ . '/bootstrap.php';

use App\Controllers\ProductController;
use App\Exceptions\ApiException;
use App\Logger\Logger;
use App\Repositories\ProductRepository;
use App\Router\Router;
use App\Services\ProductService;

header('Content-Type: application/json; charset=utf-8');

try {
    $dataDir = PROJECT_ROOT . DIRECTORY_SEPARATOR . 'data';
    $repository = new ProductRepository($dataDir);
    $service = new ProductService($repository);
    $controller = new ProductController($service);
    $router = new Router($controller);

    $data = $router->dispatch();
    http_response_code(200);
    echo json_encode(
        [
            'success' => true,
            'data' => $data,
        ],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
    );
} catch (ApiException $e) {
    Logger::error($e->getMessage(), ['code' => $e->getCode()]);
    $code = $e->getCode();
    if ($code < 400 || $code > 599) {
        $code = 500;
    }
    http_response_code($code);
    echo json_encode(
        [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $e->getMessage(),
            ],
        ],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
    );
} catch (Throwable $e) {
    Logger::error('Unhandled exception', ['message' => $e->getMessage(), 'type' => $e::class]);
    http_response_code(500);
    echo json_encode(
        [
            'success' => false,
            'error' => [
                'code' => 500,
                'message' => 'Internal server error',
            ],
        ],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
    );
}
