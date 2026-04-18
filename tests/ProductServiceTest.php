<?php

declare(strict_types=1);

namespace App\Tests;

use App\Exceptions\ApiException;
use App\Repositories\ProductRepository;
use App\Services\ProductService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\ProductService
 */
final class ProductServiceTest extends TestCase
{
    private static string $projectRoot;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__);
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', self::$projectRoot);
        }
    }

    /**
     * Compare must honor sparse fieldsets and include numeric precio in ARS.
     */
    public function testCompareAppliesFieldFiltering(): void
    {
        $repository = new ProductRepository(self::$projectRoot . DIRECTORY_SEPARATOR . 'data');
        $service = new ProductService($repository);

        $result = $service->compare([1, 2], ['marca', 'camara', 'precio'], null);

        self::assertSame(['Samsung Galaxy S24', 'iPhone 15 Pro'], $result['productos']);
        self::assertSame(['marca', 'camara', 'precio'], array_keys($result['comparacion']));

        self::assertSame(
            ['1' => 'Samsung', '2' => 'Apple'],
            $result['comparacion']['marca'],
        );
        self::assertSame(
            ['1' => '50MP', '2' => '48MP'],
            $result['comparacion']['camara'],
        );
        self::assertSame(1299999.0, $result['comparacion']['precio']['1']);
        self::assertSame(1599999.0, $result['comparacion']['precio']['2']);
        self::assertSame(1, $result['lista_de_precios_id']);
    }

    public function testCompareWithoutPrecioOmitsListaDePreciosId(): void
    {
        $repository = new ProductRepository(self::$projectRoot . DIRECTORY_SEPARATOR . 'data');
        $service = new ProductService($repository);

        $result = $service->compare([1, 2], ['marca'], null);

        self::assertArrayNotHasKey('lista_de_precios_id', $result);
    }

    /**
     * List endpoint exposes which ARS price list was used for each row.
     */
    public function testListProductsIncludesListaDePreciosIdOnPrecio(): void
    {
        $repository = new ProductRepository(self::$projectRoot . DIRECTORY_SEPARATOR . 'data');
        $service = new ProductService($repository);

        $rows = $service->listProducts(null, null);
        self::assertNotEmpty($rows);
        self::assertSame(1, $rows[0]['precio']['lista_de_precios_id']);
    }

    /**
     * Omitting campos must return the union of specification keys plus precio.
     */
    public function testCompareWithoutCamposReturnsUnionPlusPrecio(): void
    {
        $repository = new ProductRepository(self::$projectRoot . DIRECTORY_SEPARATOR . 'data');
        $service = new ProductService($repository);

        $result = $service->compare([1, 2], null, null);
        $keys = array_keys($result['comparacion']);

        self::assertContains('precio', $keys);
        self::assertSame('precio', $keys[array_key_last($keys)]);
    }

    public function testCompareRejectsLessThanTwoIds(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(400);

        $repository = new ProductRepository(self::$projectRoot . DIRECTORY_SEPARATOR . 'data');
        $service = new ProductService($repository);
        $service->compare([1], null, null);
    }

    public function testListProductsUsesExplicitPriceList(): void
    {
        $repository = new ProductRepository(self::$projectRoot . DIRECTORY_SEPARATOR . 'data');
        $service = new ProductService($repository);

        $rows = $service->listProducts(null, 2);
        self::assertSame(2, $rows[0]['precio']['lista_de_precios_id']);
        self::assertSame('USD', $rows[0]['precio']['moneda']);
        self::assertSame(1238.0, $rows[0]['precio']['valor']);
    }

    public function testGetProductUsesExplicitListWithoutMonedaQuery(): void
    {
        $repository = new ProductRepository(self::$projectRoot . DIRECTORY_SEPARATOR . 'data');
        $service = new ProductService($repository);

        $detail = $service->getProduct(1, null, 2);
        self::assertSame(1238.0, $detail['precio']['valor']);
        self::assertSame('USD', $detail['precio']['moneda']);
        self::assertSame(2, $detail['precio']['lista_de_precios_id']);
    }

    public function testGetProductRejectsMonedaMismatchWithLista(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(400);

        $repository = new ProductRepository(self::$projectRoot . DIRECTORY_SEPARATOR . 'data');
        $service = new ProductService($repository);
        $service->getProduct(1, 'ARS', 2);
    }

    public function testCompareUsesExplicitPriceList(): void
    {
        $repository = new ProductRepository(self::$projectRoot . DIRECTORY_SEPARATOR . 'data');
        $service = new ProductService($repository);

        $result = $service->compare([1, 2], ['precio'], 2);
        self::assertSame(2, $result['lista_de_precios_id']);
        self::assertSame(1238.0, $result['comparacion']['precio']['1']);
        self::assertSame(1523.0, $result['comparacion']['precio']['2']);
    }
}
