<?php
/**
 * Printable Thermal Receipt View (SRS-F07, BR-05, BR-22)
 * Kedai Paradise POS
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$transactionId = (int)($_GET['id'] ?? 0);
$isReprint = !empty($_GET['reprint']);
$width = (int)($_GET['w'] ?? 58);
if (!in_array($width, [58, 80], true)) {
    $width = 58;
}

if ($transactionId <= 0) {
    http_response_code(404);
    exit('ID Transaksi tidak valid.');
}

$pdo = db();

// Fetch transaction & settings
$stmtTx = $pdo->prepare(
    'SELECT t.*, u.name AS cashier_name 
     FROM transactions t 
     LEFT JOIN users u ON u.user_id = t.user_id 
     WHERE t.transaction_id = :id LIMIT 1'
);
$stmtTx->execute([':id' => $transactionId]);
$tx = $stmtTx->fetch();

if (!$tx) {
    http_response_code(404);
    exit('Transaksi tidak ditemukan.');
}

$settings = $pdo->query('SELECT business_name, business_address, receipt_footer FROM settings WHERE settings_id = 1 LIMIT 1')->fetch();
$businessName = $settings['business_name'] ?? 'Kedai Paradise';
$businessAddress = $settings['business_address'] ?? '';
$receiptFooter = $settings['receipt_footer'] ?? 'Terima kasih telah berkunjung!';

// Fetch order items
$stmtOrders = $pdo->prepare(
    'SELECT o.*, m.name AS menu_name 
     FROM orders o 
     JOIN menu m ON m.menu_id = o.menu_id 
     WHERE o.transaction_id = :id'
);
$stmtOrders->execute([':id' => $transactionId]);
$orders = $stmtOrders->fetchAll();

// Fetch frozen modifiers snapshot
$orderIds = array_column($orders, 'order_id');
$modifiersByOrder = [];
if (!empty($orderIds)) {
    $in = implode(',', array_fill(0, count($orderIds), '?'));
    $stmtMods = $pdo->prepare("SELECT * FROM order_item_modifiers WHERE order_id IN ($in)");
    $stmtMods->execute($orderIds);
    while ($m = $stmtMods->fetch()) {
        $modifiersByOrder[(int)$m['order_id']][] = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Struk #<?= (int)$tx['order_no'] ?> - Kedai Paradise</title>
    <style>
        body {
            font-family: monospace;
            font-size: <?= $width === 80 ? '13px' : '11px' ?>;
            line-height: 1.2;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 4mm;
            width: <?= $width === 80 ? '72mm' : '48mm' ?>;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .flex-between {
            display: flex;
            justify-content: space-between;
        }
        .sub-item {
            padding-left: 8px;
            font-size: 10px;
        }
        .banner {
            border: 2px dashed #000;
            text-align: center;
            font-weight: bold;
            padding: 4px;
            margin-bottom: 6px;
            font-size: 12px;
        }
        @media print {
            body {
                padding: 0;
                margin: 0;
                width: <?= $width ?>mm;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 12px; text-align: center;">
        <button onclick="window.print()" style="padding: 6px 12px; font-weight: bold; cursor: pointer;">Cetak Struk</button>
    </div>

    <?php if ($tx['status'] === 'void'): ?>
        <div class="banner">*** DIBATALKAN ***</div>
    <?php elseif ($isReprint): ?>
        <div class="center bold" style="margin-bottom: 4px;">SALINAN / CETAK ULANG</div>
    <?php endif; ?>

    <div class="center bold" style="font-size: 13px;"><?= e($businessName) ?></div>
    <?php if ($businessAddress !== ''): ?>
        <div class="center"><?= e($businessAddress) ?></div>
    <?php endif; ?>
    <div class="divider"></div>

    <div class="flex-between">
        <span>No: #<?= (int)$tx['order_no'] ?></span>
        <span>Meja: <?= e($tx['table_no']) ?></span>
    </div>
    <div class="flex-between">
        <span>Kasir: <?= e($tx['cashier_name'] ?? 'Admin') ?></span>
        <span><?= e(date('d/m/Y H:i', strtotime($tx['date']))) ?> WIB</span>
    </div>
    <div class="divider"></div>

    <?php foreach ($orders as $ord): 
        $mods = $modifiersByOrder[(int)$ord['order_id']] ?? [];
    ?>
        <div><strong><?= (int)$ord['quantity'] ?>x <?= e($ord['menu_name']) ?></strong></div>
        <?php foreach ($mods as $mod): ?>
            <div class="sub-item">  [<?= e($mod['modifier_name']) ?>]</div>
        <?php endforeach; ?>
        <div class="flex-between">
            <span></span>
            <span><?= e(formatRupiah((int)$ord['subtotal'])) ?></span>
        </div>
    <?php endforeach; ?>

    <div class="divider"></div>
    <div class="flex-between">
        <span>Total:</span>
        <span class="bold"><?= e(formatRupiah((int)$tx['total'])) ?></span>
    </div>
    <div class="flex-between">
        <span>Metode:</span>
        <span><?= strtoupper(e($tx['method'])) ?></span>
    </div>
    <div class="flex-between">
        <span>Dibayar:</span>
        <span><?= e(formatRupiah((int)$tx['paid'])) ?></span>
    </div>
    <div class="flex-between">
        <span>Kembalian:</span>
        <span><?= e(formatRupiah((int)$tx['change_due'])) ?></span>
    </div>

    <div class="divider"></div>
    <div class="center"><?= e($receiptFooter) ?></div>
    <div class="center" style="font-size: 10px; margin-top: 4px;">
        Status: <?= $tx['status'] === 'completed' ? 'SELESAI' : ($tx['status'] === 'void' ? 'DIBATALKAN' : 'DIPROSES') ?>
    </div>

    <script>
        <?php if (!isset($_GET['autoprint']) || $_GET['autoprint'] !== '0'): ?>
            window.onload = function() { window.print(); };
        <?php endif; ?>
    </script>
</body>
</html>