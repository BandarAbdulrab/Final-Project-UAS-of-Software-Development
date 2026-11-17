<?php
/**
 * Dasbor Utama (Admin & Kasir) - SRS-F11
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$pageTitle = 'Dasbor - Kedai Paradise';
$role = $_SESSION['role'] ?? 'cashier';
$today = date('Y-m-d');
$pdo = db();

// Kueri umum (berlaku untuk Admin dan Kasir)
$stmtToday = $pdo->prepare("SELECT 
    COUNT(*) as total_orders,
    COALESCE(SUM(total), 0) as total_revenue,
    COALESCE(SUM(gross_profit), 0) as total_gross_profit
    FROM transactions t 
    WHERE t.status <> 'void' AND t.order_date = :today");
$stmtToday->execute(['today' => $today]);
$todayStats = $stmtToday->fetch();

$stmtQueue = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'processing'");
$activeQueueCount = (int)$stmtQueue->fetchColumn();

// Data khusus Admin
if ($role === 'admin') {
    // 1. Pemasukan & Pengeluaran Manual Hari Ini
    $stmtFin = $pdo->prepare("SELECT type, COALESCE(SUM(amount), 0) as total 
                              FROM finance 
                              WHERE DATE(date) = :today 
                              GROUP BY type");
    $stmtFin->execute(['today' => $today]);
    $finToday = ['income' => 0, 'expense' => 0];
    foreach ($stmtFin->fetchAll() as $row) {
        $finToday[$row['type']] = (int)$row['total'];
    }
    $todayCashBalance = $todayStats['total_revenue'] + $finToday['income'] - $finToday['expense'];

    // 2. Status Tutup Kasir Hari Ini
    $stmtClose = $pdo->prepare("SELECT c.*, u.name as closer_name 
                                FROM cash_closings c 
                                JOIN users u ON c.user_id = u.user_id 
                                WHERE c.closing_date = :today");
    $stmtClose->execute(['today' => $today]);
    $todayClosing = $stmtClose->fetch();

    // 3. Top 5 Menu Terlaris Hari Ini
    $stmtTop = $pdo->prepare("SELECT m.name, SUM(o.quantity) as qty_sold, SUM(o.subtotal) as revenue
                              FROM orders o
                              JOIN transactions t ON o.transaction_id = t.transaction_id
                              JOIN menu m ON o.menu_id = m.menu_id
                              WHERE t.status <> 'void' AND t.order_date = :today
                              GROUP BY m.menu_id, m.name
                              ORDER BY qty_sold DESC, revenue DESC
                              LIMIT 5");
    $stmtTop->execute(['today' => $today]);
    $topItems = $stmtTop->fetchAll();

    // 4. Peringatan: Menu Habis & Pesanan Dibatalkan Hari Ini
    $soldOutItems = $pdo->query("SELECT name FROM menu WHERE status = 1 AND available = 0")->fetchAll(PDO::FETCH_COLUMN);
    
    $stmtVoid = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total), 0) as val 
                               FROM transactions t 
                               WHERE t.status = 'void' AND t.order_date = :today");
    $stmtVoid->execute(['today' => $today]);
    $voidToday = $stmtVoid->fetch();

    // 5. Grafik Batang Omset 7 Hari Terakhir
    $sevenDaysAgo = date('Y-m-d', strtotime('-6 days'));
    $stmtChart = $pdo->prepare("SELECT order_date, COALESCE(SUM(total), 0) as daily_rev 
                                FROM transactions t 
                                WHERE t.status <> 'void' AND t.order_date BETWEEN :start AND :end 
                                GROUP BY order_date");
    $stmtChart->execute(['start' => $sevenDaysAgo, 'end' => $today]);
    $revMap = [];
    foreach ($stmtChart->fetchAll() as $r) {
        $revMap[$r['order_date']] = (int)$r['daily_rev'];
    }
    
    $chartData = [];
    $maxRev = 1;
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $val = $revMap[$d] ?? 0;
        if ($val > $maxRev) $maxRev = $val;
        $chartData[] = [
            'label' => date('d/m', strtotime($d)),
            'value' => $val
        ];
    }
}

include __DIR__ . '/includes/layout_header.php';
?>

<?php if ($role === 'admin'): ?>
<!-- TAMPILAN DASBOR ADMIN -->
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#1B4D3E]">Dasbor Eksekutif</h1>
            <p class="text-sm text-gray-600">Ringkasan operasional dan keuangan hari ini (<?= date('d/m/Y') ?>).</p>
        </div>
        <!-- Tautan Cepat -->
        <div class="flex flex-wrap gap-2">
            <a href="pos.php" class="bg-[#1B4D3E] text-white px-3 py-1.5 rounded text-sm hover:bg-[#153d31]">Buka Kasir</a>
            <a href="queue.php" class="bg-blue-600 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700">Antrian (<?= $activeQueueCount ?>)</a>
            <a href="finance.php" class="bg-gray-700 text-white px-3 py-1.5 rounded text-sm hover:bg-gray-800">Keuangan</a>
            <a href="reports.php" class="bg-gray-100 text-gray-800 border border-gray-300 px-3 py-1.5 rounded text-sm hover:bg-gray-200">Laporan</a>
        </div>
    </div>

    <!-- Kartu KPI Utama -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 uppercase font-medium">Omset Hari Ini</p>
            <p class="text-lg font-bold text-[#1B4D3E] mt-1"><?= formatRupiah((int)$todayStats['total_revenue']) ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 uppercase font-medium">Laba Kotor Hari Ini</p>
            <p class="text-lg font-bold text-green-600 mt-1"><?= formatRupiah((int)$todayStats['total_gross_profit']) ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 uppercase font-medium">Total Transaksi</p>
            <p class="text-lg font-bold text-gray-800 mt-1"><?= (int)$todayStats['total_orders'] ?> Pesanan</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 uppercase font-medium">Antrian Aktif</p>
            <p class="text-lg font-bold text-blue-600 mt-1"><?= $activeQueueCount ?> Diproses</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 uppercase font-medium">Saldo Kas Hari Ini</p>
            <p class="text-lg font-bold text-indigo-600 mt-1"><?= formatRupiah($todayCashBalance) ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 uppercase font-medium">Kas Laci Terhitung</p>
            <p class="text-lg font-bold text-gray-800 mt-1"><?= $todayClosing ? formatRupiah((int)$todayClosing['counted_cash']) : '-' ?></p>
        </div>
    </div>

    <!-- Baris Tengah: Grafik 7 Hari & Status Tutup Kasir -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Grafik Batang SVG/CSS 7 Hari -->
        <div class="lg:col-span-2 bg-white p-5 rounded-lg shadow-sm border border-gray-200">
            <h2 class="font-semibold text-gray-800 mb-4">Tren Omset 7 Hari Terakhir</h2>
            <div class="flex items-end justify-between h-44 pt-6 pb-2 px-2 border-b border-gray-200 gap-2">
                <?php foreach ($chartData as $bar): 
                    $heightPct = round(($bar['value'] / $maxRev) * 100);
                ?>
                <div class="flex flex-col items-center flex-1 h-full justify-end group">
                    <span class="text-[10px] text-gray-500 mb-1 opacity-0 group-hover:opacity-100 transition"><?= formatRupiah($bar['value']) ?></span>
                    <div class="w-full max-w-[40px] bg-[#1B4D3E] rounded-t transition-all hover:bg-[#276b56]" style="height: <?= max($heightPct, 4) ?>%;"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-between px-2 pt-2 text-xs text-gray-500">
                <?php foreach ($chartData as $bar): ?>
                    <div class="flex-1 text-center"><?= e($bar['label']) ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Status Tutup Kasir & Peringatan -->
        <div class="space-y-4">
            <!-- Kartu Tutup Kasir -->
            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                <h2 class="font-semibold text-gray-800 mb-2">Status Tutup Kasir Hari Ini</h2>
                <?php if ($todayClosing): ?>
                    <div class="p-3 bg-green-50 border border-green-200 rounded text-sm text-green-800 space-y-1">
                        <p class="font-bold">SUDAH DITUTUP</p>
                        <p class="text-xs">Oleh: <?= e($todayClosing['closer_name']) ?></p>
                        <p class="text-xs">Selisih: <?= formatRupiah((int)$todayClosing['difference']) ?></p>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded text-sm text-amber-800 space-y-2">
                        <p class="font-bold">BELUM DITUTUP</p>
                        <p class="text-xs">Laci kas belum diaudit untuk hari ini.</p>
                        <a href="cash_close.php" class="inline-block bg-amber-600 text-white text-xs px-3 py-1.5 rounded hover:bg-amber-700">Lakukan Tutup Kasir</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Kartu Peringatan -->
            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200 space-y-3">
                <h2 class="font-semibold text-gray-800">Peringatan Operasional</h2>
                <div class="text-sm">
                    <span class="font-medium text-gray-700">Menu Habis:</span>
                    <?php if (empty($soldOutItems)): ?>
                        <span class="text-green-600 text-xs">Semua menu tersedia</span>
                    <?php else: ?>
                        <span class="text-red-600 text-xs font-medium"><?= e(implode(', ', $soldOutItems)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="text-sm">
                    <span class="font-medium text-gray-700">Pesanan Dibatalkan Hari Ini:</span>
                    <span class="text-xs <?= $voidToday['cnt'] > 0 ? 'text-red-600 font-bold' : 'text-gray-500' ?>">
                        <?= (int)$voidToday['cnt'] ?> pesanan (<?= formatRupiah((int)$voidToday['val']) ?>)
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 5 Menu Terlaris -->
    <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
        <h2 class="font-semibold text-gray-800 mb-3">5 Menu Terlaris Hari Ini</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-600">
                        <th class="p-2.5">Nama Menu</th>
                        <th class="p-2.5 text-center">Porsi Terjual</th>
                        <th class="p-2.5 text-right">Kontribusi Omset</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($topItems)): ?>
                        <tr><td colspan="3" class="p-3 text-center text-gray-500">Belum ada penjualan hari ini.</td></tr>
                    <?php else: foreach ($topItems as $item): ?>
                        <tr>
                            <td class="p-2.5 font-medium"><?= e($item['name']) ?></td>
                            <td class="p-2.5 text-center"><?= (int)$item['qty_sold'] ?></td>
                            <td class="p-2.5 text-right text-[#1B4D3E] font-medium"><?= formatRupiah((int)$item['revenue']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<!-- TAMPILAN DASBOR KASIR (KOMPAK - TANPA DATA KEUANGAN) -->
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[#1B4D3E]">Dasbor Kasir</h1>
        <p class="text-sm text-gray-600">Ringkasan operasional shift hari ini (<?= date('d/m/Y') ?>).</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 text-center">
            <p class="text-sm text-gray-500 font-medium">Total Pesanan Hari Ini</p>
            <p class="text-3xl font-bold text-[#1B4D3E] mt-2"><?= (int)$todayStats['total_orders'] ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 text-center">
            <p class="text-sm text-gray-500 font-medium">Pesanan Diproses (Antrian)</p>
            <p class="text-3xl font-bold text-blue-600 mt-2"><?= $activeQueueCount ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="pos.php" class="block text-center bg-[#1B4D3E] text-white font-semibold py-4 rounded-lg shadow hover:bg-[#153d31] transition">
            + Buat Pesanan Baru (Kasir)
        </a>
        <a href="queue.php" class="block text-center bg-blue-600 text-white font-semibold py-4 rounded-lg shadow hover:bg-blue-700 transition">
            Lihat Antrian Dapur
        </a>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>