<?php

declare(strict_types=1);

namespace App\Models;

/**
 * EAV-style attribute row for heterogeneous product specifications.
 */
final class ProductDetail
{
    /**
     * @param positive-int $id
     * @param positive-int $fkProducto
     */
    public function __construct(
        public int $id,
        public int $fkProducto,
        public string $clave,
        public string $valor,
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
            (string) $row['clave'],
            (string) $row['valor'],
        );
    }
}
