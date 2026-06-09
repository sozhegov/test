<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Категория (таблица categories). Дерево через parentCid.
 */
final class Category
{
    public function __construct(
        public ?int $cid,
        public ?int $parentCid,
        public string $name,
    ) {
    }
}
