<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Logger\Logger;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Price;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Repositories\ProductRepository;

/**
 * Application use-cases for listing, detailing, and comparing catalog products.
 */
final class ProductService
{
    public function __construct(
        private readonly ProductRepository $repository,
    ) {
    }

    /**
     * List active products with category name and price from the default ARS list or an explicit list.
     *
     * @param int|null $categoriaId Optional filter by category id
     * @param int|null $listaDePreciosId Optional override: active price list id (currency is inferred from the list)
     *
     * @return list<array<string, mixed>>
     *
     * @throws ApiException On persistence failures or misconfiguration
     */
    public function listProducts(?int $categoriaId, ?int $listaDePreciosId): array
    {
        $categories = $this->indexCategoriesById($this->activeCategories());
        $priceCtx = $this->resolveListingPriceContext($listaDePreciosId);
        $pricesByProduct = $this->indexPriceByProductAndList($this->repository->getAllPrices());

        $out = [];
        foreach ($this->activeProducts() as $product) {
            if ($categoriaId !== null && $product->fkCategoria !== $categoriaId) {
                continue;
            }
            $cat = $categories[$product->fkCategoria] ?? null;
            $catName = $cat?->nombre ?? '';

            $amount = $this->findPriceAmount($pricesByProduct, $product->id, $priceCtx['listId']);
            $out[] = [
                'id' => $product->id,
                'nombre' => $product->nombre,
                'descripcion' => $product->descripcion,
                'categoria' => $catName,
                'precio' => $this->formatPrecio($amount, $priceCtx['moneda'], $priceCtx['listId']),
            ];
        }

        return $out;
    }

    /**
     * Return a single active product with EAV specs and price resolved by moneda and/or explicit price list.
     *
     * @param positive-int $id
     * @param string|null $monedaQuery Optional currency when no list override is provided (defaults to ARS). If
     *                                   {@see $listaDePreciosId} is set, this must match the list currency when present.
     * @param int|null $listaDePreciosId Optional override: active price list id (currency inferred from list when
     *                                   {@see $monedaQuery} is omitted)
     *
     * @return array<string, mixed>
     *
     * @throws ApiException 404 when missing/inactive product, 400 for unknown currency or inconsistent params
     */
    public function getProduct(int $id, ?string $monedaQuery, ?int $listaDePreciosId): array
    {
        $product = $this->findActiveProductById($id);
        if ($product === null) {
            throw new ApiException('Product not found', 404);
        }

        $categories = $this->indexCategoriesById($this->activeCategories());
        $catName = $categories[$product->fkCategoria]->nombre ?? '';

        $detailsMap = $this->detailsMapForProduct($id);
        $priceCtx = $this->resolveProductDetailPriceContext($listaDePreciosId, $monedaQuery);
        $pricesByProduct = $this->indexPriceByProductAndList($this->repository->getAllPrices());
        $amount = $this->findPriceAmount($pricesByProduct, $id, $priceCtx['listId']);

        return [
            'id' => $product->id,
            'nombre' => $product->nombre,
            'descripcion' => $product->descripcion,
            'categoria' => $catName,
            'precio' => $this->formatPrecio($amount, $priceCtx['moneda'], $priceCtx['listId']),
            'especificaciones' => $detailsMap,
        ];
    }

    /**
     * Build a side-by-side comparison keyed by attribute (including precio from default ARS or an explicit list).
     *
     * @param list<positive-int> $ids Two to four product ids
     * @param list<non-empty-string>|null $campos Optional ordered sparse fieldset; null means all fields
     * @param int|null $listaDePreciosId Optional override for the `precio` column when present
     *
     * @return array{productos: list<string>, comparacion: array<string, array<string, float|int|string|null>>, lista_de_precios_id?: positive-int}
     *
     * @throws ApiException 400/404 as per API rules
     */
    public function compare(array $ids, ?array $campos, ?int $listaDePreciosId): array
    {
        if (count($ids) < 2) {
            throw new ApiException('At least two product ids are required', 400);
        }
        if (count($ids) > 4) {
            throw new ApiException('A maximum of four product ids is allowed', 400);
        }

        $unique = array_values(array_unique($ids));
        if (count($unique) !== count($ids)) {
            throw new ApiException('Duplicate product ids are not allowed', 400);
        }

        $products = [];
        foreach ($ids as $pid) {
            $p = $this->findActiveProductById($pid);
            if ($p === null) {
                throw new ApiException('Product not found', 404);
            }
            $products[$pid] = $p;
        }

        $detailsByProduct = $this->detailsByProducts($ids);
        $unionKeys = $this->unionSpecificationKeys($detailsByProduct);
        $unionKeys[] = 'precio';

        $orderedFields = $this->orderComparisonFields($unionKeys, $campos);

        $priceCtx = $this->resolveComparePriceContext($listaDePreciosId);
        $pricesByProduct = $this->indexPriceByProductAndList($this->repository->getAllPrices());

        $comparacion = [];
        foreach ($orderedFields as $field) {
            $row = [];
            foreach ($ids as $pid) {
                $key = (string) $pid;
                if ($field === 'precio') {
                    $row[$key] = $this->findPriceAmount($pricesByProduct, $pid, $priceCtx['listId']);
                    continue;
                }
                $row[$key] = $detailsByProduct[$pid][$field] ?? null;
            }
            $comparacion[$field] = $row;
        }

        if ($comparacion === []) {
            Logger::warning('Comparison produced an empty field set', ['ids' => $ids]);
        }

        $names = [];
        foreach ($ids as $pid) {
            $names[] = $products[$pid]->nombre;
        }

        $payload = [
            'productos' => $names,
            'comparacion' => $comparacion,
        ];
        if (array_key_exists('precio', $comparacion)) {
            $payload['lista_de_precios_id'] = $priceCtx['listId'];
        }

        return $payload;
    }

    /**
     * @return list<array{id: int, nombre: string, descripcion: string, estado: bool}>
     *
     * @throws ApiException On persistence failures or misconfiguration
     */
    public function listCategories(): array
    {
        $out = [];
        foreach ($this->activeCategories() as $c) {
            $out[] = [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'descripcion' => $c->descripcion,
                'estado' => $c->estado,
            ];
        }

        return $out;
    }

    /**
     * @return list<Product>
     */
    private function activeProducts(): array
    {
        return array_values(array_filter(
            $this->repository->getAllProducts(),
            static fn (Product $p): bool => $p->estado,
        ));
    }

    /**
     * @return list<Category>
     */
    private function activeCategories(): array
    {
        return array_values(array_filter(
            $this->repository->getAllCategories(),
            static fn (Category $c): bool => $c->estado,
        ));
    }

    /**
     * @param positive-int $id
     */
    private function findActiveProductById(int $id): ?Product
    {
        foreach ($this->repository->getAllProducts() as $p) {
            if ($p->id === $id && $p->estado) {
                return $p;
            }
        }

        return null;
    }

    /**
     * @return array<int, Category>
     */
    private function indexCategoriesById(array $categories): array
    {
        $map = [];
        foreach ($categories as $c) {
            $map[$c->id] = $c;
        }

        return $map;
    }

    /**
     * @param array<int, array<string, string>> $detailsByProduct
     *
     * @return list<string>
     */
    private function unionSpecificationKeys(array $detailsByProduct): array
    {
        $keys = [];
        foreach ($detailsByProduct as $byKey) {
            foreach (array_keys($byKey) as $k) {
                $keys[$k] = true;
            }
        }

        $list = array_keys($keys);
        sort($list);

        return $list;
    }

    /**
     * @param list<string> $unionIncludingPrecio Union of spec keys plus precio at end
     * @param list<non-empty-string>|null $campos
     *
     * @return list<string>
     */
    private function orderComparisonFields(array $unionIncludingPrecio, ?array $campos): array
    {
        $specKeys = array_values(array_filter(
            $unionIncludingPrecio,
            static fn (string $k): bool => $k !== 'precio',
        ));

        if ($campos === null || $campos === []) {
            return [...$specKeys, 'precio'];
        }

        $wanted = [];
        foreach ($campos as $c) {
            $c = strtolower(trim($c));
            if ($c === '') {
                continue;
            }
            $wanted[] = $c;
        }

        $ordered = [];
        foreach ($wanted as $w) {
            if ($w === 'precio' || in_array($w, $specKeys, true)) {
                $ordered[] = $w;
            }
        }

        if ($ordered === []) {
            return [...$specKeys, 'precio'];
        }

        return $ordered;
    }

    /**
     * @param list<positive-int> $ids
     *
     * @return array<int, array<string, string>>
     */
    private function detailsByProducts(array $ids): array
    {
        $idSet = array_fill_keys($ids, true);
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = [];
        }

        foreach ($this->repository->getAllProductDetails() as $d) {
            if (!isset($idSet[$d->fkProducto])) {
                continue;
            }
            $out[$d->fkProducto][$d->clave] = $d->valor;
        }

        return $out;
    }

    /**
     * @param positive-int $productId
     *
     * @return array<string, string>
     */
    private function detailsMapForProduct(int $productId): array
    {
        $map = [];
        foreach ($this->repository->getAllProductDetails() as $d) {
            if ($d->fkProducto === $productId) {
                $map[$d->clave] = $d->valor;
            }
        }

        return $map;
    }

    /**
     * @param list<Price> $prices
     *
     * @return array<int, array<int, float>>
     */
    private function indexPriceByProductAndList(array $prices): array
    {
        $map = [];
        foreach ($prices as $price) {
            $map[$price->fkProducto][$price->fkListaDePrecios] = $price->precio;
        }

        return $map;
    }

    /**
     * @param array<int, array<int, float>> $pricesByProduct
     * @param positive-int $productId
     * @param positive-int $priceListId
     */
    private function findPriceAmount(array $pricesByProduct, int $productId, int $priceListId): float
    {
        return $pricesByProduct[$productId][$priceListId] ?? 0.0;
    }

    /**
     * Resolve the first active price list id for the given currency code.
     *
     * @param non-empty-string $code ARS or USD
     *
     * @return positive-int
     *
     * @throws ApiException When configuration is inconsistent
     */
    private function resolveDefaultPriceListId(string $code): int
    {
        $currencyId = null;
        foreach ($this->repository->getAllCurrencies() as $c) {
            if ($c->estado && strtoupper($c->simbolo) === $code) {
                $currencyId = $c->id;
                break;
            }
        }
        if ($currencyId === null) {
            throw new ApiException('Currency configuration missing', 500);
        }

        foreach ($this->repository->getAllPriceLists() as $list) {
            if ($list->estado && $list->fkMoneda === $currencyId) {
                return $list->id;
            }
        }

        throw new ApiException('Price list configuration missing', 500);
    }

    /**
     * Resolve which price list and currency code to use for catalog listing.
     *
     * @return array{listId: positive-int, moneda: non-empty-string}
     *
     * @throws ApiException When the override is invalid
     */
    private function resolveListingPriceContext(?int $listaOverride): array
    {
        if ($listaOverride !== null) {
            $ctx = $this->activePriceListContext($listaOverride);

            return ['listId' => $ctx['list']->id, 'moneda' => $ctx['moneda']];
        }

        $listId = $this->resolveDefaultPriceListId('ARS');

        return ['listId' => $listId, 'moneda' => 'ARS'];
    }

    /**
     * Resolve which price list and currency code to use for product detail.
     *
     * @return array{listId: positive-int, moneda: non-empty-string}
     *
     * @throws ApiException When parameters are inconsistent or invalid
     */
    private function resolveProductDetailPriceContext(?int $listaOverride, ?string $monedaQuery): array
    {
        if ($listaOverride !== null) {
            $ctx = $this->activePriceListContext($listaOverride);

            if ($monedaQuery !== null && trim($monedaQuery) !== '') {
                $requested = strtoupper(trim($monedaQuery));
                if (!in_array($requested, ['ARS', 'USD'], true)) {
                    throw new ApiException('Unsupported currency', 400);
                }
                if ($requested !== $ctx['moneda']) {
                    throw new ApiException('lista_de_precios_id does not match moneda', 400);
                }
            }

            return ['listId' => $ctx['list']->id, 'moneda' => $ctx['moneda']];
        }

        $m = ($monedaQuery !== null && trim($monedaQuery) !== '') ? strtoupper(trim($monedaQuery)) : 'ARS';
        if (!in_array($m, ['ARS', 'USD'], true)) {
            throw new ApiException('Unsupported currency', 400);
        }

        $listId = $this->resolveDefaultPriceListId($m);

        return ['listId' => $listId, 'moneda' => $m];
    }

    /**
     * Resolve which price list to use for comparison `precio` values.
     *
     * @return array{listId: positive-int, moneda: non-empty-string}
     *
     * @throws ApiException When the override is invalid
     */
    private function resolveComparePriceContext(?int $listaOverride): array
    {
        return $this->resolveListingPriceContext($listaOverride);
    }

    /**
     * Load an active price list and derive its ISO-like currency code.
     *
     * @param positive-int $listId
     *
     * @return array{list: PriceList, moneda: non-empty-string}
     *
     * @throws ApiException When the list or currency configuration is invalid
     */
    private function activePriceListContext(int $listId): array
    {
        $list = $this->findPriceListById($listId);
        if ($list === null) {
            throw new ApiException('Price list not found', 400);
        }
        if (!$list->estado) {
            throw new ApiException('Price list is inactive', 400);
        }

        $currency = $this->findCurrencyById($list->fkMoneda);
        if ($currency === null || !$currency->estado) {
            throw new ApiException('Price list configuration invalid', 500);
        }

        $code = strtoupper($currency->simbolo);

        return ['list' => $list, 'moneda' => $code];
    }

    /**
     * @param positive-int $id
     */
    private function findPriceListById(int $id): ?PriceList
    {
        foreach ($this->repository->getAllPriceLists() as $list) {
            if ($list->id === $id) {
                return $list;
            }
        }

        return null;
    }

    /**
     * @param positive-int $id
     */
    private function findCurrencyById(int $id): ?Currency
    {
        foreach ($this->repository->getAllCurrencies() as $currency) {
            if ($currency->id === $id) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * Shape the price payload for API consumers, including which price list was resolved.
     *
     * @param positive-int $listaDePreciosId Active {@see PriceList} id used for {@see $valor}
     *
     * @return array{valor: float, moneda: string, simbolo: string, lista_de_precios_id: int}
     */
    private function formatPrecio(float $valor, string $monedaCode, int $listaDePreciosId): array
    {
        $display = match (strtoupper($monedaCode)) {
            'ARS' => '$',
            'USD' => 'US$',
            default => strtoupper($monedaCode),
        };

        return [
            'valor' => $valor,
            'moneda' => strtoupper($monedaCode),
            'simbolo' => $display,
            'lista_de_precios_id' => $listaDePreciosId,
        ];
    }
}
