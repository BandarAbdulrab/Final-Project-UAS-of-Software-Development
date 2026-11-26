<?php
/**
 * Buku Kas / Ledger Keuangan Manual (Admin Only) - SRS-F14 (Figma Slides 11 & 12 Match)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/report_queries.php';
requireRole(['admin']);

$pageTitle = 'Buku Kas Keuangan Manual - Kedai Paradise';

$fromDate   = $_GET['from'] ?? date('Y-m-01');
$toDate     = $_GET['to'] ?? date('Y-m-d');
$filterType = $_GET['type'] ?? '';
$filterPool = $_GET['pool'] ?? '';
$filterCat  = trim($_GET['category'] ?? '');
$sort       = $_GET['sort'] ?? 'date';
$dir        = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$pdo = db();
$finRows = getFinanceReport($pdo, $fromDate, $toDate, $filterType, $filterPool, $filterCat, $sort, $dir);

$totalCashOut = 0;
$totalCashIn  = 0;
foreach ($finRows as $r) {
    if ($r['type'] === 'expense') {
        $totalCashOut += (int)$r['amount'];
    } else {
        $totalCashIn += (int)$r['amount'];
    }
}

$poolLabels = [
    'restock'   => 'Belanja Bahan',
    'operating' => 'Operasional',
    'profit'    => 'Laba',
];

function sortLinkFinance(string $col, string $label, string $currentSort, string $currentDir, array $baseParams, string $align = 'left'): string {
    $nextDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = $currentSort === $col ? ($currentDir === 'asc' ? ' &#9650;' : ' &#9660;') : '';
    $url = 'finance.php?' . http_build_query(array_merge($baseParams, ['sort' => $col, 'dir' => $nextDir]));
    $alignClass = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : 'text-left');
    return '<th class="py-3 px-2.5 ' . $alignClass . '"><a href="' . e($url) . '" class="hover:text-[#1B4D3E] font-bold whitespace-nowrap">' . e($label) . $arrow . '</a></th>';
}

$baseQuery = [
    'from'     => $fromDate,
    'to'       => $toDate,
    'type'     => $filterType,
    'pool'     => $filterPool,
    'category' => $filterCat,
];

$printPeriodLabel = date('d/m/Y', strtotime($fromDate)) . ' s/d ' . date('d/m/Y', strtotime($toDate));
include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-6">
    <!-- Top Header & Cash In/Out Summary Badges (Figma Slide 11) -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-[#1B4D3E]">Modul Pembukuan</span>
            <h1 class="text-2xl font-extrabold text-[#1E293B] uppercase">BUKU KAS KEUANGAN MANUAL (PENGELUARAN &amp; PEMASUKAN TOKO)</h1>
            <p class="text-xs text-gray-500">Pencatatan kas operasional harian non-penjualan untuk akurasi arus kas dapur dan toko.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="bg-red-50 border border-red-200 px-4 py-2 rounded-xl">
                <span class="text-[10px] font-bold uppercase text-red-700 block">Kas Keluar</span>
                <span class="text-base font-extrabold text-red-700">&bull; <?= formatRupiah($totalCashOut) ?></span>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 px-4 py-2 rounded-xl">
                <span class="text-[10px] font-bold uppercase text-[#1B4D3E] block">Kas Masuk</span>
                <span class="text-base font-extrabold text-[#1B4D3E]">&bull; <?= formatRupiah($totalCashIn) ?></span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
        <!-- Left 4 Columns: Record / Edit Form (hidden in print) -->
        <div class="xl:col-span-4 bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-6 space-y-4 no-print print:hidden">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div>
                    <h2 id="financeFormTitle" class="font-extrabold text-sm text-[#1E293B] uppercase">CATAT TRANSAKSI KEUANGAN</h2>
                    <p class="text-[11px] text-gray-400">Masukkan perincian mutasi kas manual toko</p>
                </div>
                <button type="button" id="btnResetFinanceForm" onclick="resetFinanceForm()" class="hidden text-xs text-red-600 font-bold hover:underline">Batal Ubah</button>
            </div>

            <form id="financeForm" onsubmit="submitFinance(event)" class="space-y-4" autocomplete="off">
                <input type="hidden" name="action" id="fin_action" value="create">
                <input type="hidden" name="finance_id" id="fin_id" value="">

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Tanggal &amp; Waktu <span class="text-red-600">*</span></label>
                    <input type="datetime-local" name="date" id="fin_date" value="<?= date('Y-m-d\TH:i') ?>" required
                           class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-semibold focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Jenis Mutasi Kas <span class="text-red-600">*</span></label>
                    <select name="type" id="fin_type" required
                            class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-bold text-[#1E293B] focus:bg-white">
                        <option value="expense">&uarr; Pengeluaran (Expense)</option>
                        <option value="income">&darr; Pemasukan (Income)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Pos Dana Target <span class="text-red-600">*</span></label>
                    <select name="pool" id="fin_pool" required
                            class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-semibold focus:bg-white">
                        <option value="restock">Belanja Bahan (Restock)</option>
                        <option value="operating">Operasional (Operating)</option>
                        <option value="profit">Laba (Profit)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Kategori <span class="text-red-600">*</span></label>
                    <input type="text" name="category" id="fin_category" maxlength="100" autocomplete="off"
                           placeholder="Contoh: Belanja bahan baku, Token Listrik" required list="catList"
                           class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-semibold focus:bg-white">
                    <datalist id="catList">
                        <option value="Belanja bahan baku">
                        <option value="Token Listrik & Gas LPG">
                        <option value="Gaji karyawan">
                        <option value="Sewa lapak">
                        <option value="Penjualan Minyak Bekas">
                    </datalist>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nominal (Rp) <span class="text-red-600">*</span></label>
                    <input type="number" name="amount" id="fin_amount" min="1" step="1" placeholder="250000" required
                           class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-base font-extrabold text-[#1E293B] focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Keterangan / Rincian</label>
                    <textarea name="description" id="fin_description" rows="2"
                              placeholder="Contoh: Ayam segar, beras, minyak goreng..."
                              class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2 text-sm focus:bg-white"></textarea>
                </div>

                <button type="submit" id="fin_submit_btn"
                        class="w-full bg-[#9E3B1B] hover:bg-[#822F14] text-white font-extrabold py-3.5 rounded-xl shadow-sm transition uppercase tracking-wider text-xs">
                    &#128190; SIMPAN CATATAN KEUANGAN
                </button>
            </form>
        </div>

        <!-- Right 8 Columns: Filter & Ledger Table (expands full width in print) -->
        <div class="xl:col-span-8 print-full bg-white rounded-2xl shadow-sm border border-[#E6E4DD] overflow-hidden">
            <div class="p-5 border-b border-gray-100 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-extrabold text-base text-[#1E293B] uppercase">DAFTAR CATATAN TRANSAKSI</h2>
                        <p class="text-xs text-gray-400">Data buku kas manual periode aktif</p>
                    </div>
                    <div class="flex items-center gap-2 print:hidden">
                        <a href="export_csv.php?<?= e(http_build_query(array_merge($baseQuery, ['report' => 'finance', 'sort' => $sort, 'dir' => $dir]))) ?>"
                           class="bg-[#F8F7F4] hover:bg-gray-200 text-gray-700 border border-gray-300 px-3 py-1.5 rounded-xl text-xs font-bold">
                            &darr; Ekspor CSV
                        </a>
                        <button type="button" onclick="window.print()"
                                class="bg-[#F8F7F4] hover:bg-gray-200 text-gray-700 border border-gray-300 px-3 py-1.5 rounded-xl text-xs font-bold">
                            &#128424; Cetak/PDF
                        </button>
                    </div>
                </div>

                <form method="GET" class="flex flex-wrap items-center gap-2 pt-1 print:hidden" autocomplete="off">
                    <input type="hidden" name="sort" value="<?= e($sort) ?>">
                    <input type="hidden" name="dir" value="<?= e($dir) ?>">
                    <input type="date" name="from" value="<?= e($fromDate) ?>" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-2.5 py-1.5 text-xs font-semibold">
                    <span class="text-xs text-gray-400">s/d</span>
                    <input type="date" name="to" value="<?= e($toDate) ?>" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-2.5 py-1.5 text-xs font-semibold">
                    <select name="type" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-2.5 py-1.5 text-xs font-semibold">
                        <option value="">Semua Jenis</option>
                        <option value="expense" <?= $filterType === 'expense' ? 'selected' : '' ?>>Pengeluaran</option>
                        <option value="income" <?= $filterType === 'income' ? 'selected' : '' ?>>Pemasukan</option>
                    </select>
                    <select name="pool" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-2.5 py-1.5 text-xs font-semibold">
                        <option value="">Semua Pos Dana</option>
                        <option value="restock" <?= $filterPool === 'restock' ? 'selected' : '' ?>>Belanja Bahan</option>
                        <option value="operating" <?= $filterPool === 'operating' ? 'selected' : '' ?>>Operasional</option>
                        <option value="profit" <?= $filterPool === 'profit' ? 'selected' : '' ?>>Laba</option>
                    </select>
                    <input type="text" name="category" value="<?= e($filterCat) ?>" autocomplete="off" placeholder="Cari kategori..."
                           class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-2.5 py-1.5 text-xs">
                    <button type="submit" class="bg-[#1B4D3E] text-white px-3.5 py-1.5 rounded-xl text-xs font-bold hover:bg-[#153d31]">Saring</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-[#F8F7F4] border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            <?= sortLinkFinance('date', 'Tanggal/Waktu', $sort, $dir, $baseQuery, 'left') ?>
                            <?= sortLinkFinance('type', 'Jenis', $sort, $dir, $baseQuery, 'left') ?>
                            <?= sortLinkFinance('category', 'Kategori', $sort, $dir, $baseQuery, 'left') ?>
                            <?= sortLinkFinance('pool', 'Pos Dana', $sort, $dir, $baseQuery, 'left') ?>
                            <?= sortLinkFinance('amount', 'Nominal', $sort, $dir, $baseQuery, 'right') ?>
                            <?= sortLinkFinance('recorder_name', 'User', $sort, $dir, $baseQuery, 'left') ?>
                            <th class="py-3 px-2.5 text-center print:hidden">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($finRows)): ?>
                            <tr><td colspan="7" class="p-8 text-center text-gray-400">Belum ada catatan keuangan manual pada periode ini.</td></tr>
                        <?php else: foreach ($finRows as $row):
                            $dtLocal = date('Y-m-d\TH:i', strtotime($row['date']));
                            $rowJson = htmlspecialchars(json_encode([
                                'finance_id'  => (int)$row['finance_id'],
                                'date'        => $dtLocal,
                                'date_wib'    => formatDateTimeWib($row['date']),
                                'type'        => $row['type'],
                                'pool'        => $row['pool'],
                                'pool_label'  => $poolLabels[$row['pool']] ?? $row['pool'],
                                'category'    => $row['category'],
                                'amount'      => (int)$row['amount'],
                                'amount_fmt'  => formatRupiah((int)$row['amount']),
                                'description' => $row['description'] ?? '',
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr class="hover:bg-[#F8F7F4]/60">
                                <td class="py-3 px-2.5 text-xs text-gray-600 whitespace-nowrap">
                                    <div class="font-bold text-gray-800"><?= e(date('d/m/Y', strtotime($row['date']))) ?></div>
                                    <div class="text-[11px] text-gray-400"><?= e(date('H:i', strtotime($row['date']))) ?> WIB</div>
                                </td>
                                <td class="py-3 px-2.5 whitespace-nowrap">
                                    <?php if ($row['type'] === 'income'): ?>
                                        <span class="inline-block whitespace-nowrap bg-emerald-100 text-[#1B4D3E] px-2.5 py-1 rounded-full text-xs font-extrabold">&darr; Pemasukan</span>
                                    <?php else: ?>
                                        <span class="inline-block whitespace-nowrap bg-red-100 text-red-700 px-2.5 py-1 rounded-full text-xs font-extrabold">&uarr; Pengeluaran</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-2.5 min-w-[150px]">
                                    <div class="font-extrabold text-[#1E293B]"><?= e($row['category']) ?></div>
                                    <?php if (!empty($row['description'])): ?>
                                        <div class="text-xs text-gray-500"><?= e($row['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-2.5 whitespace-nowrap">
                                    <span class="inline-block whitespace-nowrap bg-[#F8F7F4] border border-gray-200 text-gray-700 px-2.5 py-1 rounded-lg text-xs font-bold">
                                        <?= e($poolLabels[$row['pool']] ?? $row['pool']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-2.5 text-right font-extrabold whitespace-nowrap <?= $row['type'] === 'income' ? 'text-[#1B4D3E]' : 'text-[#9E3B1B]' ?>">
                                    <?= $row['type'] === 'income' ? '+ ' : '- ' ?><?= formatRupiah((int)$row['amount']) ?>
                                </td>
                                <td class="py-3 px-2.5 text-xs font-semibold text-gray-600 whitespace-nowrap"><?= e($row['recorder_name']) ?></td>
                                <td class="py-3 px-2.5 text-center whitespace-nowrap space-x-1 print:hidden">
                                    <button type="button" onclick='openEditFinanceModal(<?= $rowJson ?>)'
                                            class="px-2.5 py-1 rounded-lg bg-[#F8F7F4] hover:bg-gray-200 text-gray-700 border border-gray-200 text-xs font-bold">
                                        Ubah
                                    </button>
                                    <button type="button" onclick='openDeleteFinanceModal(<?= $rowJson ?>)'
                                            class="px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-xs font-bold">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer Balance Bar -->
            <div class="bg-[#F8F7F4] border-t border-[#E6E4DD] px-5 py-3 flex flex-wrap items-center justify-between text-xs text-gray-600">
                <span>Menampilkan <strong><?= count($finRows) ?></strong> data transaksi buku kas</span>
                <div class="flex items-center gap-4 font-bold">
                    <span class="text-red-700">- <?= formatRupiah($totalCashOut) ?></span>
                    <span>|</span>
                    <span class="text-[#1B4D3E]">+ <?= formatRupiah($totalCashIn) ?></span>
                    <span>|</span>
                    <span class="text-[#1E293B]">Selisih: <?= ($totalCashIn - $totalCashOut >= 0 ? '+' : '-') . formatRupiah(abs($totalCashIn - $totalCashOut)) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal (Figma Slide 12) -->
<div id="deleteFinanceModal" class="fixed inset-0 bg-black/55 hidden items-center justify-center z-50 p-4">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-[#E6E4DD] p-6 space-y-4">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-red-100 text-red-700 flex items-center justify-center font-bold text-lg">&#128465;</span>
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-red-700 block">Konfirmasi Penghapusan</span>
                    <h3 class="text-lg font-extrabold text-[#1E293B]">Hapus Catatan Keuangan</h3>
                </div>
            </div>
            <button type="button" onclick="closeDeleteFinanceModal()" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>

        <div class="bg-[#F8F7F4] border border-[#E6E4DD] rounded-xl p-4 space-y-1">
            <div class="flex justify-between items-center">
                <span class="text-[11px] text-gray-400 font-semibold">Transaksi Terpilih</span>
                <span id="del_fin_badge" class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-red-100 text-red-700">PENGELUARAN</span>
            </div>
            <div class="flex justify-between items-baseline pt-1">
                <span id="del_fin_cat" class="font-extrabold text-base text-[#1E293B]">-</span>
                <span id="del_fin_amt" class="font-extrabold text-lg text-[#9E3B1B]">Rp0</span>
            </div>
            <div id="del_fin_meta" class="text-xs text-gray-500 pt-0.5"></div>
        </div>

        <p class="text-xs text-gray-700 font-medium leading-relaxed">
            &#9888; Hapus catatan ini? Saldo akan dihitung ulang secara otomatis.
        </p>

        <div class="bg-red-50 border border-red-200 rounded-xl p-3 text-xs text-red-800">
            &#9432; Tindakan ini akan mempengaruhi rekap neraca pos dana dan tidak dapat dibatalkan setelah disetujui.
        </div>

        <div class="grid grid-cols-2 gap-3 pt-2">
            <button type="button" onclick="closeDeleteFinanceModal()" class="py-2.5 rounded-xl bg-[#F8F7F4] hover:bg-gray-200 text-gray-700 font-bold text-xs">
                Batal
            </button>
            <button type="button" id="confirmDeleteFinBtn" class="py-2.5 rounded-xl bg-red-700 hover:bg-red-800 text-white font-extrabold text-xs uppercase tracking-wider">
                &#128465; Hapus
            </button>
        </div>
    </div>
</div>

<script>
let pendingDeleteId = null;

function resetFinanceForm() {
    document.getElementById('financeFormTitle').textContent = 'CATAT TRANSAKSI KEUANGAN';
    document.getElementById('fin_action').value = 'create';
    document.getElementById('fin_id').value = '';
    document.getElementById('fin_date').value = '<?= date('Y-m-d\TH:i') ?>';
    document.getElementById('fin_type').value = 'expense';
    document.getElementById('fin_pool').value = 'restock';
    document.getElementById('fin_category').value = '';
    document.getElementById('fin_amount').value = '';
    document.getElementById('fin_description').value = '';
    document.getElementById('fin_submit_btn').innerHTML = '&#128190; SIMPAN CATATAN KEUANGAN';
    document.getElementById('btnResetFinanceForm').classList.add('hidden');
}

function openEditFinanceModal(row) {
    document.getElementById('financeFormTitle').textContent = 'UBAH CATATAN KEUANGAN #' + row.finance_id;
    document.getElementById('fin_action').value = 'update';
    document.getElementById('fin_id').value = row.finance_id;
    document.getElementById('fin_date').value = row.date;
    document.getElementById('fin_type').value = row.type;
    document.getElementById('fin_pool').value = row.pool;
    document.getElementById('fin_category').value = row.category;
    document.getElementById('fin_amount').value = row.amount;
    document.getElementById('fin_description').value = row.description || '';
    document.getElementById('fin_submit_btn').innerHTML = '&#128190; PERBARUI CATATAN';
    document.getElementById('btnResetFinanceForm').classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function openDeleteFinanceModal(row) {
    pendingDeleteId = row.finance_id;
    document.getElementById('del_fin_cat').textContent = row.category;
    document.getElementById('del_fin_amt').textContent = row.amount_fmt;
    document.getElementById('del_fin_meta').textContent = row.date_wib + ' • Pos ' + row.pool_label;
    const badge = document.getElementById('del_fin_badge');
    badge.textContent = row.type === 'income' ? 'PEMASUKAN' : 'PENGELUARAN';
    badge.className = 'text-[10px] font-extrabold uppercase px-2 py-0.5 rounded ' + (row.type === 'income' ? 'bg-emerald-100 text-[#1B4D3E]' : 'bg-red-100 text-red-700');
    const m = document.getElementById('deleteFinanceModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeDeleteFinanceModal() {
    pendingDeleteId = null;
    const m = document.getElementById('deleteFinanceModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

document.getElementById('confirmDeleteFinBtn').addEventListener('click', async function () {
    if (!pendingDeleteId) return;
    try {
        const res = await apiFetch('api_finance.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'delete', finance_id: pendingDeleteId })
        });
        showToast(res.message, 'success');
        closeDeleteFinanceModal();
        setTimeout(() => location.reload(), 400);
    } catch (err) {
        showToast(err.message, 'error');
    }
});

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
        setTimeout(() => location.reload(), 500);
    } catch (err) {
        showToast(err.message, 'error');
    }
}
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
