<?php
/**
 * Native Memory-Efficient CSV Streaming Engine (RFC 4180 & SRS-NF13 / Prompt 8.2)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/report_queries.php';

$report     = $_GET['report'] ?? '';
$fromDate   = $_GET['from'] ?? date('Y-m-01');
$toDate     = $_GET['to'] ?? date('Y-m-d');
$delimParam = $_GET['delimiter'] ?? 'comma';
$sort       = $_GET['sort'] ?? '';
$dir        = strtolower($_GET['dir'] ?? 'desc');
$role       = $_SESSION['role'] ?? 'cashier';

$allowedReports = ['summary', 'menu_performance', 'finance', 'history'];
$sortWhitelists = [
    'summary'          => ['', 'order_date', 'orders_count', 'revenue', 'cost', 'gross_profit', 'net_profit', 'avg_duration'],
    'menu_performance' => ['', 'menu_name', 'category', 'total_qty', 'base_sales', 'modifier_sales', 'gross_sales', 'total_cost', 'gross_profit'],
    'finance'          => ['', 'date', 'type', 'category', 'pool', 'amount', 'recorder_name'],
    'history'          => ['', 'order_no', 'date', 'table_no', 'method', 'total', 'status', 'duration_seconds', 'total_cost', 'gross_profit', 'alloc_profit', 'cashier'],
];

if (
    !in_array($report, $allowedReports, true) ||
    !in_array($delimParam, ['comma', 'semicolon'], true) ||
    !in_array($dir, ['asc', 'desc'], true) ||
    !isValidDateYmd($fromDate) ||
    !isValidDateYmd($toDate) ||
    $fromDate > $toDate ||
    !in_array($sort, $sortWhitelists[$report], true)
) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Export gagal. Periksa periode, urutan, dan pilihan pemisah, lalu coba lagi.');
}

if (in_array($report, ['summary', 'menu_performance', 'finance'], true) && $role !== 'admin') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Anda tidak memiliki akses ke fitur ini');
}

if ($report === 'history' && $role === 'cashier') {
    $fromDate = date('Y-m-d');
    $toDate   = date('Y-m-d');
}

$delimiter = $delimParam === 'semicolon' ? ';' : ',';
$pdo = db();

$fromCompact = str_replace('-', '', $fromDate);
$toCompact   = str_replace('-', '', $toDate);
$filename    = "kedai-paradise_{$report}_{$fromCompact}-{$toCompact}.csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

function sanitizeCsvCell($val) {
    if (is_int($val) || is_float($val)) {
        return $val;
    }
    if (is_string($val) && strlen($val) > 0) {
        if (in_array($val[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $val;
        }
    }
    return $val;
}

function writeCsvRow($out, array $row, string $delim): void {
    $sanitized = array_map('sanitizeCsvCell', $row);
    fputcsv($out, $sanitized, $delim, '"', '');
}

if ($report === 'summary') {
    $kpi        = getSummaryReport($pdo, $fromDate, $toDate);
    $voidInfo   = getVoidSummary($pdo, $fromDate, $toDate);
    $poolData   = getPoolBreakdown($pdo, $fromDate, $toDate, $kpi);
    $methodData = getPaymentMethodBreakdown($pdo, $fromDate, $toDate);
    $dailyRows  = getDailyBreakdown($pdo, $fromDate, $toDate, $sort ?: 'order_date', $dir);

    writeCsvRow($out, ['SUMMARY'], $delimiter);
    writeCsvRow($out, ['total_orders', 'gross_revenue', 'total_cost', 'gross_profit', 'net_profit', 'manual_income', 'expenses', 'net_cash_balance', 'avg_duration_seconds', 'voided_orders', 'voided_value'], $delimiter);
    writeCsvRow($out, [
        (int)$kpi['total_orders'],
        (int)$kpi['total_revenue'],
        (int)$kpi['total_cost'],
        (int)$kpi['total_gross_profit'],
        (int)$kpi['total_net_profit'],
        (int)$poolData['total_manual_income'],
        (int)$poolData['total_expenses'],
        (int)$poolData['net_cash_balance'],
        (int)round((float)($kpi['avg_duration'] ?? 0)),
        (int)$voidInfo['void_count'],
        (int)$voidInfo['void_value'],
    ], $delimiter);
    writeCsvRow($out, [], $delimiter);

    writeCsvRow($out, ['REVENUE_PER_METHOD'], $delimiter);
    writeCsvRow($out, ['method', 'orders_count', 'amount', 'percentage'], $delimiter);
    foreach ($methodData as $key => $m) {
        writeCsvRow($out, [$key, (int)$m['orders_count'], (int)$m['amount'], $m['percentage']], $delimiter);
    }
    writeCsvRow($out, [], $delimiter);

    writeCsvRow($out, ['POOL_BALANCE'], $delimiter);
    writeCsvRow($out, ['pool', 'allocated', 'manual_income', 'expenses', 'net_balance'], $delimiter);
    foreach ($poolData['pools'] as $key => $p) {
        writeCsvRow($out, [$key, (int)$p['allocated'], (int)$p['manual_income'], (int)$p['expenses'], (int)$p['net_balance']], $delimiter);
    }
    writeCsvRow($out, [], $delimiter);

    writeCsvRow($out, ['DAILY_BREAKDOWN'], $delimiter);
    writeCsvRow($out, ['order_date', 'orders_count', 'gross_revenue', 'total_cost', 'gross_profit', 'net_profit', 'avg_duration_seconds'], $delimiter);
    foreach ($dailyRows as $r) {
        writeCsvRow($out, [
            $r['order_date'],
            (int)$r['orders_count'],
            (int)$r['revenue'],
            (int)$r['cost'],
            (int)$r['gross_profit'],
            (int)$r['net_profit'],
            (int)round((float)($r['avg_duration'] ?? 0)),
        ], $delimiter);
    }

} elseif ($report === 'menu_performance') {
    $rows = getMenuPerformanceReport($pdo, $fromDate, $toDate, $sort ?: 'gross_sales', $dir);
    writeCsvRow($out, ['menu_name', 'quantity_sold', 'base_sales', 'modifier_sales', 'gross_sales', 'total_cost', 'gross_profit'], $delimiter);
    foreach ($rows as $r) {
        writeCsvRow($out, [
            $r['menu_name'],
            (int)$r['total_qty'],
            (int)$r['base_sales'],
            (int)$r['modifier_sales'],
            (int)$r['gross_sales'],
            (int)$r['total_cost'],
            (int)$r['gross_profit'],
        ], $delimiter);
    }

} elseif ($report === 'finance') {
    $filterType = $_GET['type'] ?? '';
    $filterPool = $_GET['pool'] ?? '';
    $filterCat  = trim($_GET['category'] ?? '');
    $rows = getFinanceReport($pdo, $fromDate, $toDate, $filterType, $filterPool, $filterCat, $sort ?: 'date', $dir);

    writeCsvRow($out, ['finance_id', 'date', 'type', 'category', 'pool', 'amount', 'description', 'recorded_by'], $delimiter);
    foreach ($rows as $r) {
        writeCsvRow($out, [
            (int)$r['finance_id'],
            date('d/m/Y H:i', strtotime($r['date'])),
            $r['type'],
            $r['category'],
            $r['pool'],
            (int)$r['amount'],
            $r['description'] ?? '',
            $r['recorder_name'],
        ], $delimiter);
    }

} elseif ($report === 'history') {
    $status = $_GET['status'] ?? '';
    $method = $_GET['method'] ?? '';
    $mode   = ($_GET['mode'] ?? 'summary') === 'lines' ? 'lines' : 'summary';

    $where = "t.order_date BETWEEN :fromDate AND :toDate";
    $params = ['fromDate' => $fromDate, 'toDate' => $toDate];
    if (in_array($status, ['processing', 'completed', 'void'], true)) {
        $where .= " AND t.status = :status";
        $params['status'] = $status;
    }
    if (in_array($method, ['cash', 'qris'], true)) {
        $where .= " AND t.method = :method";
        $params['method'] = $method;
    }

    $histSortMap = [
        'order_no'         => 't.order_no',
        'date'             => 't.date',
        'table_no'         => 't.table_no',
        'method'           => 't.method',
        'total'            => 't.total',
        'status'           => 't.status',
        'duration_seconds' => 't.duration_seconds',
        'total_cost'       => 't.total_cost',
        'gross_profit'     => 't.gross_profit',
        'alloc_profit'     => 't.alloc_profit',
        'cashier'          => 'u.name',
    ];
    $orderBy = $histSortMap[$sort] ?? 't.date';
    $dirSql  = $dir === 'asc' ? 'ASC' : 'DESC';

    if ($mode === 'summary') {
        $headers = ['order_no', 'date', 'table_no', 'method', 'total', 'paid', 'change_due', 'status', 'completed_at', 'duration_seconds', 'items', 'cashier'];
        if ($role === 'admin') {
            $headers = array_merge($headers, ['total_cost', 'gross_profit', 'alloc_restock', 'alloc_operating', 'alloc_profit', 'void_reason', 'voided_at', 'voided_by']);
        }
        writeCsvRow($out, $headers, $delimiter);

        $sql = "SELECT t.*, u.name AS cashier_name, vu.name AS voided_by_name
                FROM transactions t
                JOIN users u ON t.user_id = u.user_id
                LEFT JOIN users vu ON t.voided_by = vu.user_id
                WHERE {$where}
                ORDER BY {$orderBy} {$dirSql}, t.transaction_id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $itemStmt = $pdo->prepare("
            SELECT o.quantity, m.name AS menu_name,
                   GROUP_CONCAT(oim.modifier_name ORDER BY oim.order_modifier_id SEPARATOR ', ') AS mods
            FROM orders o
            JOIN menu m ON o.menu_id = m.menu_id
            LEFT JOIN order_item_modifiers oim ON o.order_id = oim.order_id
            WHERE o.transaction_id = :tid
            GROUP BY o.order_id
        ");

        while ($r = $stmt->fetch()) {
            $itemStmt->execute(['tid' => $r['transaction_id']]);
            $itemParts = [];
            foreach ($itemStmt->fetchAll() as $it) {
                $label = $it['quantity'] . 'x ' . $it['menu_name'];
                if (!empty($it['mods'])) {
                    $label .= ' [' . $it['mods'] . ']';
                }
                $itemParts[] = $label;
            }
            $rowOut = [
                (int)$r['order_no'],
                date('d/m/Y H:i', strtotime($r['date'])),
                $r['table_no'],
                $r['method'],
                (int)$r['total'],
                (int)$r['paid'],
                (int)$r['change_due'],
                $r['status'],
                !empty($r['completed_at']) ? date('d/m/Y H:i', strtotime($r['completed_at'])) : '',
                $r['duration_seconds'] !== null ? (int)$r['duration_seconds'] : '',
                implode('; ', $itemParts),
                $r['cashier_name'],
            ];
            if ($role === 'admin') {
                $rowOut = array_merge($rowOut, [
                    (int)$r['total_cost'],
                    (int)$r['gross_profit'],
                    (int)$r['alloc_restock'],
                    (int)$r['alloc_operating'],
                    (int)$r['alloc_profit'],
                    $r['void_reason'] ?? '',
                    !empty($r['voided_at']) ? date('d/m/Y H:i', strtotime($r['voided_at'])) : '',
                    $r['voided_by_name'] ?? '',
                ]);
            }
            writeCsvRow($out, $rowOut, $delimiter);
        }
    } else {
        $headers = ['order_no', 'date', 'status', 'menu_name', 'modifiers', 'quantity', 'unit_price', 'subtotal'];
        if ($role === 'admin') {
            $headers = array_merge($headers, ['unit_cost', 'line_cost']);
        }
        writeCsvRow($out, $headers, $delimiter);

        $sql = "SELECT t.order_no, t.date, t.status, o.order_id, o.quantity, o.price, o.cost_price, o.modifier_price, o.modifier_cost, o.subtotal, m.name AS menu_name,
                       GROUP_CONCAT(oim.modifier_name ORDER BY oim.order_modifier_id SEPARATOR ', ') AS mods
                FROM transactions t
                JOIN users u ON t.user_id = u.user_id
                JOIN orders o ON t.transaction_id = o.transaction_id
                JOIN menu m ON o.menu_id = m.menu_id
                LEFT JOIN order_item_modifiers oim ON o.order_id = oim.order_id
                WHERE {$where}
                GROUP BY o.order_id
                ORDER BY {$orderBy} {$dirSql}, t.transaction_id DESC, o.order_id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($r = $stmt->fetch()) {
            $unitPrice = (int)$r['price'] + (int)$r['modifier_price'];
            $unitCost  = (int)$r['cost_price'] + (int)$r['modifier_cost'];
            $lineCost  = $unitCost * (int)$r['quantity'];
            $rowOut = [
                (int)$r['order_no'],
                date('d/m/Y H:i', strtotime($r['date'])),
                $r['status'],
                $r['menu_name'],
                $r['mods'] ?? '',
                (int)$r['quantity'],
                $unitPrice,
                (int)$r['subtotal'],
            ];
            if ($role === 'admin') {
                $rowOut[] = $unitCost;
                $rowOut[] = $lineCost;
            }
            writeCsvRow($out, $rowOut, $delimiter);
        }
    }
}

fclose($out);
exit;