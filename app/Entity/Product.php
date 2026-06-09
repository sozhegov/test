<?php

declare(strict_types=1);

namespace App\Entity;

use App\Pricing\PdoChargedPriceProvider;
use PDO;
use RuntimeException;

/**
 * Товар (таблица products).
 */
final class Product
{
    /** Минимальная наценка (Входная*minMarkup) — нижняя граница цены продажи. */
    public const MIN_MARKUP = 1.25;

    public function __construct(
        public ?int $pid,
        public string $name,
        public int $qty,
        public float $inPrice,
        public float $outPrice,
        public int $discountLess = 0,
    ) {
    }

    /**
     * Находит товар по pid или возвращает null.
     */
    public static function find(PDO $db, int $pid): ?self
    {
        $stmt = $db->prepare(
            'SELECT pid, name, qty, inPrice, outPrice, discountLess FROM products WHERE pid = :pid'
        );
        $stmt->execute(['pid' => $pid]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new self(
            pid: (int) $row['pid'],
            name: (string) $row['name'],
            qty: (int) $row['qty'],
            inPrice: (float) $row['inPrice'],
            outPrice: (float) $row['outPrice'],
            discountLess: (int) $row['discountLess'],
        );
    }

    /**
     * Расчётные цены товара по каждому поставщику (см. README).
     *
     * @return \App\Pricing\ChargedPrice[]
     */
    public static function getChargedProductPrices(PDO $db, int $pid): array
    {
        return self::chargedPriceProvider($db)->forProduct($pid);
    }

    /**
     * Цена товара для покупателя по алгоритму task.txt.
     */
    public static function getCustomerProductPrice(PDO $db, int $pid): float
    {
        $product = self::find($db, $pid);
        if ($product === null) {
            throw new RuntimeException("Товар pid={$pid} не найден");
        }

        $minByType = self::chargedPriceProvider($db)->minVendorPriceByType($pid);

        return $product->customerPrice($minByType);
    }

    /**
     * Правила выбора цены для покупателя по минимальным ценам
     * поставщиков в группах a/b/c.
     *
     *   1. Товар есть на нашем складе (qty > 0): минимум из групп a и b, строго
     *      больше inPrice*MIN_MARKUP; иначе — inPrice*MIN_MARKUP.
     *   2. Товара нет на складе: минимум из группы c.
     *   3. Поставщиков нет вовсе: outPrice, но не ниже inPrice*MIN_MARKUP.
     *
     * @param array<string,float> $minByType минимальная vendorPrice по типу поставщика.
     */
    public function customerPrice(array $minByType): float
    {
        $floor = $this->inPrice * self::MIN_MARKUP;

        // 1. Есть на нашем складе — берём из a и b.
        if ($this->qty > 0) {
            $candidates = [];
            foreach (['a', 'b'] as $type) {
                if (isset($minByType[$type]) && $minByType[$type] > $floor) {
                    $candidates[] = $minByType[$type];
                }
            }

            return round($candidates !== [] ? min($candidates) : $floor, 2);
        }

        // 2. Нет на складе — берём из c.
        if (isset($minByType['c'])) {
            return round($minByType['c'], 2);
        }

        // 3. Данных по поставщикам нет — выходная цена, не ниже floor.
        return round(max($this->outPrice, $floor), 2);
    }

    private static function chargedPriceProvider(PDO $db): PdoChargedPriceProvider
    {
        return new PdoChargedPriceProvider($db);
    }
}
