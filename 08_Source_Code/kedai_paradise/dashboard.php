<?php
/**
 * Main Dashboard (Admin & Cashier) - SRS-F11 (Figma Design Match)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$pageTitle = 'Dasbor - Kedai Paradise';
$role = $_SESSION['role'] ?? 'cashier';
$today = date('Y-m-d');
$pdo = db();

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

if ($role === 'admin') {
    $stmtFin = $pdo->prepare("SELECT type, COALESCE(SUM(amount), 0) as total 
                              FROM finance 
                              WHERE DATE(date) = :today 
                              GROUP BY type");
    $stmtFin->execute(['today' => $today]);
    $finToday = ['income' => 0, 'expense' => 0];
    foreach ($stmtFin->fetchAll() as $row) {
        $finToday[$row['type']] = (int)$row['total'];
    }
    $todayCashBalance = (int)$todayStats['total_revenue'] + $finToday['income'] - $finToday['expense'];

    $stmtClose = $pdo->prepare("SELECT c.*, u.name as closer_name, u.username as closer_user
                                FROM cash_closings c 
                                JOIN users u ON c.user_id = u.user_id 
                                WHERE c.closing_date = :today");
    $stmtClose->execute(['today' => $today]);
    $todayClosing = $stmtClose->fetch();

    $stmtTop = $pdo->prepare("SELECT m.menu_id, m.name, m.category, SUM(o.quantity) as qty_sold, SUM(o.subtotal) as revenue
                              FROM orders o
                              JOIN transactions t ON o.transaction_id = t.transaction_id
                              JOIN menu m ON o.menu_id = m.menu_id
                              WHERE t.status <> 'void' AND t.order_date = :today
                              GROUP BY m.menu_id, m.name, m.category
                              ORDER BY qty_sold DESC, revenue DESC
                              LIMIT 5");
    $stmtTop->execute(['today' => $today]);
    $topItems = $stmtTop->fetchAll();

    $soldOutItems = $pdo->query("SELECT menu_id, name FROM menu WHERE status = 1 AND available = 0")->fetchAll();

    $stmtVoid = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total), 0) as val 
                               FROM transactions t 
                               WHERE t.status = 'void' AND t.order_date = :today");
    $stmtVoid->execute(['today' => $today]);
    $voidToday = $stmtVoid->fetch();

    $stmtLastVoid = $pdo->prepare("SELECT order_no, total, void_reason FROM transactions WHERE status = 'void' AND order_date = :today ORDER BY voided_at DESC LIMIT 1");
    $stmtLastVoid->execute(['today' => $today]);
    $lastVoidOrder = $stmtLastVoid->fetch();

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
            'date'    => $d,
            'label'   => date('d M', strtotime($d)),
            'value'   => $val,
            'isToday' => ($d === $today),
        ];
    }
}

include __DIR__ . '/includes/layout_header.php';
?>

<?php if ($role === 'admin'):
    $totalOrdersToday = (int)$todayStats['total_orders'];
    $avgOrderVal = $totalOrdersToday > 0 ? round((int)$todayStats['total_revenue'] / $totalOrdersToday) : 0;
    $issueCount = count($soldOutItems) + ((int)$voidToday['cnt'] > 0 ? 1 : 0);
?>
<!-- ADMIN EXECUTIVE DASHBOARD (Figma Slide 4) -->
<div class="space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#1B4D3E]">
                <span>Ikhtisar Restoran &amp; Finansial</span>
                <span class="text-gray-300">&bull;</span>
                <span class="bg-emerald-100 text-[#1B4D3E] px-2 py-0.5 rounded-full">Outlet Kandang Gampang</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#1E293B] mt-1">DASBOR EKSEKUTIF KEDAI PARADISE</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold bg-white border border-[#E6E4DD] px-3 py-2 rounded-xl text-gray-600 shadow-sm">
                &#128197; Hari Ini: <strong><?= date('d/m/Y') ?> WIB</strong>
            </span>
            <a href="pos.php" class="bg-[#1B4D3E] hover:bg-[#153d31] text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm transition">
                + Buka Kasir
            </a>
        </div>
    </div>

    <!-- 6 KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <span>Omset Hari Ini</span>
                <span class="w-6 h-6 rounded-lg bg-emerald-50 text-[#1B4D3E] flex items-center justify-center font-bold">Rp</span>
            </div>
            <p class="text-xl font-extrabold text-[#1E293B] mt-2"><?= formatRupiah((int)$todayStats['total_revenue']) ?></p>
            <p class="text-[11px] text-emerald-700 font-medium mt-1">Penjualan Kotor Sah</p>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <span>Laba Kotor Hari Ini</span>
                <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">&#9650;</span>
            </div>
            <p class="text-xl font-extrabold text-[#1B4D3E] mt-2"><?= formatRupiah((int)$todayStats['total_gross_profit']) ?></p>
            <p class="text-[11px] text-gray-500 mt-1">Setelah potong HPP</p>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <span>Total Transaksi</span>
                <span class="w-6 h-6 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center font-bold">#</span>
            </div>
            <p class="text-xl font-extrabold text-[#1E293B] mt-2"><?= $totalOrdersToday ?> Pesanan</p>
            <p class="text-[11px] text-gray-500 mt-1">Rata-rata <?= formatRupiah($avgOrderVal) ?></p>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <span>Antrian Aktif</span>
                <span class="w-6 h-6 rounded-lg bg-orange-50 text-orange-700 flex items-center justify-center font-bold">&#9201;</span>
            </div>
            <p class="text-xl font-extrabold text-[#9E3B1B] mt-2"><?= $activeQueueCount ?> Pesanan</p>
            <p class="text-[11px] text-gray-500 mt-1">Dapur Sedang Memasak</p>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <span>Saldo Kas Hari Ini</span>
                <span class="w-6 h-6 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center font-bold">&#128179;</span>
            </div>
            <p class="text-xl font-extrabold text-[#1E293B] mt-2"><?= formatRupiah($todayCashBalance) ?></p>
            <p class="text-[11px] text-gray-500 mt-1">Sistem POS Kasir</p>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <span>Kas Laci Terhitung</span>
                <span class="w-6 h-6 rounded-lg bg-gray-100 text-gray-700 flex items-center justify-center font-bold">&#128452;</span>
            </div>
            <p class="text-xl font-extrabold text-[#1E293B] mt-2"><?= $todayClosing ? formatRupiah((int)$todayClosing['counted_cash']) : '-' ?></p>
            <p class="text-[11px] text-gray-500 mt-1"><?= $todayClosing ? 'Termasuk Modal Awal' : 'Belum Tutup Kasir' ?></p>
        </div>
    </div>

    <!-- Middle Row: 7-Day Trend Chart & Cash Closing Status -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- 7-Day Revenue Bar Chart -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-[#E6E4DD] flex flex-col justify-between">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <div>
                    <h2 class="font-extrabold text-base text-[#1E293B] uppercase tracking-wide">Tren Omset 7 Hari Terakhir</h2>
                    <p class="text-xs text-gray-500">Periode <?= e($chartData[0]['label']) ?> &ndash; <?= e($chartData[6]['label']) ?> <?= date('Y') ?></p>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-500">
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-emerald-200 inline-block"></span> Hari Lalu</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-[#1B4D3E] inline-block"></span> Hari Ini (Aktif)</span>
                </div>
            </div>

            <div class="flex items-end justify-between h-52 pt-8 pb-2 px-3 border-b border-gray-100 gap-3">
                <?php foreach ($chartData as $bar):
                    $heightPct = round(($bar['value'] / $maxRev) * 100);
                    $shortK = $bar['value'] >= 1000 ? round($bar['value'] / 1000) . 'k' : (string)$bar['value'];
                ?>
                <div class="flex flex-col items-center flex-1 h-full justify-end group">
                    <span class="text-[11px] font-bold mb-1.5 <?= $bar['isToday'] ? 'text-[#1B4D3E]' : 'text-gray-500' ?>">
                        <?= $bar['value'] > 0 ? e($shortK) : '0' ?>
                    </span>
                    <div class="w-full max-w-[48px] rounded-t-xl transition-all <?= $bar['isToday'] ? 'bg-[#1B4D3E] shadow-md' : 'bg-emerald-200/75 hover:bg-emerald-300' ?>"
                         style="height: <?= max($heightPct, 6) ?>%;"
                         title="<?= e($bar['label']) ?>: <?= formatRupiah($bar['value']) ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-between px-3 pt-2.5 text-xs font-semibold text-gray-500">
                <?php foreach ($chartData as $bar): ?>
                    <div class="flex-1 text-center <?= $bar['isToday'] ? 'text-[#1B4D3E] font-bold' : '' ?>"><?= e($bar['label']) ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cash Closing Status Card -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#E6E4DD] flex flex-col justify-between space-y-4">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Status Tutup Kasir</span>
                    <?php if ($todayClosing): ?>
                        <span class="bg-emerald-100 text-[#1B4D3E] text-xs font-extrabold px-2.5 py-1 rounded-full">SUDAH DITUTUP</span>
                    <?php else: ?>
                        <span class="bg-amber-100 text-amber-800 text-xs font-extrabold px-2.5 py-1 rounded-full">BELUM DITUTUP</span>
                    <?php endif; ?>
                </div>

                <?php if ($todayClosing):
                    $diff = (int)$todayClosing['difference'];
                ?>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between items-center text-xs text-gray-500 bg-[#F8F7F4] p-3 rounded-xl">
                            <span>Ditutup Oleh: <strong class="text-gray-800"><?= e($todayClosing['closer_name']) ?></strong></span>
                            <span>Waktu: <strong><?= date('H:i', strtotime($todayClosing['created_at'])) ?> WIB</strong></span>
                        </div>
                        <div class="p-3.5 rounded-xl border <?= $diff === 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-red-50 border-red-200 text-red-900' ?>">
                            <div class="font-bold text-xs uppercase">
                                Selisih Fisik: <?= ($diff > 0 ? '+' : '') . formatRupiah($diff) ?>
                                <?= $diff === 0 ? '(Pas)' : ($diff < 0 ? '(Kurang)' : '(Lebih)') ?>
                            </div>
                            <div class="text-xs mt-1 text-gray-600 flex justify-between">
                                <span>Kas Sistem: <strong><?= formatRupiah((int)$todayClosing['expected_cash']) ?></strong></span>
                                <span>Kas Fisik: <strong><?= formatRupiah((int)$todayClosing['counted_cash']) ?></strong></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mt-4 p-4 bg-amber-50/70 border border-amber-200 rounded-xl text-xs text-amber-900 space-y-1.5 leading-relaxed">
                        <p class="font-bold text-sm">Rekonsiliasi laci belum dilakukan hari ini.</p>
                        <p>Pastikan uang fisik dihitung dan disimpan pada menu Tutup Kasir di akhir shift operasional.</p>
                    </div>
                <?php endif; ?>
            </div>

            <a href="cash_close.php" class="w-full block text-center bg-[#F8F7F4] hover:bg-gray-200/70 text-[#1E293B] border border-gray-300 font-bold text-xs py-3 rounded-xl transition">
                Buka Menu Tutup Kasir &rarr;
            </a>
        </div>
    </div>

    <!-- Bottom Row: Top 5 Best-Selling Items & Operational Alerts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Top 5 Menu Table -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="font-extrabold text-base text-[#1E293B] uppercase tracking-wide">Menu Terlaris Hari Ini (Top 5 Items)</h2>
                    <p class="text-xs text-gray-500">Paling banyak dipesan pelanggan sepanjang hari ini</p>
                </div>
                <a href="menu_performance.php" class="text-xs font-bold text-[#1B4D3E] hover:underline">Lihat Analisis &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-400">
                            <th class="py-2.5 px-3">Peringkat &amp; Nama Menu</th>
                            <th class="py-2.5 px-3 text-center">Porsi Terjual</th>
                            <th class="py-2.5 px-3 text-right">Kontribusi Omset</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($topItems)): ?>
                            <tr><td colspan="3" class="py-8 text-center text-gray-400 text-xs">Belum ada penjualan tercatat hari ini.</td></tr>
                        <?php else:
                            $totalPortions = 0;
                            $topRevenueSum = 0;
                            foreach ($topItems as $idx => $item):
                                $rank = $idx + 1;
                                $totalPortions += (int)$item['qty_sold'];
                                $topRevenueSum += (int)$item['revenue'];
                        ?>
                            <tr class="hover:bg-[#F8F7F4]/60">
                                <td class="py-3 px-3 flex items-center gap-3">
                                    <span class="w-7 h-7 rounded-lg font-extrabold text-xs flex items-center justify-center <?= $rank === 1 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' ?>">
                                        <?= $rank ?>
                                    </span>
                                    <div>
                                        <div class="font-bold text-[#1E293B]"><?= e($item['name']) ?></div>
                                        <div class="text-[11px] text-gray-400"><?= e($item['category']) ?></div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span class="bg-emerald-50 text-[#1B4D3E] font-bold text-xs px-2.5 py-1 rounded-full">
                                        <?= (int)$item['qty_sold'] ?> porsi
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right font-extrabold text-[#1B4D3E]">
                                    <?= formatRupiah((int)$item['revenue']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="bg-[#F8F7F4] text-xs font-bold text-gray-600">
                            <td class="py-2.5 px-3">Akumulasi Top Menu</td>
                            <td class="py-2.5 px-3 text-center"><?= $totalPortions ?> Porsi Terlayani</td>
                            <td class="py-2.5 px-3 text-right text-[#1B4D3E]"><?= formatRupiah($topRevenueSum) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Operational Alerts Card -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#E6E4DD] space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-extrabold text-base text-[#1E293B] uppercase tracking-wide">Peringatan Operasional</h2>
                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full <?= $issueCount > 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-800' ?>">
                    <?= $issueCount ?> Isu
                </span>
            </div>

            <!-- Sold Out Alert -->
            <div class="p-3.5 rounded-xl border <?= empty($soldOutItems) ? 'bg-emerald-50/50 border-emerald-200' : 'bg-amber-50/70 border-amber-200' ?> space-y-2">
                <div class="text-xs font-bold uppercase tracking-wider <?= empty($soldOutItems) ? 'text-emerald-800' : 'text-amber-900' ?>">
                    Inventaris Kosong / Habis
                </div>
                <?php if (empty($soldOutItems)): ?>
                    <p class="text-xs text-emerald-700">Seluruh menu aktif berstatus Tersedia.</p>
                <?php else: ?>
                    <?php foreach ($soldOutItems as $so): ?>
                        <div class="flex items-center justify-between gap-2 bg-white p-2.5 rounded-lg border border-amber-200/80">
                            <div>
                                <div class="text-xs font-bold text-gray-800">Menu Habis: <?= e($so['name']) ?></div>
                                <div class="text-[11px] text-gray-500">Kasir tidak dapat input item ini</div>
                            </div>
                            <button type="button" onclick="quickMarkAvailable(<?= (int)$so['menu_id'] ?>)"
                                    class="bg-emerald-50 hover:bg-emerald-100 text-[#1B4D3E] border border-emerald-200 text-[11px] font-bold px-2.5 py-1 rounded-lg whitespace-nowrap">
                                &#10004; Tandai Tersedia
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Void Alert -->
            <div class="p-3.5 rounded-xl border <?= (int)$voidToday['cnt'] > 0 ? 'bg-red-50/70 border-red-200' : 'bg-gray-50 border-gray-200' ?> space-y-1.5">
                <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider <?= (int)$voidToday['cnt'] > 0 ? 'text-red-800' : 'text-gray-500' ?>">
                    <span>Pembatalan Kasir</span>
                    <span><?= (int)$voidToday['cnt'] ?> Dibatalkan Hari Ini</span>
                </div>
                <?php if ($lastVoidOrder): ?>
                    <div class="bg-white p-2.5 rounded-lg border border-red-100 text-xs space-y-1">
                        <div class="flex justify-between font-bold text-gray-800">
                            <span>Pesanan #<?= str_pad((string)$lastVoidOrder['order_no'], 3, '0', STR_PAD_LEFT) ?></span>
                            <span class="text-red-600 line-through"><?= formatRupiah((int)$lastVoidOrder['total']) ?></span>
                        </div>
                        <p class="text-[11px] text-gray-500">Alasan: <?= e($lastVoidOrder['void_reason']) ?></p>
                        <a href="history.php?status=void" class="inline-block text-[11px] font-bold text-[#9E3B1B] hover:underline pt-0.5">Detail Transaksi &rarr;</a>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-gray-500">Tidak ada transaksi yang dibatalkan hari ini.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
async function quickMarkAvailable(menuId) {
    try {
        await apiFetch('api_toggle_availability.php', {
            method: 'POST',
            body: JSON.stringify({ menu_id: menuId, available: 1 })
        });
        location.reload();
    } catch (e) {
        alert(e.message || 'Gagal mengubah status menu.');
    }
}
</script>

<?php else: ?>
<!-- CASHIER COMPACT DASHBOARD (Figma Slide 1) -->
<div class="max-w-3xl mx-auto space-y-6 py-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-[#1B4D3E]">Ringkasan Shift Kasir</span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#1E293B]">DASBOR KASIR</h1>
        </div>
        <span class="text-xs font-semibold bg-white border border-[#E6E4DD] px-3.5 py-2 rounded-xl text-gray-600 shadow-sm">
            Hari Ini: <strong><?= date('d/m/Y') ?></strong>
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#E6E4DD] flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Total Pesanan Hari Ini</span>
            <div class="text-4xl font-extrabold text-[#1E293B] my-3"><?= (int)$todayStats['total_orders'] ?> <span class="text-lg font-bold text-gray-500">Pesanan</span></div>
            <a href="pos.php" class="w-full block text-center bg-[#9E3B1B] hover:bg-[#822F14] text-white font-bold text-sm py-3 rounded-xl shadow-sm transition">
                Buka Kasir &rarr;
            </a>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#E6E4DD] flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Pesanan Diproses</span>
            <div class="text-4xl font-extrabold text-[#1B4D3E] my-3"><?= $activeQueueCount ?> <span class="text-lg font-bold text-gray-500">Pesanan</span></div>
            <a href="queue.php" class="w-full block text-center bg-[#1B4D3E] hover:bg-[#153d31] text-white font-bold text-sm py-3 rounded-xl shadow-sm transition">
                Lihat Antrian Dapur &rarr;
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>