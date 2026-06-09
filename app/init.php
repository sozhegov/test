<?php

declare(strict_types=1);

/**
 * Инициализация БД: пересоздаёт таблицы под сущности из README и наполняет
 * их связным тестовым набором. Скрипт идемпотентен — можно запускать повторно.
 *
 * Запуск:  docker exec test-php php /var/www/html/init.php
 */

require __DIR__ . '/bootstrap.php';

use App\Database;
use App\Entity\Category;
use App\Entity\PriceCharge;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Stock;
use App\Entity\Vendor;

$db = Database::connection();

/* ---------------------------------------------------------------------------
 * 1. Схема
 * ------------------------------------------------------------------------- */

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['product_category', 'price_charge', 'stocks', 'categories', 'vendor', 'products'] as $table) {
    $db->exec("DROP TABLE IF EXISTS {$table}");
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec(<<<'SQL'
    CREATE TABLE products (
        pid          INT AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(255) NOT NULL,
        qty          INT NOT NULL DEFAULT 0,
        inPrice      DECIMAL(12,2) NOT NULL,
        outPrice     DECIMAL(12,2) NOT NULL,
        discountLess TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$db->exec(<<<'SQL'
    CREATE TABLE vendor (
        vid                INT AUTO_INCREMENT PRIMARY KEY,
        name               VARCHAR(255) NOT NULL,
        discountLessCharge DECIMAL(6,4) NOT NULL,
        defaultCharge      DECIMAL(6,4) NOT NULL,
        type               ENUM('a','b','c') NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$db->exec(<<<'SQL'
    CREATE TABLE categories (
        cid       INT AUTO_INCREMENT PRIMARY KEY,
        parentCid INT NULL,
        name      VARCHAR(255) NOT NULL,
        CONSTRAINT fk_categories_parent
            FOREIGN KEY (parentCid) REFERENCES categories (cid) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$db->exec(<<<'SQL'
    CREATE TABLE stocks (
        id    INT AUTO_INCREMENT PRIMARY KEY,
        vid   INT NOT NULL,
        pid   INT NOT NULL,
        price DECIMAL(12,2) NOT NULL,
        qty   INT NOT NULL DEFAULT 0,
        UNIQUE KEY uq_stocks_vid_pid (vid, pid),
        CONSTRAINT fk_stocks_vendor  FOREIGN KEY (vid) REFERENCES vendor (vid)   ON DELETE CASCADE,
        CONSTRAINT fk_stocks_product FOREIGN KEY (pid) REFERENCES products (pid) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$db->exec(<<<'SQL'
    CREATE TABLE price_charge (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        vid         INT NOT NULL,
        fromPrice   DECIMAL(12,2) NOT NULL,
        toPrice     DECIMAL(12,2) NOT NULL,
        charge      DECIMAL(6,4) NOT NULL,
        chargeFound TINYINT(1) NOT NULL DEFAULT 1,
        KEY idx_price_charge_vid (vid),
        CONSTRAINT fk_price_charge_vendor FOREIGN KEY (vid) REFERENCES vendor (vid) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$db->exec(<<<'SQL'
    CREATE TABLE product_category (
        cid INT NOT NULL,
        pid INT NOT NULL,
        PRIMARY KEY (cid, pid),
        CONSTRAINT fk_pc_category FOREIGN KEY (cid) REFERENCES categories (cid) ON DELETE CASCADE,
        CONSTRAINT fk_pc_product  FOREIGN KEY (pid) REFERENCES products (pid)   ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

/* ---------------------------------------------------------------------------
 * 2. Тестовые данные (через классы-сущности)
 * ------------------------------------------------------------------------- */

// Товары: pid фиксируем явно, чтобы связать с остатками/категориями.
$products = [
    new Product(1, 'BMW 1:18',   qty: 5, inPrice: 1000.00, outPrice: 1500.00, discountLess: 0),
    new Product(2, 'Audi 1:43', qty: 0, inPrice: 1300.00,  outPrice: 1650.00,  discountLess: 1),
    new Product(3, 'ИЛ-2 1:43',     qty: 2, inPrice: 5000.00, outPrice: 8000.00, discountLess: 0),
    new Product(4, 'АН-2 1:43',     qty: 0, inPrice: 2000.00,  outPrice: 2600.00,  discountLess: 0),
];

$insertProduct = $db->prepare(
    'INSERT INTO products (pid, name, qty, inPrice, outPrice, discountLess)
     VALUES (:pid, :name, :qty, :inPrice, :outPrice, :discountLess)'
);
foreach ($products as $p) {
    $insertProduct->execute([
        'pid'          => $p->pid,
        'name'         => $p->name,
        'qty'          => $p->qty,
        'inPrice'      => $p->inPrice,
        'outPrice'     => $p->outPrice,
        'discountLess' => (int) $p->discountLess,
    ]);
}

// Поставщики всех типов a/b/c.
$vendors = [
    new Vendor(1, 'Мелкий магазин',        discountLessCharge: 1.5000, defaultCharge: 1.2500, type: Vendor::TYPE_A),
    new Vendor(2, 'Хобби-лавка',           discountLessCharge: 1.4000, defaultCharge: 1.2500, type: Vendor::TYPE_A),
    new Vendor(3, 'Удалённый склад',       discountLessCharge: 1.5000, defaultCharge: 1.2500, type: Vendor::TYPE_B),
    new Vendor(4, 'Расчётный поставщик',   discountLessCharge: 1.5000, defaultCharge: 1.2500, type: Vendor::TYPE_C),
];

$insertVendor = $db->prepare(
    'INSERT INTO vendor (vid, name, discountLessCharge, defaultCharge, type)
     VALUES (:vid, :name, :discountLessCharge, :defaultCharge, :type)'
);
foreach ($vendors as $v) {
    $insertVendor->execute([
        'vid'                => $v->vid,
        'name'               => $v->name,
        'discountLessCharge' => $v->discountLessCharge,
        'defaultCharge'      => $v->defaultCharge,
        'type'               => $v->type,
    ]);
}

// Складские остатки поставщиков по товарам.
$stocks = [
    new Stock(vid: 1, pid: 1, price: 1400.00, qty: 3),
    new Stock(vid: 1, pid: 2, price: 420.00,  qty: 10),
    new Stock(vid: 2, pid: 1, price: 1380.00, qty: 1),
    new Stock(vid: 3, pid: 1, price: 1350.00, qty: 20),
    new Stock(vid: 3, pid: 3, price: 7600.00, qty: 4),
    new Stock(vid: 4, pid: 3, price: 7200.00, qty: 4),
    new Stock(vid: 4, pid: 4, price: 230.00,  qty: 15),
];

$insertStock = $db->prepare(
    'INSERT INTO stocks (vid, pid, price, qty) VALUES (:vid, :pid, :price, :qty)'
);
foreach ($stocks as $s) {
    $insertStock->execute([
        'vid'   => $s->vid,
        'pid'   => $s->pid,
        'price' => $s->price,
        'qty'   => $s->qty,
    ]);
}

// Диапазоны наценок: каждому поставщику одинаковая сетка диапазонов.
$ranges = [
    [0.00,     499.99,   1.4500],
    [500.00,   999.99,   1.4000],
    [1000.00,  4999.99,  1.3500],
    [5000.00,  99999.99, 1.3000],
];
$priceCharges = [];
foreach ($vendors as $v) {
    foreach ($ranges as [$from, $to, $charge]) {
        $priceCharges[] = new PriceCharge(vid: (int) $v->vid, fromPrice: $from, toPrice: $to, charge: $charge);
    }
}

$insertCharge = $db->prepare(
    'INSERT INTO price_charge (vid, fromPrice, toPrice, charge, chargeFound)
     VALUES (:vid, :fromPrice, :toPrice, :charge, :chargeFound)'
);
foreach ($priceCharges as $pc) {
    $insertCharge->execute([
        'vid'         => $pc->vid,
        'fromPrice'   => $pc->fromPrice,
        'toPrice'     => $pc->toPrice,
        'charge'      => $pc->charge,
        'chargeFound' => (int) $pc->chargeFound,
    ]);
}

// Дерево категорий.
$categories = [
    new Category(1, null, 'Модели'),
    new Category(2, 1,    'Автомодели'),
    new Category(3, 1, 'Авиамодели'),
];

$insertCategory = $db->prepare(
    'INSERT INTO categories (cid, parentCid, name) VALUES (:cid, :parentCid, :name)'
);
foreach ($categories as $c) {
    $insertCategory->execute([
        'cid'       => $c->cid,
        'parentCid' => $c->parentCid,
        'name'      => $c->name,
    ]);
}

// Привязка товаров к категориям.
$productCategories = [
    new ProductCategory(cid: 2, pid: 1),
    new ProductCategory(cid: 2, pid: 2),
    new ProductCategory(cid: 3, pid: 3),
    new ProductCategory(cid: 3, pid: 4),
];

$insertProductCategory = $db->prepare(
    'INSERT INTO product_category (cid, pid) VALUES (:cid, :pid)'
);
foreach ($productCategories as $pc) {
    $insertProductCategory->execute([
        'cid' => $pc->cid,
        'pid' => $pc->pid,
    ]);
}

/* ---------------------------------------------------------------------------
 * 3. Отчёт
 * ------------------------------------------------------------------------- */

$tables = ['products', 'vendor', 'stocks', 'price_charge', 'categories', 'product_category'];
echo "БД инициализирована. Строк в таблицах:" . PHP_EOL;
foreach ($tables as $table) {
    $count = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    echo sprintf("  %-18s %d%s", $table, $count, PHP_EOL);
}
