<?php
/**
 * Tutup Kasir / End-of-Day Cash Closing (SRS-F15) - Figma Slides 3 & 7 Design Match
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/cashclose_functions.php';

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

    if ($openingCash === false || $cashOut === false || $countedCash === false) {
        $errorMsg = 'Nominal uang harus berupa angka bulat >= 0.';
    } else {
        try {
            $saved = saveCashClosing($pdo, (string)$postDate, $userId, (int)$openingCash, (int)$cashOut, (int)$countedCash, $note, $role);
            $closingDate = $saved['closing_date'];
            $successMsg = "Laporan tutup kasir tanggal $closingDate berhasil disimpan.";
        } catch (Throwable $e) {
            $errorMsg = $e->getMessage();
        }
    }
}

$stmtDay = $pdo->prepare("SELECT 
    COALESCE(SUM(CASE WHEN method = 'cash' AND status <> 'void' THEN total ELSE 0 END), 0) AS cash_sales,
    COALESCE(SUM(CASE WHEN method = 'qris' AND status <> 'void' THEN total ELSE 0 END), 0) AS qris_sales,
    COALESCE(SUM(CASE WHEN status = 'void' THEN 1 ELSE 0 END), 0) AS void_count,
    COALESCE(SUM(CASE WHEN status = 'void' THEN total ELSE 0 END), 0) AS void_total,
    COALESCE(SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END), 0) AS processing_count
    FROM transactions WHERE order_date = :date");
$stmtDay->execute(['date' => $closingDate]);
$dayRec = $stmtDay->fetch();

$stmtExisting = $pdo->prepare("SELECT * FROM cash_closings WHERE closing_date = :date");
$stmtExisting->execute(['date' => $closingDate]);
$existingClose = $stmtExisting->fetch();

$historyClosings = $pdo->query("
    SELECT c.*, u.username AS closer_user, u.name AS closer_name,
           COALESCE((SELECT SUM(t.total) FROM transactions t WHERE t.order_date = c.closing_date AND t.method = 'cash' AND t.status <> 'void'), 0) AS live_cash_sales
    FROM cash_closings c
    JOIN users u ON c.user_id = u.user_id
    ORDER BY c.closing_date DESC
    LIMIT 30
")->fetchAll();

include __DIR__ . '/includes/layout_header.php';
?>

<?php if ($role === 'admin'): ?>
<!-- ADMIN VIEW: Side-by-Side Reconciliation & History (Figma Slide 7) -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

    <!-- Left 7 Columns: Drawer Reconciliation Form -->
    <div class="lg:col-span-7 bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-6 space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-orange-50 text-[#9E3B1B] flex items-center justify-center font-bold text-lg">&#128179;</span>
                <div>
                    <h1 class="text-xl font-extrabold text-[#1E293B] uppercase">TUTUP KASIR (REKONSILIASI LACI)</h1>
                    <p class="text-xs text-gray-500">Kalkulasi dan rekonsiliasi penerimaan tunai fisik terhadap catatan POS</p>
                </div>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3.5 rounded-xl text-sm font-medium">&#10004; <?= e($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 p-3.5 rounded-xl text-sm font-medium">&#9888; <?= e($errorMsg) ?></div>
        <?php endif; ?>
        <?php if ((int)$dayRec['processing_count'] > 0): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 p-3.5 rounded-xl text-xs">
                &#9888; Peringatan: Masih ada <strong><?= (int)$dayRec['processing_count'] ?> pesanan</strong> berstatus <em>Diproses</em> pada tanggal ini.
            </div>
        <?php endif; ?>

        <!-- Date Selector Row -->
        <form method="GET" class="bg-[#F8F7F4] p-3.5 rounded-xl border border-[#E6E4DD] flex items-center justify-between gap-3">
            <span class="text-xs font-bold text-gray-700">&#128197; Tanggal Tutup Kasir</span>
            <div class="flex items-center gap-2">
                <input type="date" name="date" max="<?= e($today) ?>" value="<?= e($closingDate) ?>"
                       onchange="this.form.submit()"
                       class="bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-xs font-bold text-[#1E293B]">
            </div>
        </form>

        <!-- Automatic System Summary Cards -->
        <div class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">&#128200; Ringkasan Sistem (Otomatis)</span>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="bg-[#F8F7F4] p-3.5 rounded-xl border border-[#E6E4DD]">
                    <span class="text-[11px] text-gray-500 font-semibold block">Penjualan Tunai Bersih</span>
                    <span class="text-lg font-extrabold text-[#1E293B] mt-0.5 block"><?= formatRupiah((int)$dayRec['cash_sales']) ?></span>
                    <span class="text-[10px] text-emerald-700 font-bold">&bull; Non-Void</span>
                </div>
                <div class="bg-[#F8F7F4] p-3.5 rounded-xl border border-[#E6E4DD]">
                    <span class="text-[11px] text-gray-500 font-semibold block">Penjualan QRIS (Info)</span>
                    <span class="text-lg font-extrabold text-[#1E293B] mt-0.5 block"><?= formatRupiah((int)$dayRec['qris_sales']) ?></span>
                    <span class="text-[10px] text-gray-400">Masuk Rekening Bank</span>
                </div>
                <div class="bg-[#F8F7F4] p-3.5 rounded-xl border border-[#E6E4DD]">
                    <span class="text-[11px] text-gray-500 font-semibold block">Pesanan Dibatalkan</span>
                    <span class="text-lg font-extrabold text-[#9E3B1B] mt-0.5 block"><?= (int)$dayRec['void_count'] ?> Pesanan</span>
                    <span class="text-[10px] text-red-600">Total <?= formatRupiah((int)$dayRec['void_total']) ?></span>
                </div>
            </div>
        </div>

        <!-- Reconciliation Form -->
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <input type="hidden" name="closing_date" value="<?= e($closingDate) ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <div class="flex justify-between text-xs font-bold text-gray-700 mb-1">
                        <span>Modal Awal Laci (Float)</span>
                        <span class="text-gray-400">IDR</span>
                    </div>
                    <input type="number" name="opening_cash" id="opening_cash" min="0" step="1"
                           value="<?= $existingClose ? (int)$existingClose['opening_cash'] : 200000 ?>"
                           oninput="calcExpected()" required
                           class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-base font-extrabold text-[#1E293B] focus:bg-white">
                    <p class="text-[11px] text-gray-400 mt-1">Saldo kas awal saat pembukaan kasir</p>
                </div>

                <div>
                    <div class="flex justify-between text-xs font-bold text-gray-700 mb-1">
                        <span>Uang Keluar dari Laci (Cash Out)</span>
                        <span class="text-gray-400">IDR</span>
                    </div>
                    <input type="number" name="cash_out" id="cash_out" min="0" step="1"
                           value="<?= $existingClose ? (int)$existingClose['cash_out'] : 0 ?>"
                           oninput="calcExpected()" required
                           class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-base font-extrabold text-[#1E293B] focus:bg-white">
                    <p class="text-[11px] text-gray-400 mt-1">Catatan: Jika untuk belanja toko, catat juga di Keuangan</p>
                </div>
            </div>

            <!-- Expected Target Box -->
            <div class="bg-[#EEF7F2] border border-emerald-200 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <div class="text-sm font-extrabold text-[#1E293B]">Kas Seharusnya di Laci (Target)</div>
                    <div id="expected_formula_text" class="text-xs text-gray-500 mt-0.5">Kalkulasi: (Modal Awal + Penjualan Tunai - Uang Keluar)</div>
                </div>
                <div class="text-right">
                    <div id="expected_display" class="text-2xl font-extrabold text-[#1B4D3E]">Rp0</div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Nominal Wajib Ada</span>
                </div>
            </div>

            <!-- Physical Cash Counted -->
            <div>
                <div class="flex justify-between items-center mb-1">
                    <label for="counted_cash" class="text-xs font-extrabold uppercase tracking-wider text-gray-700">Uang Fisik Dihitung (Actual)</label>
                    <span class="text-[11px] text-[#9E3B1B] font-semibold">Hasil Perhitungan Nyata</span>
                </div>
                <input type="number" name="counted_cash" id="counted_cash" min="0" step="1"
                       value="<?= $existingClose ? (int)$existingClose['counted_cash'] : ((int)$dayRec['cash_sales'] + 200000) ?>"
                       oninput="calcExpected()" required
                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-4 py-3 text-xl font-extrabold text-[#1E293B] focus:bg-white focus:ring-2 focus:ring-[#1B4D3E]">
            </div>

            <!-- Live Difference Banner -->
            <div id="diff_banner" class="p-3.5 rounded-xl border flex items-center justify-between">
                <div>
                    <div id="diff_title" class="text-xs font-bold">Status Rekonsiliasi Kas</div>
                    <div id="diff_subtitle" class="text-[11px] opacity-80">Perbandingan uang fisik terhadap sistem</div>
                </div>
                <span id="diff_badge" class="text-xs font-extrabold px-3 py-1.5 rounded-full">SELISIH KAS: Rp0</span>
            </div>

            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="text-xs font-bold text-gray-700">Catatan Selisih</label>
                    <span class="text-[11px] text-gray-400">Opsional (Maks. 255 karakter)</span>
                </div>
                <input type="text" name="note" maxlength="255" value="<?= e($existingClose['note'] ?? '') ?>"
                       placeholder="Contoh: Salah kembalian pada transaksi siang tadi..."
                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white">
            </div>

            <button type="submit" class="w-full bg-[#9E3B1B] hover:bg-[#822F14] text-white font-extrabold py-3.5 rounded-xl shadow-sm transition uppercase tracking-wider text-sm">
                &#128190; SIMPAN LAPORAN TUTUP KASIR
            </button>
        </form>
    </div>

    <!-- Right 5 Columns: 30-Day History -->
    <div class="lg:col-span-5 bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <div>
                <h2 class="text-base font-extrabold text-[#1E293B] uppercase">RIWAYAT TUTUP KASIR</h2>
                <p class="text-xs text-gray-400">(30 Hari Terakhir)</p>
            </div>
            <button type="button" onclick="window.print()" class="p-2 rounded-lg bg-[#F8F7F4] hover:bg-gray-200 text-gray-600 text-xs font-bold" title="Cetak">&#128424;</button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-[11px] font-bold uppercase text-gray-400">
                        <th class="py-2.5 px-2">Tanggal</th>
                        <th class="py-2.5 px-2">Operator</th>
                        <th class="py-2.5 px-2 text-right">Target</th>
                        <th class="py-2.5 px-2 text-right">Dihitung</th>
                        <th class="py-2.5 px-2 text-right">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($historyClosings)): ?>
                        <tr><td colspan="5" class="py-8 text-center text-gray-400">Belum ada riwayat tutup kasir.</td></tr>
                    <?php else: foreach ($historyClosings as $c):
                        $isStale = ((int)$c['cash_sales'] !== (int)$c['live_cash_sales']);
                        $diffVal = (int)$c['difference'];
                        $dotColor = $diffVal === 0 ? 'text-emerald-500' : ($diffVal > 0 ? 'text-amber-500' : 'text-red-500');
                        $badgeCls = $diffVal === 0 ? 'bg-emerald-50 text-emerald-800' : ($diffVal > 0 ? 'bg-amber-50 text-amber-800' : 'bg-red-50 text-red-700');
                    ?>
                        <tr class="hover:bg-[#F8F7F4]/60">
                            <td class="py-3 px-2 font-bold text-[#1E293B] whitespace-nowrap">
                                <span class="<?= $dotColor ?>">&bull;</span> <?= e(date('d/m/Y', strtotime($c['closing_date']))) ?>
                            </td>
                            <td class="py-3 px-2 text-gray-500"><?= e($c['closer_user']) ?></td>
                            <td class="py-3 px-2 text-right text-gray-600"><?= number_format((int)$c['expected_cash'], 0, ',', '.') ?></td>
                            <td class="py-3 px-2 text-right font-extrabold text-[#1E293B]"><?= number_format((int)$c['counted_cash'], 0, ',', '.') ?></td>
                            <td class="py-3 px-2 text-right">
                                <span class="inline-block font-bold px-2 py-0.5 rounded-full <?= $badgeCls ?>">
                                    <?= $diffVal === 0 ? '&#10004; 0 (Pas)' : (($diffVal > 0 ? '+' : '') . number_format($diffVal, 0, ',', '.')) ?>
                                </span>
                                <?php if ($isStale): ?>
                                    <div class="mt-1">
                                        <span class="inline-block bg-amber-100 text-amber-900 text-[10px] font-bold px-2 py-0.5 rounded">&#9888; Perlu disimpan ulang</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const cashSales = <?= (int)$dayRec['cash_sales'] ?>;
function calcExpected() {
    const opening = parseInt(document.getElementById('opening_cash').value, 10) || 0;
    const cashOut = parseInt(document.getElementById('cash_out').value, 10) || 0;
    const counted = parseInt(document.getElementById('counted_cash').value, 10) || 0;

    const expected = opening + cashSales - cashOut;
    const diff = counted - expected;

    document.getElementById('expected_display').textContent = 'Rp' + expected.toLocaleString('id-ID');
    document.getElementById('expected_formula_text').textContent =
        `Kalkulasi: (${opening.toLocaleString('id-ID')} + ${cashSales.toLocaleString('id-ID')} - ${cashOut.toLocaleString('id-ID')})`;

    const banner = document.getElementById('diff_banner');
    const title = document.getElementById('diff_title');
    const sub = document.getElementById('diff_subtitle');
    const badge = document.getElementById('diff_badge');

    if (diff === 0) {
        banner.className = 'p-3.5 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-900 flex items-center justify-between';
        title.textContent = 'Kas Sesuai (Seimbang)';
        sub.textContent = 'Uang fisik cocok dengan catatan sistem POS';
        badge.className = 'text-xs font-extrabold px-3 py-1.5 rounded-full bg-[#1B4D3E] text-white';
        badge.textContent = 'SELISIH KAS: Rp0 (PAS)';
    } else if (diff > 0) {
        banner.className = 'p-3.5 rounded-xl border bg-amber-50 border-amber-200 text-amber-900 flex items-center justify-between';
        title.textContent = 'Indikasi Kelebihan Kas';
        sub.textContent = 'Fisik lebih besar dibanding rekonsiliasi sistem';
        badge.className = 'text-xs font-extrabold px-3 py-1.5 rounded-full bg-amber-600 text-white';
        badge.textContent = 'SELISIH KAS: +Rp' + diff.toLocaleString('id-ID') + ' (LEBIH)';
    } else {
        banner.className = 'p-3.5 rounded-xl border bg-red-50 border-red-200 text-red-900 flex items-center justify-between';
        title.textContent = 'Indikasi Ketidakcocokan Kas';
        sub.textContent = 'Fisik lebih kecil dibanding rekonsiliasi sistem';
        badge.className = 'text-xs font-extrabold px-3 py-1.5 rounded-full bg-red-700 text-white';
        badge.textContent = 'SELISIH KAS: -Rp' + Math.abs(diff).toLocaleString('id-ID') + ' (KURANG)';
    }
}
window.addEventListener('DOMContentLoaded', calcExpected);
</script>

<?php else: ?>
<!-- CASHIER VIEW: Read-Only 30-Day History (Figma Slide 3) -->
<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Pusat Informasi Kasir</span>
            <h1 class="text-2xl font-extrabold text-[#1E293B]">Tutup Kasir Harian</h1>
            <p class="text-xs text-gray-500">Arsip pencatatan dan rekonsiliasi kas masuk akhir shift kasir Kedai Paradise Purbalingga.</p>
        </div>
        <span class="bg-amber-50 text-amber-900 border border-amber-200 text-xs font-bold px-3 py-1.5 rounded-full">
            &#128274; Hanya Tinjauan Baca
        </span>
    </div>

    <div class="bg-[#FAF6F0] border border-amber-200/80 rounded-2xl p-4 flex items-center gap-3 text-xs text-amber-950">
        <span class="w-8 h-8 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-base">&#128274;</span>
        <div>
            <div class="font-bold">Akses Terbatas Kasir</div>
            <div class="text-gray-600">Hanya admin yang dapat menyimpan laporan tutup kasir.</div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] overflow-hidden">
        <div class="p-4 border-b border-gray-100 font-extrabold text-xs uppercase tracking-wider text-gray-600">
            Riwayat Tutup Kasir (30 Hari Terakhir)
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-[#F8F7F4] border-b border-gray-200 text-xs font-bold uppercase text-gray-500">
                        <th class="p-3.5">Tanggal</th>
                        <th class="p-3.5 text-right">Target</th>
                        <th class="p-3.5 text-right">Dihitung</th>
                        <th class="p-3.5 text-right">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($historyClosings)): ?>
                        <tr><td colspan="4" class="p-6 text-center text-gray-400">Belum ada riwayat tutup kasir.</td></tr>
                    <?php else: foreach ($historyClosings as $c):
                        $isStale = ((int)$c['cash_sales'] !== (int)$c['live_cash_sales']);
                        $diffVal = (int)$c['difference'];
                        $badgeCls = $diffVal === 0 ? 'bg-emerald-50 text-emerald-800' : ($diffVal > 0 ? 'bg-amber-50 text-amber-800' : 'bg-red-50 text-red-700');
                    ?>
                        <tr class="hover:bg-[#F8F7F4]/60">
                            <td class="p-3.5 font-bold text-[#1E293B]">
                                <?= e(date('d/m/Y', strtotime($c['closing_date']))) ?>
                                <?php if ($isStale): ?>
                                    <span class="ml-2 inline-block bg-amber-100 text-amber-900 text-[10px] font-bold px-2 py-0.5 rounded">! Perlu disimpan ulang</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 text-right text-gray-600"><?= number_format((int)$c['expected_cash'], 0, ',', '.') ?></td>
                            <td class="p-3.5 text-right font-extrabold text-[#1E293B]"><?= number_format((int)$c['counted_cash'], 0, ',', '.') ?></td>
                            <td class="p-3.5 text-right">
                                <span class="inline-block text-xs font-bold px-2.5 py-0.5 rounded-full <?= $badgeCls ?>">
                                    <?= $diffVal === 0 ? '&#10004; 0 (Pas)' : (($diffVal > 0 ? '+' : '') . number_format($diffVal, 0, ',', '.')) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>