<?php
/**
 * Analisis Performa Menu (Admin Only) - SRS-F10 / Prompt 7.2
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/report_queries.php';
requireRole(['admin']);

$pageTitle = 'Performa Menu - Kedai Paradise';

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to'] ?? date('Y-m-d');
$sort     = $_GET['sort'] ?? 'gross_sales';
$dir      = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$pdo = db();
$perfRows = getMenuPerformanceReport($pdo, $fromDate, $toDate, $sort, $dir);

$totQty  = 0;
$totBase = 0;
$totMod  = 0;
$totGross = 0;
$totCost = 0;
$totProfit = 0;

foreach ($perfRows as $r) {
    $totQty    += (int)$r['total_qty'];
    $totBase   += (int)$r['base_sales'];
    $totMod    += (int)$r['modifier_sales'];
    $totGross  += (int)$r['gross_sales'];
    $totCost   += (int)$r['total_cost'];
    $totProfit += (int)$r['gross_profit'];
}

function sortLinkPerf(string $col, string $label, string $currentSort, string $currentDir, string $from, string $to, string $align = 'left'): string {
    $nextDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = '';
    if ($currentSort === $col) {
        $arrow = $currentDir === 'asc' ? ' ▲' : ' ▼';
    }
    $url = 'menu_performance.php?' . http_build_query(['from' => $from, 'to' => $to, 'sort' => $col, 'dir' => $nextDir]);
    $alignClass = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : 'text-left');
    return '<th class="p-3 ' . $alignClass . '"><a href="' . e($url) . '" class="hover:text-[#1B4D3E] font-semibold">' . e($label) . $arrow . '</a></th>';
}

include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#1B4D3E]">Performa Penjualan Menu</h1>
            <p class="text-sm text-gray-600">Analisis produk terlaris, kontribusi omset, HPP, dan margin laba kotor per item.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="dir" value="<?= e($dir) ?>">
            <input type="date" name="from" value="<?= e($fromDate) ?>" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
            <span class="text-gray-500">s/d</span>
            <input type="date" name="to" value="<?= e($toDate) ?>" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
            <button type="submit" class="bg-[#1B4D3E] text-white px-4 py-1.5 rounded text-sm hover:bg-[#153d31]">Filter</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
            <h2 class="font-semibold text-gray-800">Daftar Menu & Kontribusi Pendapatan</h2>
            <div class="flex items-center gap-2">
                <a href="export_csv.php?<?= e(http_build_query(['report' => 'menu_performance', 'from' => $fromDate, 'to' => $toDate, 'sort' => $sort, 'dir' => $dir])) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded text-xs font-medium">Ekspor CSV</a>
                <button type="button" onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded text-xs font-medium">Cetak/PDF</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-600">
                        <?= sortLinkPerf('menu_name', 'Nama Menu', $sort, $dir, $fromDate, $toDate, 'left') ?>
                        <?= sortLinkPerf('category', 'Kategori', $sort, $dir, $fromDate, $toDate, 'left') ?>
                        <?= sortLinkPerf('total_qty', 'Terjual', $sort, $dir, $fromDate, $toDate, 'center') ?>
                        <?= sortLinkPerf('base_sales', 'Omset Dasar', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkPerf('modifier_sales', 'Omset Modifier', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkPerf('gross_sales', 'Total Omset', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkPerf('total_cost', 'Total HPP', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkPerf('gross_profit', 'Laba Kotor', $sort, $dir, $fromDate, $toDate, 'right') ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($perfRows)): ?>
                        <tr><td colspan="8" class="p-4 text-center text-gray-500">Belum ada data penjualan menu pada periode ini.</td></tr>
                    <?php else: foreach ($perfRows as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3 font-medium text-gray-800"><?= e($row['menu_name']) ?></td>
                            <td class="p-3 text-gray-600"><span class="bg-gray-100 px-2 py-0.5 rounded text-xs"><?= e($row['category']) ?></span></td>
                            <td class="p-3 text-center font-semibold"><?= (int)$row['total_qty'] ?></td>
                            <td class="p-3 text-right"><?= formatRupiah((int)$row['base_sales']) ?></td>
                            <td class="p-3 text-right"><?= formatRupiah((int)$row['modifier_sales']) ?></td>
                            <td class="p-3 text-right font-medium text-[#1B4D3E]"><?= formatRupiah((int)$row['gross_sales']) ?></td>
                            <td class="p-3 text-right text-gray-600"><?= formatRupiah((int)$row['total_cost']) ?></td>
                            <td class="p-3 text-right text-green-600 font-medium"><?= formatRupiah((int)$row['gross_profit']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold">
                        <td class="p-3" colspan="2">TOTAL</td>
                        <td class="p-3 text-center"><?= $totQty ?></td>
                        <td class="p-3 text-right"><?= formatRupiah($totBase) ?></td>
                        <td class="p-3 text-right"><?= formatRupiah($totMod) ?></td>
                        <td class="p-3 text-right text-[#1B4D3E]"><?= formatRupiah($totGross) ?></td>
                        <td class="p-3 text-right text-gray-700"><?= formatRupiah($totCost) ?></td>
                        <td class="p-3 text-right text-green-700"><?= formatRupiah($totProfit) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>