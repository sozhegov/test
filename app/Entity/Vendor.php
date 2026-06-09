<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Поставщик (таблица vendor).
 *
 * type: a/b/c — категория поставщика (мелкий / крупный рекомендованный /
 * крупный расчётный).
 */
final class Vendor
{
    public const TYPE_A = 'a';
    public const TYPE_B = 'b';
    public const TYPE_C = 'c';

    public function __construct(
        public ?int $vid,
        public string $name,
        public float $discountLessCharge,
        public float $defaultCharge,
        public string $type,
    ) {
    }
}
