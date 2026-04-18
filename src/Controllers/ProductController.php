<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use App\Services\ProductService;

/**
 * HTTP adapter for product-related endpoints; delegates all rules to {@see ProductService}.
 */
final class ProductController
{
    public function __construct(
        private readonly ProductService $service,
    ) {
    }

    /**
     * GET /api/products — optional ?categoria_id= & ?lista_de_precios_id=
     *
     * @return list<array<string, mixed>>
     *
     * @throws ApiException When query parameters are invalid or the service layer fails
     */
    public function listProducts(): array
    {
        $categoriaId = null;
        if (isset($_GET['categoria_id']) && $_GET['categoria_id'] !== '') {
            if (!is_numeric($_GET['categoria_id'])) {
                throw new ApiException('Invalid categoria_id', 400);
            }
            $categoriaId = (int) $_GET['categoria_id'];
            if ($categoriaId < 1) {
                throw new ApiException('Invalid categoria_id', 400);
            }
        }

        return $this->service->listProducts($categoriaId, $this->parseOptionalListaDePreciosId());
    }

    /**
     * GET /api/products/{id} — optional ?moneda=ARS|USD & ?lista_de_precios_id=
     *
     * @param positive-int $id
     *
     * @return array<string, mixed>
     *
     * @throws ApiException When the id is invalid or the product cannot be resolved
     */
    public function getProduct(int $id): array
    {
        if ($id < 1) {
            throw new ApiException('Invalid product id', 400);
        }

        $moneda = null;
        if (isset($_GET['moneda']) && is_string($_GET['moneda']) && trim($_GET['moneda']) !== '') {
            $moneda = trim($_GET['moneda']);
        }

        return $this->service->getProduct($id, $moneda, $this->parseOptionalListaDePreciosId());
    }

    /**
     * GET /api/products/compare — required ?ids=1,2[,3,4] optional ?campos=a,b,c & ?lista_de_precios_id=
     *
     * @return array{productos: list<string>, comparacion: array<string, array<string, float|int|string|null>>, lista_de_precios_id?: int}
     *
     * @throws ApiException When ids or campos are malformed or business rules fail
     */
    public function compare(): array
    {
        if (!isset($_GET['ids']) || !is_string($_GET['ids']) || trim($_GET['ids']) === '') {
            throw new ApiException('Query parameter ids is required', 400);
        }

        $parts = array_map('trim', explode(',', $_GET['ids']));
        $ids = [];
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            if (!ctype_digit($p)) {
                throw new ApiException('Invalid product id in ids', 400);
            }
            $ids[] = (int) $p;
        }

        $campos = null;
        if (isset($_GET['campos']) && is_string($_GET['campos']) && trim($_GET['campos']) !== '') {
            $campos = array_values(array_filter(
                array_map('trim', explode(',', $_GET['campos'])),
                static fn (string $s): bool => $s !== '',
            ));
        }

        return $this->service->compare($ids, $campos, $this->parseOptionalListaDePreciosId());
    }

    /**
     * Parse optional `lista_de_precios_id` query string into a positive integer.
     *
     * @throws ApiException When the value is present but malformed
     */
    private function parseOptionalListaDePreciosId(): ?int
    {
        if (!array_key_exists('lista_de_precios_id', $_GET)) {
            return null;
        }

        $raw = $_GET['lista_de_precios_id'];
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_int($raw)) {
            $raw = (string) $raw;
        }

        if (!is_string($raw)) {
            throw new ApiException('Invalid lista_de_precios_id', 400);
        }

        $raw = trim($raw);
        if ($raw === '' || !ctype_digit($raw)) {
            throw new ApiException('Invalid lista_de_precios_id', 400);
        }

        $id = (int) $raw;
        if ($id < 1) {
            throw new ApiException('Invalid lista_de_precios_id', 400);
        }

        return $id;
    }

    /**
     * GET /api/categories
     *
     * @return list<array<string, mixed>>
     *
     * @throws ApiException When the service layer fails
     */
    public function listCategories(): array
    {
        return $this->service->listCategories();
    }
}
