<?php
/**
 * Tutup Kasir / End-of-Day Cash Closing (SRS-F15 / Prompt 8.3)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$pageTitle = 'Tutup Kasir - Kedai Paradise';
$role = $_SESSION['role'] ?? 'cashier';
$userId = (int)($_SESSION['user_id'] ?? 1);

$today = date('Y-m-d');
$closingDate = $_GET['date'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $closingDate) || $closingDate > $today) {
    $closingDate = $today;
}

$pdo = db();

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role !== 'admin') {
        http_response_code(403);
        exit('Anda tidak memiliki akses ke fitur ini');
    }
    requireCsrf();

    $postDate    = $_POST['closing_date'] ?? $closingDate;
    $openingCash = filter_var($_POST['opening_cash'] ?? 0, FILTER_VALIDATE_INT);
    $cashOut     = filter_var($_POST['cash_out'] ?? 0, FILTER_VALIDATE_INT);
    $countedCash = filter_var($_POST['counted_cash'] ?? 0, FILTER_VALIDATE_INT);
    $note        = trim($_POST['note'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate) || $postDate > $today) {
        $errorMsg = 'Tanggal tutup kasir tidak boleh di masa depan.';
    } elseif ($openingCash === false || $openingCash < 0 || $cashOut === false || $cashOut < 0 || $countedCash === false || $countedCash < 0) {
        $errorMsg = 'Nominal uang harus berupa angka bulat >= 0.';
    } elseif (mb_strlen($note) > 255) {
        $errorMsg = 'Catatan maksimal 255 karakter.';
    } else {
        $closingDate = $postDate;
        $stmtSales = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM transactions WHERE method = 'cash' AND status <> 'void' AND order_date = :date");
        $stmtSales->execute(['date' => $closingDate]);
        $cashSales = (int)$stmtSales->fetchColumn();

        $expectedCash = $openingCash + $cashSales - $cashOut;
        $difference   = $countedCash - $expectedCash;

        try {
            $stmtSave = $pdo->prepare("INSERT INTO cash_closings (closing_date, user_id, opening_cash, cash_sales, cash_out, expected_cash, counted_cash, difference, note, created_at) 
                                       VALUES (:date, :uid, :op, :cs, :co, :exp, :cnt, :diff, :note, NOW())
                                       ON DUPLICATE KEY UPDATE user_id = :uid2, opening_cash = :op2, cash_sales = :cs2, cash_out = :co2, expected_cash = :exp2, counted_cash = :cnt2, difference = :diff2, note = :note2, created_at = NOW()");
            $stmtSave->execute([
                'date'  => $closingDate, 'uid' => $userId, 'op' => $openingCash, 'cs' => $cashSales, 'co' => $cashOut, 'exp' => $expectedCash, 'cnt' => $countedCash, 'diff' => $difference, 'note' => $note !== '' ? $note : null,
                'uid2' => $userId, 'op2' => $openingCash, 'cs2' => $cashSales, 'co2' => $cashOut, 'exp2' => $expectedCash, 'cnt2' => $countedCash, 'diff2' => $difference, 'note2' => $note !== '' ? $note : null,
            ]);
            $successMsg = "Audit tutup kasir tanggal $closingDate berhasil disimpan.";
        } catch (Exception $e) {
            $errorMsg = "Gagal menyimpan: " . $e->getMessage();
        }
    }
}

// Rekapitulasi penjualan tunai & QRIS (MENGECUALIKAN VOID!)
$stmtDay = $pdo->prepare("SELECT 
    COALESCE(SUM(CASE WHEN method = 'cash' AND status <> 'void' THEN total ELSE 0 END), 0) AS cash_sales,
    COALESCE(SUM(CASE WHEN method = 'qris' AND status <> 'void' THEN total ELSE 0 END), 0) AS qris_sales,
    COALESCE(SUM(CASE WHEN status = 'void' THEN 1 ELSE 0 END), 0) AS void_count,
    COALESCE(SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END), 0) AS processing_count
    FROM transactions WHERE order_date = :date");
$stmtDay->execute(['date' => $closingDate]);
$dayRec = $stmtDay->fetch();

$stmtExisting = $pdo->prepare("SELECT * FROM cash_closings WHERE closing_date = :date");
$stmtExisting->execute(['date' => $closingDate]);
$existingClose = $stmtExisting->fetch();

// Riwayat tutup kasir 30 hari terakhir beserta pengecekan perubahan penjualan tunai (stale check)
$historyClosings = $pdo->query("
    SELECT c.*, u.name AS closer_name,
           COALESCE((SELECT SUM(t.total) FROM transactions t WHERE t.order_date = c.closing_date AND t.method = 'cash' AND t.status <> 'void'), 0) AS live_cash_sales
    FROM cash_closings c
    JOIN users u ON c.user_id = u.user_id
    ORDER BY c.closing_date DESC
    LIMIT 30
")->fetchAll();

include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-6 max-w-5xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#1B4D3E]">Tutup Kasir (Cash Closing)</h1>
            <p class="text-sm text-gray-600">Audit harian laci uang tunai dan rekapitulasi pembayaran.</p>
        </div>
        <?php if ($role === 'admin'): ?>
        <form method="GET" class="flex items-center gap-2">
            <input type="date" name="date" max="<?= e($today) ?>" value="<?= e($closingDate) ?>" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
            <button type="submit" class="bg-[#1B4D3E] text-white px-3 py-1.5 rounded text-sm hover:bg-[#153d31]">Pilih Tanggal</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 p-3 rounded text-sm"><?= e($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded text-sm"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
        <?php if ((int)$dayRec['processing_count'] > 0): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded text-sm">
                Peringatan: Masih ada <strong><?= (int)$dayRec['processing_count'] ?> pesanan</strong> berstatus <em>Diproses</em> pada tanggal ini.
            </div>
        <?php endif; ?>

        <!-- Ringkasan Shift Tanggal Terpilih -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 uppercase font-medium">Penjualan Tunai (Non-Void)</p>
                <p class="text-xl font-bold text-[#1B4D3E] mt-1"><?= formatRupiah((int)$dayRec['cash_sales']) ?></p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 uppercase font-medium">Penjualan QRIS (Info)</p>
                <p class="text-xl font-bold text-blue-600 mt-1"><?= formatRupiah((int)$dayRec['qris_sales']) ?></p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 uppercase font-medium">Pesanan Dibatalkan (Void)</p>
                <p class="text-xl font-bold text-red-600 mt-1"><?= (int)$dayRec['void_count'] ?> Pesanan</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 uppercase font-medium">Pesanan Belum Selesai</p>
                <p class="text-xl font-bold <?= (int)$dayRec['processing_count'] > 0 ? 'text-amber-600' : 'text-gray-800' ?> mt-1"><?= (int)$dayRec['processing_count'] ?> Tiket</p>
            </div>
        </div>

        <!-- Form Tutup Kasir (Admin Only) -->
        <form method="POST" class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <input type="hidden" name="closing_date" value="<?= e($closingDate) ?>">
            <h2 class="font-semibold text-gray-800 border-b pb-2">Form Audit Laci Kas (<?= e($closingDate) ?>)</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Modal Awal Laci</label>
                    <input type="number" name="opening_cash" id="opening_cash" min="0" step="1" value="<?= $existingClose ? (int)$existingClose['opening_cash'] : 200000 ?>" oninput="calcExpected()" required class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Penjualan Tunai (Otomatis)</label>
                    <input type="text" id="cash_sales_display" value="<?= formatRupiah((int)$dayRec['cash_sales']) ?>" disabled class="w-full bg-gray-50 border rounded px-3 py-2 text-sm font-semibold text-gray-700">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Uang Keluar dari Laci</label>
                    <input type="number" name="cash_out" id="cash_out" min="0" step="1" value="<?= $existingClose ? (int)$existingClose['cash_out'] : 0 ?>" oninput="calcExpected()" required class="w-full border rounded px-3 py-2 text-sm">
                    <p class="text-[11px] text-gray-500 mt-1">Jika ini pengeluaran usaha, catat juga di menu Keuangan</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Kas Seharusnya (Expected)</label>
                    <input type="text" id="expected_display" disabled class="w-full bg-gray-50 border rounded px-3 py-2 text-sm font-bold text-blue-700">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Uang Terhitung (Counted)</label>
                    <input type="number" name="counted_cash" id="counted_cash" min="0" step="1" value="<?= $existingClose ? (int)$existingClose['counted_cash'] : 0 ?>" oninput="calcExpected()" required class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Selisih (Difference)</label>
                    <input type="text" id="diff_display" disabled class="w-full bg-gray-50 border rounded px-3 py-2 text-sm font-bold">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Catatan / Keterangan Selisih (Maks. 255 karakter)</label>
                <input type="text" name="note" maxlength="255" value="<?= e($existingClose['note'] ?? '') ?>" placeholder="Opsional..." class="w-full border rounded px-3 py-2 text-sm">
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-[#1B4D3E] text-white px-6 py-2 rounded text-sm font-semibold hover:bg-[#153d31] transition">Simpan Tutup Kasir</button>
            </div>
        </form>
    <?php endif; ?>

    <!-- Riwayat Tutup Kasir (30 Hari Terakhir) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200 font-semibold text-gray-800">Riwayat Audit Tutup Kasir (30 Hari Terakhir)</div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b text-gray-600">
                        <th class="p-3">Tanggal</th>
                        <th class="p-3 text-right">Modal Awal</th>
                        <th class="p-3 text-right">Tunai Sistem</th>
                        <th class="p-3 text-right">Uang Keluar</th>
                        <th class="p-3 text-right">Seharusnya</th>
                        <th class="p-3 text-right">Uang Terhitung</th>
                        <th class="p-3 text-right">Selisih</th>
                        <th class="p-3">Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($historyClosings)): ?>
                        <tr><td colspan="8" class="p-4 text-center text-gray-500">Belum ada riwayat tutup kasir.</td></tr>
                    <?php else: foreach ($historyClosings as $c):
                        $isStale = ((int)$c['cash_sales'] !== (int)$c['live_cash_sales']);
                        $diffVal = (int)$c['difference'];
                        $diffClass = $diffVal === 0 ? 'text-green-600' : ($diffVal > 0 ? 'text-amber-600' : 'text-red-600');
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3 font-medium">
                                <?= e($c['closing_date']) ?>
                                <?php if ($isStale): ?>
                                    <span class="ml-1 inline-block bg-amber-100 text-amber-800 text-[11px] font-semibold px-2 py-0.5 rounded">Perlu disimpan ulang</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-right"><?= formatRupiah((int)$c['opening_cash']) ?></td>
                            <td class="p-3 text-right"><?= formatRupiah((int)$c['cash_sales']) ?></td>
                            <td class="p-3 text-right"><?= formatRupiah((int)$c['cash_out']) ?></td>
                            <td class="p-3 text-right"><?= formatRupiah((int)$c['expected_cash']) ?></td>
                            <td class="p-3 text-right font-medium"><?= formatRupiah((int)$c['counted_cash']) ?></td>
                            <td class="p-3 text-right font-bold <?= $diffClass ?>">
                                <?= ($diffVal > 0 ? '+' : '') . formatRupiah($diffVal) ?>
                            </td>
                            <td class="p-3 text-xs text-gray-600"><?= e($c['closer_name']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($role === 'admin'): ?>
<script>
const cashSales = <?= (int)$dayRec['cash_sales'] ?>;
function calcExpected() {
    const opening = parseInt(document.getElementById('opening_cash').value, 10) || 0;
    const cashOut = parseInt(document.getElementById('cash_out').value, 10) || 0;
    const counted = parseInt(document.getElementById('counted_cash').value, 10) || 0;

    const expected = opening + cashSales - cashOut;
    const diff = counted - expected;

    document.getElementById('expected_display').value = 'Rp' + expected.toLocaleString('id-ID');

    const diffEl = document.getElementById('diff_display');
    diffEl.value = (diff > 0 ? '+Rp' : (diff < 0 ? '-Rp' : 'Rp')) + Math.abs(diff).toLocaleString('id-ID');
    if (diff === 0) {
        diffEl.className = "w-full bg-green-50 border border-green-200 rounded px-3 py-2 text-sm font-bold text-green-700";
    } else if (diff > 0) {
        diffEl.className = "w-full bg-amber-50 border border-amber-200 rounded px-3 py-2 text-sm font-bold text-amber-700";
    } else {
        diffEl.className = "w-full bg-red-50 border border-red-200 rounded px-3 py-2 text-sm font-bold text-red-700";
    }
}
window.addEventListener('DOMContentLoaded', calcExpected);
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>