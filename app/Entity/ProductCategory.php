<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Привязка товара к категории, many-to-many (таблица product_category).
 */
final class ProductCategory
{
    public function __construct(
        public int $cid,
        public int $pid,
    ) {
    }
}
