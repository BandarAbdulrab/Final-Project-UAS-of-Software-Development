<?php
/**
 * Shared Report Query Layer & Whitelisted Sorting (BR-23, BR-24)
 */

function isValidDateYmd(string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)) {
        return false;
    }
    [$y,$m, $day] = array_map('intval', explode('-',$d));
    return checkdate($m, $day,$y);
}

function getSummaryReport(PDO $pdo, string$from, string $to): array {$sql = "SELECT 
                COUNT(*) AS total_orders,
                COALESCE(SUM(total), 0) AS total_revenue,
                COALESCE(SUM(total_cost), 0) AS total_cost,
                COALESCE(SUM(gross_profit), 0) AS total_gross_profit,
                COALESCE(SUM(alloc_restock), 0) AS total_alloc_restock,
                COALESCE(SUM(alloc_operating), 0) AS total_alloc_operating,
                COALESCE(SUM(alloc_profit), 0) AS total_net_profit,
                COALESCE(AVG(CASE WHEN status = 'completed' THEN duration_seconds END), 0) AS avg_duration
            FROM transactions t
            WHERE " . SALES_FILTER . " AND t.order_date BETWEEN :fromDate AND :toDate";
    $stmt =$pdo->prepare($sql);$stmt->execute(['fromDate' => $from, 'toDate' =>$to]);
    return $stmt->fetch() ?: [];
}

function getVoidSummary(PDO $pdo, string$from, string $to): array {$sql = "SELECT COUNT(*) AS void_count, COALESCE(SUM(total), 0) AS void_value
            FROM transactions t
            WHERE t.status = 'void' AND t.order_date BETWEEN :fromDate AND :toDate";
    $stmt =$pdo->prepare($sql);$stmt->execute(['fromDate' => $from, 'toDate' =>$to]);
    return $stmt->fetch() ?: ['void_count' => 0, 'void_value' => 0];
}

function getPoolBreakdown(PDO $pdo, string $from, string$to, array $kpi): array {$finSql = "SELECT pool, type, COALESCE(SUM(amount), 0) AS total_amount 
               FROM finance 
               WHERE DATE(date) BETWEEN :fromDate AND :toDate 
               GROUP BY pool, type";
    $stmtFin =$pdo->prepare($finSql);$stmtFin->execute(['fromDate' => $from, 'toDate' =>$to]);
    $finRows =$stmtFin->fetchAll();

    $manual = [ 'restock'   => ['income' => 0, 'expense' => 0], 'operating' => ['income' => 0, 'expense' => 0], 'profit'    => ['income' => 0, 'expense' => 0], ];$totalExpenses = 0;
    $totalManualIncome = 0;

    foreach ($finRows as$row) {
        $pool =$row['pool'];
        $type =$row['type'];
        $amt  = (int)($row['total_amount'] ?? 0);
        if (isset($manual[$pool][$type])) {
            $manual[$pool][$type] +=$amt;
        }
        if ($type === 'expense') {
            $totalExpenses +=$amt;
        } elseif ($type === 'income') {
            $totalManualIncome +=$amt;
        }
    }

    $allocated = [
        'restock'   => (int)($kpi['total_alloc_restock'] ?? 0),
        'operating' => (int)($kpi['total_alloc_operating'] ?? 0), 'profit'    => (int)($kpi['total_net_profit'] ?? 0),
    ];

    $pools = [];$sumPoolNet = 0;
    $labels = [
        'restock'   => 'Belanja Bahan (Restock)',
        'operating' => 'Operasional (Operating)',
        'profit'    => 'Laba Bersih (Profit)',
    ];

    foreach (['restock', 'operating', 'profit'] as $key) {$inc = $manual[$key]['income'];
        $exp =$manual[$key]['expense'];$net = $allocated[$key] + $inc -$exp;
        $sumPoolNet +=$net;
        $pools[$key] = [
            'key'           => $key,
            'label'         => $labels[$key],
            'allocated'     => $allocated[$key],
            'manual_income' => $inc,
            'expenses'      => $exp,
            'net_balance'   => $net,
        ];
    }

    $netCashBalance = (int)($kpi['total_revenue'] ?? 0) + $totalManualIncome -$totalExpenses;

    return [
        'pools'               => $pools,
        'total_expenses'      => $totalExpenses,
        'total_manual_income' => $totalManualIncome,
        'net_cash_balance'    => $netCashBalance,
        'sum_pool_net'        => $sumPoolNet,
        'reconciled'          => ($sumPoolNet ===$netCashBalance),
        'difference'          => $sumPoolNet -$netCashBalance,
    ];
}

function getPaymentMethodBreakdown(PDO $pdo, string$from, string $to): array {$sql = "SELECT 
                method,
                COUNT(*) AS orders_count,
                COALESCE(SUM(total), 0) AS amount
            FROM transactions t
            WHERE " . SALES_FILTER . " AND t.order_date BETWEEN :fromDate AND :toDate
            GROUP BY method";
    $stmt =$pdo->prepare($sql);$stmt->execute(['fromDate' => $from, 'toDate' =>$to]);
    $rows =$stmt->fetchAll();

    $result = [ 'cash' => ['label' => 'Tunai (Cash)', 'orders_count' => 0, 'amount' => 0, 'percentage' => 0.0], 'qris' => ['label' => 'QRIS', 'orders_count' => 0, 'amount' => 0, 'percentage' => 0.0], ];$grandTotal = 0;
    foreach ($rows as$r) {
        $m =$r['method'];
        if (isset($result[$m])) {
            $result[$m]['orders_count'] = (int)$r['orders_count'];$result[$m]['amount'] = (int)$r['amount'];
            $grandTotal += (int)$r['amount'];
        }
    }
    if ($grandTotal > 0) {
        foreach ($result as $k =>$v) {
            $result[$k]['percentage'] = round(($v['amount'] /$grandTotal) * 100, 1);
        }
    }
    return $result;
}

function getDailyBreakdown(PDO $pdo, string $from, string$to, string $sort = 'order_date', string$dir = 'desc'): array {
    $whitelist = [ 'order_date'   => 'order_date', 'orders_count' => 'orders_count', 'revenue'      => 'revenue', 'cost'         => 'cost', 'gross_profit' => 'gross_profit', 'net_profit'   => 'net_profit', 'avg_duration' => 'avg_duration', ];$orderBy = $whitelist[$sort] ?? 'order_date';
    $dirSql = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

    $sql = "SELECT 
                order_date,
                COUNT(*) AS orders_count,
                COALESCE(SUM(total), 0) AS revenue,
                COALESCE(SUM(total_cost), 0) AS cost,
                COALESCE(SUM(gross_profit), 0) AS gross_profit,
                COALESCE(SUM(alloc_profit), 0) AS net_profit,
                COALESCE(AVG(CASE WHEN status = 'completed' THEN duration_seconds END), 0) AS avg_duration
            FROM transactions t
            WHERE " . SALES_FILTER . " AND t.order_date BETWEEN :fromDate AND :toDate
            GROUP BY order_date
            ORDER BY {$orderBy} {$dirSql}, order_date DESC";
    $stmt =$pdo->prepare($sql);$stmt->execute(['fromDate' => $from, 'toDate' =>$to]);
    return $stmt->fetchAll();
}

function getMenuPerformanceReport(PDO $pdo, string $from, string$to, string $sort = 'gross_sales', string$dir = 'desc'): array {
    $whitelist = [ 'menu_name'      => 'm.name', 'category'       => 'm.category', 'total_qty'      => 'total_qty', 'base_sales'     => 'base_sales', 'modifier_sales' => 'modifier_sales', 'gross_sales'    => 'gross_sales', 'total_cost'     => 'total_cost', 'gross_profit'   => 'gross_profit', ];$orderBy = $whitelist[$sort] ?? 'gross_sales';
    $dirSql = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

    $sql = "SELECT 
                m.menu_id,
                m.name AS menu_name,
                m.category,
                COALESCE(SUM(CASE WHEN t.transaction_id IS NOT NULL THEN o.quantity ELSE 0 END), 0) AS total_qty,
                COALESCE(SUM(CASE WHEN t.transaction_id IS NOT NULL THEN o.price * o.quantity ELSE 0 END), 0) AS base_sales,
                COALESCE(SUM(CASE WHEN t.transaction_id IS NOT NULL THEN o.modifier_price * o.quantity ELSE 0 END), 0) AS modifier_sales,
                COALESCE(SUM(CASE WHEN t.transaction_id IS NOT NULL THEN o.subtotal ELSE 0 END), 0) AS gross_sales,
                COALESCE(SUM(CASE WHEN t.transaction_id IS NOT NULL THEN (o.cost_price + o.modifier_cost) * o.quantity ELSE 0 END), 0) AS total_cost,
                COALESCE(SUM(CASE WHEN t.transaction_id IS NOT NULL THEN o.subtotal - ((o.cost_price + o.modifier_cost) * o.quantity) ELSE 0 END), 0) AS gross_profit
            FROM menu m
            LEFT JOIN orders o ON m.menu_id = o.menu_id
            LEFT JOIN transactions t ON o.transaction_id = t.transaction_id 
                AND " . SALES_FILTER . " 
                AND t.order_date BETWEEN :fromDate AND :toDate
            GROUP BY m.menu_id, m.name, m.category
            ORDER BY {$orderBy} {$dirSql}, m.menu_id ASC";
    $stmt =$pdo->prepare($sql);$stmt->execute(['fromDate' => $from, 'toDate' =>$to]);
    return $stmt->fetchAll();
}

function getFinanceReport(PDO $pdo, string$from, string $to, string$type = '', string $pool = '', string$category = '', string $sort = 'date', string$dir = 'desc'): array {
    $whitelist = [ 'date'          => 'f.date', 'type'          => 'f.type', 'category'      => 'f.category', 'pool'          => 'f.pool', 'amount'        => 'f.amount', 'recorder_name' => 'u.name', ];$orderBy = $whitelist[$sort] ?? 'f.date';
    $dirSql = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

    $sql = "SELECT f.*, u.name AS recorder_name 
            FROM finance f 
            JOIN users u ON f.user_id = u.user_id 
            WHERE DATE(f.date) BETWEEN :fromDate AND :toDate";
    $params = ['fromDate' => $from, 'toDate' =>$to];

    if ($type !== '' && in_array($type, ['income', 'expense'], true)) {$sql .= " AND f.type = :type";
        $params['type'] =$type;
    }
    if ($pool !== '' && in_array($pool, ['restock', 'operating', 'profit'], true)) {$sql .= " AND f.pool = :pool";
        $params['pool'] =$pool;
    }
    if ($category !== '') {$sql .= " AND f.category LIKE :cat";
        $params['cat'] = '\%' .$category . '%';
    }

    $sql .= " ORDER BY {$orderBy} {$dirSql}, f.finance_id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}