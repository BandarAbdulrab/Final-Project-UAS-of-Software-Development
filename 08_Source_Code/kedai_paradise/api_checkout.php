<?php
/**
 * Atomic Checkout & 3-Pool Allocation Engine (SRS-F05, F06, F13, BR-03, BR-05, BR-06, BR-08, BR-09, BR-21, BR-22, BR-26)
 * Kedai Paradise POS
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metode request tidak diizinkan.', 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput ?: '', true);
    if (is_array($decoded)) {
        $_POST = array_merge($_POST, $decoded);
    }
}

requireCsrf();

$pdo = db();

// Ensure used_tokens array exists in session
if (!isset($_SESSION['used_tokens']) || !is_array($_SESSION['used_tokens'])) {
    $_SESSION['used_tokens'] = [];
}

if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}

// Helper to return JSON with the CURRENT (fresh) session checkout_token
$checkoutResponse = function (array $data, int $status = 200): never {
    $data['next_token'] = $_SESSION['checkout_token'];
    jsonResponse($data, $status);
};

$token = trim((string)($_POST['checkout_token'] ?? ''));

// Check if token was already consumed first (BR-26, TC-D15)
if ($token !== '' && array_key_exists($token, $_SESSION['used_tokens'])) {
    $consumedInfo = $_SESSION['used_tokens'][$token];
    if ($consumedInfo === null) {
        $checkoutResponse(['success' => false, 'message' => 'Transaksi sedang diproses, jangan kirim ulang.'], 409);
    } else {
        $checkoutResponse([
            'success'        => false,
            'message'        => 'Transaksi ini sudah disimpan sebagai Pesanan No. ' . $consumedInfo['order_no'],
            'transaction_id' => $consumedInfo['transaction_id'],
            'order_no'       => $consumedInfo['order_no'],
        ], 409);
    }
}

// Otherwise token must match the active session checkout token
if ($token === '' || !hash_equals((string)$_SESSION['checkout_token'], $token)) {
    $checkoutResponse(['success' => false, 'message' => 'Token transaksi tidak valid.'], 409);
}

// Immediately mark token as in-progress (null)
$_SESSION['used_tokens'][$token] = null;

// Validate payload inputs
$tableNo = trim((string)($_POST['table_no'] ?? ''));
$method  = trim((string)($_POST['method'] ?? ''));
$paid    = isset($_POST['paid']) ? (int)$_POST['paid'] : 0;
$items   = $_POST['items'] ?? [];

if ($tableNo === '' || !in_array($method, ['cash', 'qris'], true) || empty($items) || !is_array($items)) {
    unset($_SESSION['used_tokens'][$token]);
    $checkoutResponse(['success' => false, 'message' => 'Data pesanan tidak lengkap.'], 400);
}

// Validate table count from settings
$settings         = $pdo->query('SELECT table_count, operating_percent FROM settings WHERE settings_id = 1 LIMIT 1')->fetch();
$tableCount       = (int)($settings['table_count'] ?? 10);
$operatingPercent = (float)($settings['operating_percent'] ?? 50.00);

if ($tableNo !== 'TA') {
    $tNum = (int)$tableNo;
    if ($tNum < 1 || $tNum > $tableCount) {
        unset($_SESSION['used_tokens'][$token]);
        $checkoutResponse(['success' => false, 'message' => 'Nomor meja tidak valid.'], 400);
    }
}

// Begin Atomic Database Transaction
$pdo->beginTransaction();

try {
    $calculatedTotal     = 0;
    $calculatedTotalCost = 0;
    $orderRowsToInsert   = [];

    // Server-side price & modifier recalculation (BR-21)
    foreach ($items as $rawItem) {
        $menuId            = (int)($rawItem['menu_id'] ?? 0);
        $qty               = (int)($rawItem['quantity'] ?? 0);
        $clientModifierIds = $rawItem['modifier_ids'] ?? [];
        if (!is_array($clientModifierIds)) {
            $clientModifierIds = [];
        }

        if ($menuId <= 0 || $qty < 1) {
            throw new Exception('Item atau kuantitas pesanan tidak valid.');
        }

        // Fetch menu item
        $stmtMenu = $pdo->prepare('SELECT menu_id, name, price, cost_price, available, status FROM menu WHERE menu_id = :id FOR UPDATE');
        $stmtMenu->execute([':id' => $menuId]);
        $menu = $stmtMenu->fetch();

        if (!$menu || (int)$menu['status'] !== 1 || (int)$menu['available'] !== 1) {
            throw new Exception('Menu ' . ($menu['name'] ?? '') . ' tidak tersedia.');
        }

        $basePrice = (int)$menu['price'];
        $baseCost  = (int)$menu['cost_price'];

        $lineModifierPrice = 0;
        $lineModifierCost  = 0;
        $snapshotModifiers = [];

        if (!empty($clientModifierIds)) {
            $inClause = implode(',', array_fill(0, count($clientModifierIds), '?'));
            $params   = $clientModifierIds;
            array_unshift($params, $menuId);

            $stmtMods = $pdo->prepare(
                "SELECT mi.modifier_id, mi.name, mi.extra_price, mi.extra_cost, mi.available, mg.group_id, mg.is_required, mg.type
                 FROM menu_modifier_items mi
                 JOIN menu_modifier_groups mg ON mg.group_id = mi.group_id
                 WHERE mg.menu_id = ? AND mi.modifier_id IN ($inClause)"
            );
            $stmtMods->execute($params);
            $foundMods = $stmtMods->fetchAll();

            if (count($foundMods) !== count(array_unique($clientModifierIds))) {
                throw new Exception('Beberapa pilihan modifier tidak valid atau sudah tidak tersedia.');
            }

            foreach ($foundMods as $mod) {
                if ((int)$mod['available'] !== 1) {
                    throw new Exception('Opsi ' . $mod['name'] . ' sedang habis.');
                }
                $extraP = (int)$mod['extra_price'];
                $extraC = (int)$mod['extra_cost'];
                $lineModifierPrice += $extraP;
                $lineModifierCost  += $extraC;

                $snapshotModifiers[] = [
                    'modifier_name' => $mod['name'],
                    'price'         => $extraP,
                    'cost_price'    => $extraC,
                ];
            }
        }

        $unitPrice     = $basePrice + $lineModifierPrice;
        $unitCost      = $baseCost + $lineModifierCost;
        $subtotal      = $unitPrice * $qty;
        $lineTotalCost = $unitCost * $qty;

        $calculatedTotal     += $subtotal;
        $calculatedTotalCost += $lineTotalCost;

        $orderRowsToInsert[] = [
            'menu_id'        => $menuId,
            'quantity'       => $qty,
            'price'          => $basePrice,
            'cost_price'     => $baseCost,
            'modifier_price' => $lineModifierPrice,
            'modifier_cost'  => $lineModifierCost,
            'subtotal'       => $subtotal,
            'modifiers'      => $snapshotModifiers,
        ];
    }

    // Payment validation (BR-03)
    if ($method === 'cash') {
        if ($paid < $calculatedTotal) {
            throw new Exception('Uang diterima kurang dari total tagihan.');
        }
        $changeDue = $paid - $calculatedTotal;
    } else {
        $paid      = $calculatedTotal;
        $changeDue = 0;
    }

    // Profit & 3-Pool Virtual Allocation Engine (SRS-F13, BR-08, BR-09)
    $grossProfit    = $calculatedTotal - $calculatedTotalCost;
    $allocRestock   = $calculatedTotalCost;
    $allocOperating = (int)round(($operatingPercent / 100.0) * $grossProfit);
    $allocProfit    = $grossProfit - $allocOperating;

    if (($allocRestock + $allocOperating + $allocProfit) !== $calculatedTotal) {
        $diff = $calculatedTotal - ($allocRestock + $allocOperating + $allocProfit);
        $allocProfit += $diff;
    }

    // Daily Sequential Order Number (BR-06) with retry on duplicate key
    $maxRetries    = 3;
    $transactionId = null;
    $orderNo       = null;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $stmtMax = $pdo->prepare('SELECT COALESCE(MAX(order_no), 0) + 1 FROM transactions WHERE order_date = CURDATE() FOR UPDATE');
            $stmtMax->execute();
            $orderNo = (int)$stmtMax->fetchColumn();

            $stmtTx = $pdo->prepare(
                'INSERT INTO transactions
                 (order_no, date, table_no, method, total, paid, change_due, status,
                  total_cost, gross_profit, alloc_restock, alloc_operating, alloc_profit, user_id)
                 VALUES
                 (:order_no, NOW(), :table_no, :method, :total, :paid, :change_due, "processing",
                  :total_cost, :gross_profit, :alloc_restock, :alloc_operating, :alloc_profit, :user_id)'
            );
            $stmtTx->execute([
                ':order_no'        => $orderNo,
                ':table_no'        => $tableNo,
                ':method'          => $method,
                ':total'           => $calculatedTotal,
                ':paid'            => $paid,
                ':change_due'      => $changeDue,
                ':total_cost'      => $calculatedTotalCost,
                ':gross_profit'    => $grossProfit,
                ':alloc_restock'   => $allocRestock,
                ':alloc_operating' => $allocOperating,
                ':alloc_profit'    => $allocProfit,
                ':user_id'         => $_SESSION['user_id'],
            ]);

            $transactionId = (int)$pdo->lastInsertId();
            break;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 && $attempt < $maxRetries) {
                continue;
            }
            throw $e;
        }
    }

    if (!$transactionId) {
        throw new Exception('Gagal menghasilkan nomor pesanan unik.');
    }

    // Insert orders and frozen modifier snapshots
    $stmtOrder = $pdo->prepare(
        'INSERT INTO orders
         (transaction_id, menu_id, quantity, price, cost_price, modifier_price, modifier_cost, subtotal)
         VALUES
         (:tid, :mid, :qty, :price, :cost, :mprice, :mcost, :sub)'
    );

    $stmtModSnap = $pdo->prepare(
        'INSERT INTO order_item_modifiers (order_id, modifier_name, price, cost_price)
         VALUES (:oid, :mname, :price, :cost)'
    );

    foreach ($orderRowsToInsert as $row) {
        $stmtOrder->execute([
            ':tid'    => $transactionId,
            ':mid'    => $row['menu_id'],
            ':qty'    => $row['quantity'],
            ':price'  => $row['price'],
            ':cost'   => $row['cost_price'],
            ':mprice' => $row['modifier_price'],
            ':mcost'  => $row['modifier_cost'],
            ':sub'    => $row['subtotal'],
        ]);
        $orderId = (int)$pdo->lastInsertId();

        foreach ($row['modifiers'] as $modSnap) {
            $stmtModSnap->execute([
                ':oid'   => $orderId,
                ':mname' => $modSnap['modifier_name'],
                ':price' => $modSnap['price'],
                ':cost'  => $modSnap['cost_price'],
            ]);
        }
    }

    // Commit transaction
    $pdo->commit();

    // Mark token as successfully consumed with result
    $_SESSION['used_tokens'][$token] = [
        'transaction_id' => $transactionId,
        'order_no'       => $orderNo,
    ];
    // Generate fresh checkout token for subsequent cart orders
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));

    $checkoutResponse([
        'success'        => true,
        'transaction_id' => $transactionId,
        'order_no'       => $orderNo,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    unset($_SESSION['used_tokens'][$token]);
    error_log('api_checkout error: ' . $e->getMessage());
    $checkoutResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
