<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Price list groups prices under a single currency and validity window.
 */
final class PriceList
{
    /**
     * @param positive-int $id
     * @param positive-int $fkMoneda
     */
    public function __construct(
        public int $id,
        public string $descripcion,
        public string $fechaVigencia,
        public bool $estado,
        public int $fkMoneda,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['descripcion'],
            (string) $row['fecha_vigencia'],
            (bool) $row['estado'],
            (int) $row['fk_moneda'],
        );
    }
}
