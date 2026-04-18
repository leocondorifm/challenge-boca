<?php

declare(strict_types=1);

namespace App\Router;

use App\Controllers\ProductController;
use App\Exceptions\ApiException;
use App\Http\RequestPath;

/**
 * Minimal front controller that maps the request path to controller actions.
 */
final class Router
{
    public function __construct(
        private readonly ProductController $controller,
    ) {
    }

    /**
     * Dispatch the current HTTP request and return a JSON-serializable payload body (the "data" node).
     *
     * @return array<string|int, mixed>
     *
     * @throws ApiException For unsupported methods or unknown routes
     */
    public function dispatch(): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET') {
            throw new ApiException('Method not allowed', 400);
        }

        $path = RequestPath::current();

        if ($path === '/api/products/compare') {
            return $this->controller->compare();
        }

        if (preg_match('#^/api/products/(\d+)$#', $path, $m)) {
            return $this->controller->getProduct((int) $m[1]);
        }

        if ($path === '/api/products') {
            return $this->controller->listProducts();
        }

        if ($path === '/api/categories') {
            return $this->controller->listCategories();
        }

        throw new ApiException('Not found', 404);
    }
}
