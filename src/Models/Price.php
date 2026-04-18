<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Product price entry bound to a price list (and therefore a currency).
 */
final class Price
{
    /**
     * @param positive-int $id
     * @param positive-int $fkProducto
     * @param positive-int $fkListaDePrecios
     */
    public function __construct(
        public int $id,
        public int $fkProducto,
        public int $fkListaDePrecios,
        public float $precio,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['fk_producto'],
            (int) $row['fk_lista_de_precios'],
            (float) $row['precio'],
        );
    }
}
