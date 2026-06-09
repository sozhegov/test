<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Диапазон наценки поставщика по входной цене (таблица price_charge).
 *
 * charge — множитель цены (1.2 = наценка 20%).
 * chargeFound — всегда 1, маркер того, что диапазон найден при join.
 */
final class PriceCharge
{
    public function __construct(
        public int $vid,
        public float $fromPrice,
        public float $toPrice,
        public float $charge,
        public int $chargeFound = 1,
    ) {
    }
}
