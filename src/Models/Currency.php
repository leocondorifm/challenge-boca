<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Currency definition including ISO-like code and exchange metadata.
 */
final class Currency
{
    /**
     * @param positive-int $id
     */
    public function __construct(
        public int $id,
        public string $descripcion,
        public string $simbolo,
        public float $cotizacion,
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
            (string) $row['descripcion'],
            (string) $row['simbolo'],
            (float) $row['cotizacion'],
            (bool) $row['estado'],
        );
    }
}
