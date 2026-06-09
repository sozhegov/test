<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Товар (таблица products).
 */
final class Product
{
    public function __construct(
        public ?int $pid,
        public string $name,
        public int $qty,
        public float $inPrice,
        public float $outPrice,
        public int $discountLess = 0,
    ) {
    }
}
