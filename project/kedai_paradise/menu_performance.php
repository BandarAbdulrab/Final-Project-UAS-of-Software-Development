<?php
/**
 * Analisis Performa Menu (Admin Only) - SRS-F10 (Figma Slide 14 Match)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/report_queries.php';
requireRole(['admin']);

$pageTitle = 'Laporan Performa Penjualan Menu & Pilihan - Kedai Paradise';

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to'] ?? date('Y-m-d');
$sort     = $_GET['sort'] ?? 'gross_sales';
$dir      = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$pdo = db();
$perfRows = getMenuPerformanceReport($pdo, $fromDate, $toDate, $sort, $dir);

$totQty    = 0;
$totBase   = 0;
$totMod    = 0;
$totGross  = 0;
$totCost   = 0;
$totProfit = 0;

foreach ($perfRows as $r) {
    $totQty    += (int)$r['total_qty'];
    $totBase   += (int)$r['base_sales'];
    $totMod    += (int)$r['modifier_sales'];
    $totGross  += (int)$r['gross_sales'];
    $totCost   += (int)$r['total_cost'];
    $totProfit += (int)$r['gross_profit'];
}

$baseSharePct = $totGross > 0 ? round(($totBase / $totGross) * 100, 1) : 0.0;
$modSharePct  = $totGross > 0 ? round(($totMod / $totGross) * 100, 1) : 0.0;

function sortLinkPerf(string $col, string $label, string $currentSort, string $currentDir, string $from, string $to, string $align = 'left'): string {
    $nextDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = $currentSort === $col ? ($currentDir === 'asc' ? ' &#9650;' : ' &#9660;') : ' &#8693;';
    $url = 'menu_performance.php?' . http_build_query(['from' => $from, 'to' => $to, 'sort' => $col, 'dir' => $nextDir]);
    $alignClass = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : 'text-left');
    return '<th class="p-3.5 ' . $alignClass . '"><a href="' . e($url) . '" class="hover:text-[#1B4D3E] font-bold whitespace-nowrap">' . e($label) . '<span class="text-[10px] text-gray-400">' . $arrow . '</span></a></th>';
}

$printPeriodLabel = date('d/m/Y', strtotime($fromDate)) . ' s/d ' . date('d/m/Y', strtotime($toDate));
include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-6">
    <!-- Top Header & Filter -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-[#1B4D3E]">Kategori Produk &amp; Menu Utama</span>
            <h1 class="text-2xl font-extrabold text-[#1E293B] uppercase">LAPORAN PERFORMA PENJUALAN MENU &amp; PILIHAN</h1>
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
            <a href="export_csv.php?<?= e(http_build_query(['report' => 'menu_performance', 'from' => $fromDate, 'to' => $toDate, 'sort' => $sort, 'dir' => $dir])) ?>"
               class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3.5 py-2.5 rounded-xl text-xs font-bold shadow-sm">
                &darr; Ekspor CSV
            </a>
            <button type="button" onclick="window.print()"
               class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3.5 py-2.5 rounded-xl text-xs font-bold shadow-sm">
                &#128424; Cetak/PDF
            </button>
        </div>
    </div>

    <!-- 4 KPI Cards (Figma Slide 14) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Menu Terjual</span>
            <p class="text-2xl font-extrabold text-[#1E293B] mt-1"><?= $totQty ?> porsi</p>
            <span class="text-xs text-gray-500"><?= count($perfRows) ?> Item Menu Terdata</span>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Penjualan Dasar</span>
            <p class="text-2xl font-extrabold text-[#1E293B] mt-1"><?= formatRupiah($totBase) ?></p>
            <span class="text-xs text-emerald-700 font-medium"><?= number_format($baseSharePct, 1, ',', '.') ?>% dari omset</span>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Penjualan Pilihan (Modifier)</span>
            <p class="text-2xl font-extrabold text-[#9E3B1B] mt-1"><?= formatRupiah($totMod) ?></p>
            <span class="text-xs text-gray-500"><?= number_format($modSharePct, 1, ',', '.') ?>% ekstra add-on</span>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6E4DD]">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Laba Kotor</span>
            <p class="text-2xl font-extrabold text-[#1B4D3E] mt-1"><?= formatRupiah($totProfit) ?></p>
            <span class="text-xs text-emerald-700 font-medium">Total Omset: <?= formatRupiah($totGross) ?></span>
        </div>
    </div>

    <!-- Performance Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center">
            <h2 class="font-extrabold text-base text-[#1E293B] uppercase">DATA PENJUALAN ITEM MENU</h2>
            <span class="bg-[#F8F7F4] border border-gray-200 text-gray-600 text-xs font-bold px-3 py-1 rounded-full"><?= count($perfRows) ?> Item Terdata</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-[#F8F7F4] border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                        <?= sortLinkPerf('menu_name', 'Nama Menu', $sort, $dir, $fromDate, $toDate, 'left') ?>
                        <?= sortLinkPerf('category', 'Kategori', $sort, $dir, $fromDate, $toDate, 'left') ?>
                        <?= sortLinkPerf('total_qty', 'Terjual', $sort, $dir, $fromDate, $toDate, 'center') ?>
                        <?= sortLinkPerf('base_sales', 'Penjualan Dasar', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkPerf('modifier_sales', 'Penjualan Pilihan', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkPerf('gross_sales', 'Total Omset', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkPerf('total_cost', 'Total HPP', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkPerf('gross_profit', 'Laba Kotor', $sort, $dir, $fromDate, $toDate, 'right') ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($perfRows)): ?>
                        <tr><td colspan="8" class="p-8 text-center text-gray-400">Belum ada data penjualan menu pada periode ini.</td></tr>
                    <?php else: foreach ($perfRows as $row):
                        $gSales = (int)$row['gross_sales'];
                        $gProf  = (int)$row['gross_profit'];
                        $mPct   = $gSales > 0 ? round(($gProf / $gSales) * 100, 1) : 0.0;
                    ?>
                        <tr class="hover:bg-[#F8F7F4]/60">
                            <td class="p-3.5 font-extrabold text-[#1E293B]"><?= e($row['menu_name']) ?></td>
                            <td class="p-3.5"><span class="bg-[#F8F7F4] border border-gray-200 text-gray-700 px-2.5 py-0.5 rounded-full text-xs font-bold"><?= e($row['category']) ?></span></td>
                            <td class="p-3.5 text-center">
                                <span class="bg-emerald-50 text-[#1B4D3E] font-extrabold text-xs px-2.5 py-1 rounded-full"><?= (int)$row['total_qty'] ?> porsi</span>
                            </td>
                            <td class="p-3.5 text-right font-medium"><?= formatRupiah((int)$row['base_sales']) ?></td>
                            <td class="p-3.5 text-right text-[#9E3B1B] font-semibold"><?= formatRupiah((int)$row['modifier_sales']) ?></td>
                            <td class="p-3.5 text-right font-extrabold text-[#1E293B]"><?= formatRupiah($gSales) ?></td>
                            <td class="p-3.5 text-right text-gray-500"><?= formatRupiah((int)$row['total_cost']) ?></td>
                            <td class="p-3.5 text-right">
                                <div class="font-extrabold text-[#1B4D3E]"><?= formatRupiah($gProf) ?></div>
                                <div class="text-[10px] font-bold text-emerald-700"><?= number_format($mPct, 1, ',', '.') ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-[#F8F7F4] border-t-2 border-[#E6E4DD] font-extrabold">
                        <td class="p-3.5" colspan="2">TOTAL KESELURUHAN</td>
                        <td class="p-3.5 text-center"><?= $totQty ?> porsi</td>
                        <td class="p-3.5 text-right"><?= formatRupiah($totBase) ?></td>
                        <td class="p-3.5 text-right text-[#9E3B1B]"><?= formatRupiah($totMod) ?></td>
                        <td class="p-3.5 text-right text-[#1E293B]"><?= formatRupiah($totGross) ?></td>
                        <td class="p-3.5 text-right text-gray-700"><?= formatRupiah($totCost) ?></td>
                        <td class="p-3.5 text-right text-lg text-[#1B4D3E]"><?= formatRupiah($totProfit) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
