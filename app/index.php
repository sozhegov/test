<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Database;
use App\Entity\Product;

/**
 * Главная страница: выводит содержимое всех таблиц в виде HTML-таблиц.
 */

/**
 * Рендерит набор строк (array<array<string,scalar>>) как HTML-таблицу.
 * Заголовки берутся из ключей первой строки.
 */
function renderTable(array $rows): string
{
    if ($rows === []) {
        return '<p class="empty">Нет данных</p>';
    }

    $html = '<table><thead><tr>';
    foreach (array_keys($rows[0]) as $column) {
        $html .= '<th>' . htmlspecialchars((string) $column, ENT_QUOTES) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $value) {
            $html .= '<td>' . htmlspecialchars((string) $value, ENT_QUOTES) . '</td>';
        }
        $html .= '</tr>';
    }

    return $html . '</tbody></table>';
}

/**
 * Подпись с временем подготовки данных таблицы (в микросекундах).
 */
function renderTiming(float $micros): string
{
    return '<span class="timing">(' . number_format($micros, 1, '.', ' ') . ' мкс)</span>';
}

$tables = [
    'products'         => 'Товары (products)',
    'vendor'           => 'Поставщики (vendor)',
    'stocks'           => 'Складские остатки (stocks)',
    'price_charge'     => 'Диапазоны наценок (price_charge)',
    'categories'       => 'Категории (categories)',
    'product_category' => 'Привязки товаров к категориям (product_category)',
];

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Тестовые данные</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #1a1a1a; }
        h1 { margin-bottom: .25rem; }
        h2 { margin-top: 2rem; }
        table { border-collapse: collapse; margin-top: .5rem; }
        th, td { border: 1px solid #ccc; padding: .35rem .6rem; text-align: left; }
        th { background: #f2f2f2; }
        tr:nth-child(even) td { background: #fafafa; }
        .empty { color: #888; }
        .error { color: #b00020; }
        .meta { color: #666; font-size: .9rem; }
        .timing { color: #666; font-size: .8rem; font-weight: normal; }
    </style>
</head>
<body>
    <h1>Тестовые данные</h1>
    <p class="meta">
        PHP <?= htmlspecialchars(PHP_VERSION, ENT_QUOTES) ?> &middot;
        <a href="/init.php">Переинициализировать БД (init.php)</a>
    </p>

<?php
try {
    $db = Database::connection();

    $productIds = $db->query('SELECT pid FROM products ORDER BY pid')->fetchAll(PDO::FETCH_COLUMN);

    // Цена для покупателя по каждому товару (getCustomerProductPrice).
    $startedAt = hrtime(true);
    $customerRows = [];
    foreach ($productIds as $pid) {
        $product = Product::find($db, (int) $pid);
        $customerRows[] = [
            'pid'           => $product->pid,
            'name'          => $product->name,
            'qty'           => $product->qty,
            'inPrice'       => number_format($product->inPrice, 2, '.', ''),
            'outPrice'      => number_format($product->outPrice, 2, '.', ''),
            'customerPrice' => number_format(Product::getCustomerProductPrice($db, (int) $pid), 2, '.', ''),
        ];
    }
    $customerMicros = (hrtime(true) - $startedAt) / 1_000;
    echo '<h2>Цена для покупателя ' . renderTiming($customerMicros) . '</h2>';
    echo renderTable($customerRows);

    // Расчётные цены по поставщикам (getChargedProductPrices).
    $startedAt = hrtime(true);
    $chargedRows = [];
    foreach ($productIds as $pid) {
        foreach (Product::getChargedProductPrices($db, (int) $pid) as $cp) {
            $chargedRows[] = [
                'pid'                => $pid,
                'Название товара'    => $cp->productName,
                'discountLess'       => (int) $cp->discountLess,
                'vid'                => $cp->vid,
                'Название поставщика' => $cp->vendorName,
                'type'               => $cp->type,
                'discountLessPrice'  => number_format($cp->discountLessPrice, 2, '.', ''),
                'defaultChargePrice' => number_format($cp->defaultChargePrice, 2, '.', ''),
                'rangePrice'         => $cp->rangePrice === null ? '—' : number_format($cp->rangePrice, 2, '.', ''),
                'vendorPrice'        => number_format($cp->vendorPrice, 2, '.', ''),
            ];
        }
    }
    $chargedMicros = (hrtime(true) - $startedAt) / 1_000;
    echo '<h2>Расчётные цены поставщиков (vendorPrice) ' . renderTiming($chargedMicros) . '</h2>';
    echo renderTable($chargedRows);

    // Исходные таблицы БД.
    echo '<hr><h2>Содержимое таблиц</h2>';
    foreach ($tables as $table => $title) {
        echo '<h3>' . htmlspecialchars($title, ENT_QUOTES) . '</h3>';
        $rows = $db->query("SELECT * FROM {$table}")->fetchAll();
        echo renderTable($rows);
    }
} catch (\PDOException $e) {
    echo '<p class="error">Ошибка БД: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
    echo '<p>Возможно, таблицы ещё не созданы — выполните <a href="/init.php">init.php</a>.</p>';
}
?>
</body>
</html>
