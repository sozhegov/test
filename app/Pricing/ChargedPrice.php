<?php

declare(strict_types=1);

namespace App\Pricing;

/**
 * Результат расчёта цены товара у одного поставщика (строка из README).
 *
 * Неизменяемый value object: 4 исходные цены + флаги + итоговый vendorPrice.
 */
final class ChargedPrice
{
    public function __construct(
        public readonly int $vid,
        public readonly string $vendorName,
        public readonly string $type,
        public readonly string $productName,
        public readonly float $ourPrice,
        public readonly float $discountLessPrice,
        public readonly float $defaultChargePrice,
        public readonly ?float $rangePrice,
        public readonly bool $chargeFound,
        public readonly bool $discountLess,
        public readonly float $vendorPrice,
    ) {
    }
}
