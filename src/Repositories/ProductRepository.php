<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\ApiException;
use App\Logger\Logger;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Price;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductDetail;

/**
 * JSON-backed persistence for catalog, EAV details, and pricing aggregates.
 *
 * All reads are defensive: missing or corrupt files are logged and surfaced as 500 errors.
 */
final class ProductRepository
{
    /**
     * @param non-empty-string $dataDirectory Absolute path to the directory containing JSON seed files
     */
    public function __construct(
        private readonly string $dataDirectory,
    ) {
    }

    /**
     * Return every category row from persistence (caller filters by estado).
     *
     * @return list<Category>
     *
     * @throws ApiException When the backing file is missing, unreadable, or not valid JSON
     */
    public function getAllCategories(): array
    {
        $rows = $this->loadJson('categorias.json');

        return array_map(static fn (array $r): Category => Category::fromArray($r), $rows);
    }

    /**
     * Return every product row from persistence (caller filters by estado / category).
     *
     * @return list<Product>
     *
     * @throws ApiException When the backing file is missing, unreadable, or not valid JSON
     */
    public function getAllProducts(): array
    {
        $rows = $this->loadJson('productos.json');

        return array_map(static fn (array $r): Product => Product::fromArray($r), $rows);
    }

    /**
     * Return all EAV detail rows.
     *
     * @return list<ProductDetail>
     *
     * @throws ApiException When the backing file is missing, unreadable, or not valid JSON
     */
    public function getAllProductDetails(): array
    {
        $rows = $this->loadJson('productos_detalle.json');

        return array_map(static fn (array $r): ProductDetail => ProductDetail::fromArray($r), $rows);
    }

    /**
     * Return all price rows.
     *
     * @return list<Price>
     *
     * @throws ApiException When the backing file is missing, unreadable, or not valid JSON
     */
    public function getAllPrices(): array
    {
        $rows = $this->loadJson('precios.json');

        return array_map(static fn (array $r): Price => Price::fromArray($r), $rows);
    }

    /**
     * Return all price list rows.
     *
     * @return list<PriceList>
     *
     * @throws ApiException When the backing file is missing, unreadable, or not valid JSON
     */
    public function getAllPriceLists(): array
    {
        $rows = $this->loadJson('listas_de_precios.json');

        return array_map(static fn (array $r): PriceList => PriceList::fromArray($r), $rows);
    }

    /**
     * Return all currency rows.
     *
     * @return list<Currency>
     *
     * @throws ApiException When the backing file is missing, unreadable, or not valid JSON
     */
    public function getAllCurrencies(): array
    {
        $rows = $this->loadJson('monedas.json');

        return array_map(static fn (array $r): Currency => Currency::fromArray($r), $rows);
    }

    /**
     * Load and decode a JSON array from the data directory.
     *
     * @param non-empty-string $fileName
     *
     * @return list<array<string, mixed>>
     *
     * @throws ApiException When IO or JSON decoding fails
     */
    private function loadJson(string $fileName): array
    {
        $path = $this->dataDirectory . DIRECTORY_SEPARATOR . $fileName;

        try {
            if (!is_file($path) || !is_readable($path)) {
                Logger::error('JSON data file missing or unreadable', ['file' => $fileName]);
                throw new ApiException('Internal server error', 500);
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                Logger::error('JSON data file could not be read', ['file' => $fileName]);
                throw new ApiException('Internal server error', 500);
            }

            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                Logger::error('JSON data file root must be a list', ['file' => $fileName]);
                throw new ApiException('Internal server error', 500);
            }

            /** @var list<array<string, mixed>> $decoded */
            return $decoded;
        } catch (\JsonException $e) {
            Logger::error('Corrupt JSON data file', ['file' => $fileName, 'message' => $e->getMessage()]);
            throw new ApiException('Internal server error', 500, $e);
        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Logger::error('Unexpected error reading JSON data file', ['file' => $fileName, 'message' => $e->getMessage()]);
            throw new ApiException('Internal server error', 500, $e);
        }
    }
}
