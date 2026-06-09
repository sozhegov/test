<?php

declare(strict_types=1);

namespace App\Pricing;

/**
 * Источник расчётных цен поставщиков по товару.
 *
 * Абстракция (DIP): потребители (например Product::getCustomerProductPrice)
 * зависят от интерфейса, а не от конкретной реализации на PDO. Источником может стать
 * кэш, отдельная таблица или поисковый индекс (см. P.S. в README).
 */
interface ChargedPriceProviderInterface
{
    /**
     * @return ChargedPrice[] по одному элементу на поставщика, у которого есть товар.
     */
    public function forProduct(int $pid): array;

    /**
     * Минимальная расчётная цена (vendorPrice) в каждой группе поставщиков (a/b/c).
     *
     * @return array<string,float> ключ — тип поставщика, значение — минимальная цена.
     */
    public function minVendorPriceByType(int $pid): array;
}
