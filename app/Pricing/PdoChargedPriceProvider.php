<?php

declare(strict_types=1);

namespace App\Pricing;

use PDO;

/**
 * Реализация ChargedPriceProviderInterface на PDO.
 *
 * Выборка идёт по плану Q1→Q2→Q3 из README:
 *   Q1: products + stocks  — поставщики, у которых есть товар;
 *   Q2: + price_charge     — подбор диапазона наценки по цене стока;
 *   Q3: + vendor           — множители discountLess/default.
 *
 * Итоговый vendorPrice считается прямо в SQL по формуле README:
 *   discountLessPrice*discountLess
 *   + (defaultChargePrice*(1-chargeFound) + rangePrice*chargeFound)*(1-discountLess)
 */
final class PdoChargedPriceProvider implements ChargedPriceProviderInterface
{
    /** Базовый запрос: по строке на каждого поставщика товара с расчётным vendorPrice. */
    private const CHARGED_PRICES_SQL = <<<'SQL'
        SELECT
            v.vid                          AS vid,
            v.name                         AS vendorName,
            v.type                         AS type,
            p.name                         AS productName,
            p.outPrice                     AS ourPrice,
            p.discountLess                 AS discountLess,
            s.price * v.discountLessCharge AS discountLessPrice,
            s.price * v.defaultCharge      AS defaultChargePrice,
            s.price * pc.charge            AS rangePrice,
            pc.chargeFound                 AS chargeFound,
            ROUND(
                (s.price * v.discountLessCharge) * p.discountLess
                + (
                      (s.price * v.defaultCharge) * (1 - COALESCE(pc.chargeFound, 0))
                      + COALESCE(s.price * pc.charge, 0) * COALESCE(pc.chargeFound, 0)
                  ) * (1 - p.discountLess)
            , 2)                           AS vendorPrice
        FROM products p
        JOIN stocks s ON s.pid = p.pid
        JOIN vendor v ON v.vid = s.vid
        LEFT JOIN price_charge pc
            ON pc.vid = s.vid
           AND s.price >= pc.fromPrice
           AND s.price <= pc.toPrice
        WHERE p.pid = :pid
        SQL;

    public function __construct(
        private readonly PDO $db,
    ) {
    }

    public function forProduct(int $pid): array
    {
        $stmt = $this->db->prepare(self::CHARGED_PRICES_SQL . ' ORDER BY type, vendorPrice');
        $stmt->execute(['pid' => $pid]);

        $prices = [];
        foreach ($stmt->fetchAll() as $row) {
            $prices[] = new ChargedPrice(
                vid: (int) $row['vid'],
                vendorName: (string) $row['vendorName'],
                type: (string) $row['type'],
                productName: (string) $row['productName'],
                ourPrice: (float) $row['ourPrice'],
                discountLessPrice: (float) $row['discountLessPrice'],
                defaultChargePrice: (float) $row['defaultChargePrice'],
                rangePrice: $row['rangePrice'] !== null ? (float) $row['rangePrice'] : null,
                chargeFound: $row['chargeFound'] !== null && (int) $row['chargeFound'] === 1,
                discountLess: (bool) (int) $row['discountLess'],
                vendorPrice: (float) $row['vendorPrice'],
            );
        }

        return $prices;
    }

    public function minVendorPriceByType(int $pid): array
    {
        // Группируем по типу поставщика и сразу
        // берём минимальную расчётную цену в каждой группе.
        $sql = 'SELECT charged.type AS type, MIN(charged.vendorPrice) AS minVendorPrice'
            . ' FROM (' . self::CHARGED_PRICES_SQL . ') AS charged'
            . ' GROUP BY charged.type';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['pid' => $pid]);

        $minByType = [];
        foreach ($stmt->fetchAll() as $row) {
            $minByType[(string) $row['type']] = (float) $row['minVendorPrice'];
        }

        return $minByType;
    }
}
