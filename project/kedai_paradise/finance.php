<?php
/**
 * Buku Kas / Ledger Keuangan Manual (Admin Only) - SRS-F14
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/report_queries.php';
requireRole(['admin']);

$pageTitle = 'Buku Kas & Keuangan - Kedai Paradise';

$fromDate   = $_GET['from'] ?? date('Y-m-01');
$toDate     = $_GET['to'] ?? date('Y-m-d');
$filterType = $_GET['type'] ?? '';
$filterPool = $_GET['pool'] ?? '';
$filterCat  = trim($_GET['category'] ?? '');
$sort       = $_GET['sort'] ?? 'date';
$dir        = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$pdo = db();
$finRows = getFinanceReport($pdo, $fromDate, $toDate, $filterType, $filterPool, $filterCat, $sort, $dir);

$poolLabels = [
    'restock'   => 'Belanja Bahan',
    'operating' => 'Operasional',
    'profit'    => 'Laba',
];

function sortLinkFinance(string $col, string $label, string $currentSort, string $currentDir, array $baseParams, string $align = 'left'): string {
    $nextDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = '';
    if ($currentSort === $col) {
        $arrow = $currentDir === 'asc' ? ' ▲' : ' ▼';
    }
    $url = 'finance.php?' . http_build_query(array_merge($baseParams, ['sort' => $col, 'dir' => $nextDir]));
    $alignClass = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : 'text-left');
    return '<th class="p-3 ' . $alignClass . '"><a href="' . e($url) . '" class="hover:text-[#1B4D3E] font-semibold">' . e($label) . $arrow . '</a></th>';
}

$baseQuery = [
    'from'     => $fromDate,
    'to'       => $toDate,
    'type'     => $filterType,
    'pool'     => $filterPool,
    'category' => $filterCat,
];

include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#1B4D3E]">Buku Kas & Keuangan Operasional</h1>
            <p class="text-sm text-gray-600">Catat pemasukan non-penjualan dan pengeluaran operasional bisnis.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="openFinanceModal()" class="bg-[#1B4D3E] text-white px-4 py-2 rounded text-sm hover:bg-[#153d31] transition">
                + Catat Transaksi Baru
            </button>
            <a href="export_csv.php?<?= e(http_build_query(array_merge($baseQuery, ['report' => 'finance', 'sort' => $sort, 'dir' => $dir]))) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded text-sm font-medium">Ekspor CSV</a>
            <button type="button" onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded text-sm font-medium">Cetak/PDF</button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="dir" value="<?= e($dir) ?>">
            <div class="flex items-center gap-1">
                <span class="text-xs text-gray-500 font-medium">Dari:</span>
                <input type="date" name="from" value="<?= e($fromDate) ?>" class="border border-gray-300 rounded px-2.5 py-1 text-sm">
            </div>
            <div class="flex items-center gap-1">
                <span class="text-xs text-gray-500 font-medium">s/d:</span>
                <input type="date" name="to" value="<?= e($toDate) ?>" class="border border-gray-300 rounded px-2.5 py-1 text-sm">
            </div>
            <div>
                <select name="type" class="border border-gray-300 rounded px-2.5 py-1 text-sm">
                    <option value="">Semua Jenis</option>
                    <option value="expense" <?= $filterType === 'expense' ? 'selected' : '' ?>>Pengeluaran</option>
                    <option value="income" <?= $filterType === 'income' ? 'selected' : '' ?>>Pemasukan</option>
                </select>
            </div>
            <div>
                <select name="pool" class="border border-gray-300 rounded px-2.5 py-1 text-sm">
                    <option value="">Semua Pos Dana</option>
                    <option value="restock" <?= $filterPool === 'restock' ? 'selected' : '' ?>>Belanja Bahan (Restock)</option>
                    <option value="operating" <?= $filterPool === 'operating' ? 'selected' : '' ?>>Operasional</option>
                    <option value="profit" <?= $filterPool === 'profit' ? 'selected' : '' ?>>Laba (Profit)</option>
                </select>
            </div>
            <div>
                <input type="text" name="category" value="<?= e($filterCat) ?>" placeholder="Cari kategori..." class="border border-gray-300 rounded px-2.5 py-1 text-sm">
            </div>
            <button type="submit" class="bg-gray-800 text-white px-4 py-1 rounded text-sm hover:bg-gray-900">Saring</button>
        </form>
    </div>

    <!-- Tabel Buku Kas -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-600">
                        <?= sortLinkFinance('date', 'Tanggal & Waktu', $sort, $dir, $baseQuery, 'left') ?>
                        <?= sortLinkFinance('type', 'Jenis', $sort, $dir, $baseQuery, 'left') ?>
                        <?= sortLinkFinance('category', 'Kategori', $sort, $dir, $baseQuery, 'left') ?>
                        <?= sortLinkFinance('pool', 'Pos Dana', $sort, $dir, $baseQuery, 'left') ?>
                        <?= sortLinkFinance('amount', 'Nominal', $sort, $dir, $baseQuery, 'right') ?>
                        <th class="p-3">Keterangan</th>
                        <?= sortLinkFinance('recorder_name', 'Dicatat Oleh', $sort, $dir, $baseQuery, 'left') ?>
                        <th class="p-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($finRows)): ?>
                        <tr><td colspan="8" class="p-6 text-center text-gray-500">Belum ada catatan keuangan manual pada periode ini.</td></tr>
                    <?php else: foreach ($finRows as $row):
                        $dtLocal = date('Y-m-d\TH:i', strtotime($row['date']));
                        $rowJson = htmlspecialchars(json_encode([
                            'finance_id'  => (int)$row['finance_id'],
                            'date'        => $dtLocal,
                            'type'        => $row['type'],
                            'pool'        => $row['pool'],
                            'category'    => $row['category'],
                            'amount'      => (int)$row['amount'],
                            'description' => $row['description'] ?? '',
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3 text-gray-600"><?= formatDateTimeWib($row['date']) ?></td>
                            <td class="p-3">
                                <?php if ($row['type'] === 'income'): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs font-semibold">Pemasukan</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-800 px-2 py-0.5 rounded text-xs font-semibold">Pengeluaran</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 font-medium text-gray-800"><?= e($row['category']) ?></td>
                            <td class="p-3">
                                <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-xs"><?= e($poolLabels[$row['pool']] ?? $row['pool']) ?></span>
                            </td>
                            <td class="p-3 text-right font-bold <?= $row['type'] === 'income' ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $row['type'] === 'income' ? '+' : '-' ?><?= formatRupiah((int)$row['amount']) ?>
                            </td>
                            <td class="p-3 text-gray-600 text-xs"><?= e($row['description'] ?: '-') ?></td>
                            <td class="p-3 text-gray-500 text-xs"><?= e($row['recorder_name']) ?></td>
                            <td class="p-3 text-center space-x-2">
                                <button type="button" onclick='openEditFinanceModal(<?= $rowJson ?>)' class="text-blue-600 hover:text-blue-800 text-xs font-medium">Ubah</button>
                                <button type="button" onclick="deleteFinance(<?= (int)$row['finance_id'] ?>)" class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah / Ubah Keuangan -->
<div id="financeModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white w-full max-w-md rounded-lg shadow-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h3 id="financeModalTitle" class="font-bold text-gray-800">Catat Keuangan Manual</h3>
            <button type="button" onclick="closeFinanceModal()" class="text-gray-400 hover:text-gray-600 font-bold">&times;</button>
        </div>
        <form id="financeForm" onsubmit="submitFinance(event)" class="p-4 space-y-4">
            <input type="hidden" name="action" id="fin_action" value="create">
            <input type="hidden" name="finance_id" id="fin_id" value="">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal & Waktu</label>
                <input type="datetime-local" name="date" id="fin_date" value="<?= date('Y-m-d\TH:i') ?>" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Jenis Transaksi</label>
                    <select name="type" id="fin_type" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                        <option value="expense">Pengeluaran</option>
                        <option value="income">Pemasukan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Pos Target Pool</label>
                    <select name="pool" id="fin_pool" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                        <option value="restock">Belanja Bahan (Restock)</option>
                        <option value="operating">Operasional</option>
                        <option value="profit">Laba (Profit)</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Kategori</label>
                <input type="text" name="category" id="fin_category" maxlength="100" placeholder="Contoh: Belanja bahan, Listrik, Sewa" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm" list="catList">
                <datalist id="catList">
                    <option value="Belanja bahan">
                    <option value="Listrik">
                    <option value="Gaji karyawan">
                    <option value="Sewa lapak">
                    <option value="Pemasukan lain">
                </datalist>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Nominal (Rupiah)</label>
                <input type="number" name="amount" id="fin_amount" min="1" step="1" placeholder="Contoh: 150000" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Keterangan (Opsional)</label>
                <textarea name="description" id="fin_description" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="Catatan tambahan..."></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-200">
                <button type="button" onclick="closeFinanceModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium">Batal</button>
                <button type="submit" class="bg-[#1B4D3E] hover:bg-[#153d31] text-white px-4 py-2 rounded text-sm font-medium">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openFinanceModal() {
    document.getElementById('financeModalTitle').textContent = 'Catat Keuangan Manual';
    document.getElementById('fin_action').value = 'create';
    document.getElementById('fin_id').value = '';
    document.getElementById('fin_date').value = '<?= date('Y-m-d\TH:i') ?>';
    document.getElementById('fin_type').value = 'expense';
    document.getElementById('fin_pool').value = 'restock';
    document.getElementById('fin_category').value = '';
    document.getElementById('fin_amount').value = '';
    document.getElementById('fin_description').value = '';
    document.getElementById('financeModal').classList.remove('hidden');
    document.getElementById('financeModal').classList.add('flex');
}

function openEditFinanceModal(row) {
    document.getElementById('financeModalTitle').textContent = 'Ubah Catatan Keuangan';
    document.getElementById('fin_action').value = 'update';
    document.getElementById('fin_id').value = row.finance_id;
    document.getElementById('fin_date').value = row.date;
    document.getElementById('fin_type').value = row.type;
    document.getElementById('fin_pool').value = row.pool;
    document.getElementById('fin_category').value = row.category;
    document.getElementById('fin_amount').value = row.amount;
    document.getElementById('fin_description').value = row.description || '';
    document.getElementById('financeModal').classList.remove('hidden');
    document.getElementById('financeModal').classList.add('flex');
}

function closeFinanceModal() {
    document.getElementById('financeModal').classList.add('hidden');
    document.getElementById('financeModal').classList.remove('flex');
}

async function submitFinance(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
        const res = await apiFetch('api_finance.php', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        showToast(res.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function deleteFinance(id) {
    if (!confirm("Hapus catatan ini? Saldo akan dihitung ulang")) return;
    try {
        const res = await apiFetch('api_finance.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'delete', finance_id: id })
        });
        showToast(res.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch (err) {
        showToast(err.message, 'error');
    }
}
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>