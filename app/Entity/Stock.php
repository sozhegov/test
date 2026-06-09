<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Складской остаток поставщика по товару (таблица stocks).
 */
final class Stock
{
    public function __construct(
        public int $vid,
        public int $pid,
        public float $price,
        public int $qty,
    ) {
    }
}
