<?php
/**
 * Laporan Keuangan & Ringkasan Penjualan (Admin Only) - SRS-F10 (Figma Slide 13 Match)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/report_queries.php';
requireRole(['admin']);

$pageTitle = 'Laporan Ringkasan Penjualan & Keuangan - Kedai Paradise';

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to'] ?? date('Y-m-d');
$sort     = $_GET['sort'] ?? 'order_date';
$dir      = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$pdo = db();

$kpi        = getSummaryReport($pdo, $fromDate, $toDate);
$voidInfo   = getVoidSummary($pdo, $fromDate, $toDate);
$poolData   = getPoolBreakdown($pdo, $fromDate, $toDate, $kpi);
$methodData = getPaymentMethodBreakdown($pdo, $fromDate, $toDate);
$dailyRows  = getDailyBreakdown($pdo, $fromDate, $toDate, $sort, $dir);

$rev = (int)($kpi['total_revenue'] ?? 0);
$gp  = (int)($kpi['total_gross_profit'] ?? 0);
$np  = (int)($kpi['total_net_profit'] ?? 0);
$gpMargin = $rev > 0 ? round(($gp / $rev) * 100, 1) : 0.0;
$npMargin = $rev > 0 ? round(($np / $rev) * 100, 1) : 0.0;

function formatDurationHuman($seconds): string {
    $sec = (int)round((float)($seconds ?? 0));
    if ($sec <= 0) {
        return '-';
    }
    if ($sec >= 3600) {
        $h = (int)floor($sec / 3600);
        $m = (int)floor(($sec % 3600) / 60);
        return sprintf('%dj %02dm', $h, $m);
    }
    return sprintf('%02d:%02d', (int)floor($sec / 60), $sec % 60);
}

function sortLinkReport(string $col, string $label, string $currentSort, string $currentDir, string $from, string $to, string $align = 'left'): string {
    $nextDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = $currentSort === $col ? ($currentDir === 'asc' ? ' &#9650;' : ' &#9660;') : ' &#8693;';
    $url = 'reports.php?' . http_build_query(['from' => $from, 'to' => $to, 'sort' => $col, 'dir' => $nextDir]);
    $alignClass = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : 'text-left');
    return '<th class="p-3.5 ' . $alignClass . '"><a href="' . e($url) . '" class="hover:text-[#1B4D3E] font-bold whitespace-nowrap">' . e($label) . '<span class="text-[10px] text-gray-400">' . $arrow . '</span></a></th>';
}

$printPeriodLabel = date('d/m/Y', strtotime($fromDate)) . ' s/d ' . date('d/m/Y', strtotime($toDate));
include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-6">
    <!-- Top Header & Filter Bar -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-[#1B4D3E]">Konsolidasi Finansial &amp; Kasir</span>
            <h1 class="text-2xl font-extrabold text-[#1E293B] uppercase">LAPORAN RINGKASAN PENJUALAN &amp; KEUANGAN</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2 print:hidden">
            <form method="GET" class="flex flex-wrap items-center gap-2 bg-white p-2 rounded-2xl border border-[#E6E4DD] shadow-sm">
                <input type="hidden" name="sort" value="<?= e($sort) ?>">
                <input type="hidden" name="dir" value="<?= e($dir) ?>">
                <span class="text-xs font-bold text-gray-500 pl-2">Periode:</span>
                <input type="date" name="from" value="<?= e($fromDate) ?>" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-2.5 py-1.5 text-xs font-semibold">
                <span class="text-xs text-gray-400">s/d</span>
                <input type="date" name="to" value="<?= e($toDate) ?>" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-2.5 py-1.5 text-xs font-semibold">
                <button type="submit" class="bg-[#9E3B1B] hover:bg-[#822F14] text-white px-4 py-1.5 rounded-xl text-xs font-extrabold transition">
                    Tampilkan
                </button>
            </form>
            <a href="export_csv.php?<?= e(http_build_query(['report' => 'summary', 'from' => $fromDate, 'to' => $toDate, 'sort' => $sort, 'dir' => $dir])) ?>"
               class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3.5 py-2.5 rounded-xl text-xs font-bold shadow-sm">
                &darr; Ekspor CSV
            </a>
            <button type="button" onclick="window.print()"
               class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3.5 py-2.5 rounded-xl text-xs font-bold shadow-sm">
                &#128424; Cetak/PDF
            </button>
        </div>
    </div>

    <!-- 6 Executive KPI Cards (Figma Slide 13) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Omset</span>
            <p class="text-xl font-extrabold text-[#1E293B] mt-1"><?= formatRupiah($rev) ?></p>
            <p class="text-[11px] text-gray-500 mt-1"><?= (int)$kpi['total_orders'] ?> Pesanan Sah</p>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Laba Kotor</span>
            <p class="text-xl font-extrabold text-[#1B4D3E] mt-1"><?= formatRupiah($gp) ?></p>
            <p class="text-[11px] text-emerald-700 font-medium mt-1">Marjin Kotor <?= number_format($gpMargin, 1, ',', '.') ?>%</p>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Laba Bersih</span>
            <p class="text-xl font-extrabold text-[#1B4D3E] mt-1"><?= formatRupiah($np) ?></p>
            <p class="text-[11px] text-emerald-700 font-medium mt-1">Marjin Bersih <?= number_format($npMargin, 1, ',', '.') ?>%</p>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Pengeluaran</span>
            <p class="text-xl font-extrabold text-[#9E3B1B] mt-1"><?= formatRupiah((int)$poolData['total_expenses']) ?></p>
            <p class="text-[11px] text-gray-500 mt-1">Pemasukan Manual: +<?= formatRupiah((int)$poolData['total_manual_income']) ?></p>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Saldo Kas Bersih</span>
            <p class="text-xl font-extrabold text-[#1E293B] mt-1"><?= formatRupiah((int)$poolData['net_cash_balance']) ?></p>
            <p class="text-[11px] text-gray-500 mt-1">Fisik Kasir &amp; Bank</p>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Status Pembatalan</span>
            <p class="text-xl font-extrabold text-amber-800 mt-1"><?= (int)$voidInfo['void_count'] ?> Dibatalkan</p>
            <p class="text-[11px] text-red-600 font-medium mt-1"><?= formatRupiah((int)$voidInfo['void_value']) ?> (Voided)</p>
        </div>
    </div>

    <!-- Operational Metrics Summary Strip -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-4 flex flex-wrap items-center justify-between gap-4 text-xs">
        <div class="flex flex-wrap items-center gap-4">
            <span class="font-extrabold uppercase tracking-wider text-gray-500">Ringkasan Metrik Operasional:</span>
            <span>Volume: <strong><?= (int)$kpi['total_orders'] ?> Pesanan</strong></span>
            <span>&bull;</span>
            <span>Rata-rata Durasi Saji: <strong title="<?= round((float)($kpi['avg_duration'] ?? 0)) ?> detik"><?= e(formatDurationHuman($kpi['avg_duration'] ?? 0)) ?></strong></span>
        </div>
        <div class="flex flex-wrap items-center gap-4 font-bold">
            <span class="bg-emerald-50 text-[#1B4D3E] px-3 py-1 rounded-full">
                Tunai: <?= formatRupiah($methodData['cash']['amount']) ?> (<?= number_format($methodData['cash']['percentage'], 1, ',', '.') ?>%)
            </span>
            <span class="bg-orange-50 text-[#9E3B1B] px-3 py-1 rounded-full">
                QRIS: <?= formatRupiah($methodData['qris']['amount']) ?> (<?= number_format($methodData['qris']['percentage'], 1, ',', '.') ?>%)
            </span>
        </div>
    </div>

    <!-- 3-Pool Balance Sheet Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center">
            <div>
                <h2 class="font-extrabold text-base text-[#1E293B] uppercase">NERACA SALDO PER POS DANA (3-POOL)</h2>
                <p class="text-xs text-gray-400">Rekonsiliasi alokasi otomatis penjualan dan mutasi buku kas manual</p>
            </div>
            <?php if ($poolData['reconciled']): ?>
                <span class="bg-emerald-100 text-[#1B4D3E] text-xs font-extrabold px-3 py-1 rounded-full">&#10004; Saldo sesuai</span>
            <?php else: ?>
                <span class="bg-red-100 text-red-800 text-xs font-extrabold px-3 py-1 rounded-full">
                    Selisih: <?= formatRupiah((int)$poolData['difference']) ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-[#F8F7F4] border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                        <th class="p-3.5">Pos Dana</th>
                        <th class="p-3.5 text-right">Alokasi Penjualan</th>
                        <th class="p-3.5 text-right">Pemasukan Manual</th>
                        <th class="p-3.5 text-right">Pengeluaran Manual</th>
                        <th class="p-3.5 text-right">Saldo Akhir Pos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($poolData['pools'] as $p): ?>
                        <tr class="hover:bg-[#F8F7F4]/60">
                            <td class="p-3.5 font-extrabold text-[#1E293B]"><?= e($p['label']) ?></td>
                            <td class="p-3.5 text-right font-semibold"><?= formatRupiah($p['allocated']) ?></td>
                            <td class="p-3.5 text-right text-emerald-700 font-semibold">+<?= formatRupiah($p['manual_income']) ?></td>
                            <td class="p-3.5 text-right text-red-600 font-semibold">-<?= formatRupiah($p['expenses']) ?></td>
                            <td class="p-3.5 text-right font-extrabold text-[#1B4D3E]"><?= formatRupiah($p['net_balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-[#F8F7F4] border-t-2 border-[#E6E4DD] font-extrabold">
                        <td class="p-3.5">Total Rekonsiliasi</td>
                        <td class="p-3.5 text-right"><?= formatRupiah($rev) ?></td>
                        <td class="p-3.5 text-right text-emerald-700">+<?= formatRupiah($poolData['total_manual_income']) ?></td>
                        <td class="p-3.5 text-right text-red-700">-<?= formatRupiah($poolData['total_expenses']) ?></td>
                        <td class="p-3.5 text-right text-lg text-[#1B4D3E]"><?= formatRupiah($poolData['sum_pool_net']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Daily Breakdown Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex flex-wrap justify-between items-center gap-2">
            <div>
                <h2 class="font-extrabold text-base text-[#1E293B] uppercase">RINCIAN HARIAN</h2>
                <p class="text-xs text-gray-400">Klik judul kolom untuk menyortir urutan</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-[#F8F7F4] border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                        <?= sortLinkReport('order_date', 'Tanggal', $sort, $dir, $fromDate, $toDate, 'left') ?>
                        <?= sortLinkReport('orders_count', 'Pesanan', $sort, $dir, $fromDate, $toDate, 'center') ?>
                        <?= sortLinkReport('revenue', 'Omset Kotor', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkReport('cost', 'Total HPP', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkReport('gross_profit', 'Laba Kotor', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkReport('net_profit', 'Laba Bersih', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkReport('avg_duration', 'Rata-rata Durasi', $sort, $dir, $fromDate, $toDate, 'center') ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($dailyRows)): ?>
                        <tr><td colspan="7" class="p-8 text-center text-gray-400">Tidak ada data penjualan pada periode ini.</td></tr>
                    <?php else: foreach ($dailyRows as $row): ?>
                        <tr class="hover:bg-[#F8F7F4]/60">
                            <td class="p-3.5 font-extrabold text-[#1E293B]">
                                <?= e(date('d/m/Y', strtotime($row['order_date']))) ?>
                                <?php if ($row['order_date'] === date('Y-m-d')): ?>
                                    <span class="ml-1.5 text-[10px] bg-emerald-100 text-[#1B4D3E] px-2 py-0.5 rounded-full font-bold">Hari Ini</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 text-center font-bold"><?= (int)$row['orders_count'] ?></td>
                            <td class="p-3.5 text-right font-bold text-[#1E293B]"><?= formatRupiah((int)$row['revenue']) ?></td>
                            <td class="p-3.5 text-right text-gray-500"><?= formatRupiah((int)$row['cost']) ?></td>
                            <td class="p-3.5 text-right font-bold text-[#1B4D3E]"><?= formatRupiah((int)$row['gross_profit']) ?></td>
                            <td class="p-3.5 text-right font-extrabold text-emerald-700"><?= formatRupiah((int)$row['net_profit']) ?></td>
                            <td class="p-3.5 text-center font-mono text-xs text-gray-600" title="<?= round((float)($row['avg_duration'] ?? 0)) ?> detik">
                                <?= e(formatDurationHuman($row['avg_duration'] ?? 0)) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
