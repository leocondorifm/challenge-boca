<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Product category aggregate root (lookup table).
 */
final class Category
{
    /**
     * @param positive-int $id
     */
    public function __construct(
        public int $id,
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
            (string) $row['nombre'],
            (string) $row['descripcion'],
            (bool) $row['estado'],
        );
    }
}
