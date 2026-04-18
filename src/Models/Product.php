<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Core product entity (catalog row).
 */
final class Product
{
    /**
     * @param positive-int $id
     * @param positive-int $fkCategoria
     */
    public function __construct(
        public int $id,
        public int $fkCategoria,
        public string $nombre,
        public string $descripcion,
        public bool $estado,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['fk_categoria'],
            (string) $row['nombre'],
            (string) $row['descripcion'],
            (bool) $row['estado'],
        );
    }
}
