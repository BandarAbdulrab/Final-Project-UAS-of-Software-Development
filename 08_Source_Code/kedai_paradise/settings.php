<?php
/**
 * Pengaturan Sistem & Identitas Toko (Admin Only) - SRS-F12 (Figma Slide 17 Match)
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/auth_check.php';
requireRole(['admin']);

$pageTitle = 'Pengaturan Sistem & Identitas Toko - Kedai Paradise';
$errors = [];
$successMessage = '';

try {
    $stmt = db()->prepare('SELECT settings_id, business_name, business_address, table_count, receipt_footer, operating_percent, qris_bank, qris_owner_name, qris_image FROM settings WHERE settings_id = 1 LIMIT 1');
    $stmt->execute();
    $settings = $stmt->fetch();
} catch (Throwable $e) {
    error_log('Settings load error: ' . $e->getMessage());
    http_response_code(500);
    $settings = false;
    $errors[] = t('settings_load_error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $settings) {
    requireCsrf();

    $businessName = trim((string)($_POST['business_name'] ?? ''));
    $businessAddress = trim((string)($_POST['business_address'] ?? ''));
    $receiptFooter = trim((string)($_POST['receipt_footer'] ?? ''));
    $tableCountRaw = trim((string)($_POST['table_count'] ?? ''));
    $operatingPercentRaw = trim((string)($_POST['operating_percent'] ?? ''));
    $qrisBank = trim((string)($_POST['qris_bank'] ?? ''));
    $qrisOwnerName = trim((string)($_POST['qris_owner_name'] ?? ''));

    if ($businessName === '') {
        $errors[] = t('settings_business_name_required');
    }

    if ($tableCountRaw === '' || filter_var($tableCountRaw, FILTER_VALIDATE_INT) === false || (int)$tableCountRaw < 1) {
        $errors[] = t('settings_table_count_invalid');
    }

    if ($operatingPercentRaw === '' || !is_numeric($operatingPercentRaw) || (float)$operatingPercentRaw < 0 || (float)$operatingPercentRaw > 100) {
        $errors[] = t('settings_operating_percent_invalid');
    }

    $newQrisImage = null;
    $uploadedNewImage = false;
    $file = $_FILES['qris_image'] ?? null;
    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && !$errors) {
        try {
            $newQrisImage = saveUploadedImage($file, 'qris', 'qris', 2 * 1024 * 1024);
            $uploadedNewImage = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $oldQrisImage = $settings['qris_image'] ?? null;
        $qrisImage = $uploadedNewImage ? $newQrisImage : $oldQrisImage;

        try {
            $update = db()->prepare(
                'UPDATE settings
                 SET business_name = :business_name,
                     business_address = :business_address,
                     receipt_footer = :receipt_footer,
                     table_count = :table_count,
                     operating_percent = :operating_percent,
                     qris_bank = :qris_bank,
                     qris_owner_name = :qris_owner_name,
                     qris_image = :qris_image
                 WHERE settings_id = 1'
            );
            $update->execute([
                ':business_name'     => $businessName,
                ':business_address'  => $businessAddress !== '' ? $businessAddress : null,
                ':receipt_footer'    => $receiptFooter !== '' ? $receiptFooter : null,
                ':table_count'       => (int)$tableCountRaw,
                ':operating_percent' => number_format((float)$operatingPercentRaw, 2, '.', ''),
                ':qris_bank'         => $qrisBank !== '' ? $qrisBank : null,
                ':qris_owner_name'   => $qrisOwnerName !== '' ? $qrisOwnerName : null,
                ':qris_image'        => $qrisImage,
            ]);

            if ($uploadedNewImage && $oldQrisImage && $oldQrisImage !== $newQrisImage) {
                $uploadRoot = realpath(__DIR__ . '/uploads');
                $oldPath = __DIR__ . '/uploads/' . ltrim((string)$oldQrisImage, '/\\');
                $realOldPath = realpath($oldPath);
                if ($uploadRoot && $realOldPath && str_starts_with($realOldPath, $uploadRoot . DIRECTORY_SEPARATOR) && is_file($realOldPath)) {
                    @unlink($realOldPath);
                }
            }

            $successMessage = t('settings_saved');
            $stmt = db()->prepare('SELECT settings_id, business_name, business_address, table_count, receipt_footer, operating_percent, qris_bank, qris_owner_name, qris_image FROM settings WHERE settings_id = 1 LIMIT 1');
            $stmt->execute();
            $settings = $stmt->fetch();
        } catch (Throwable $e) {
            if ($uploadedNewImage && $newQrisImage) {
                $newPath = __DIR__ . '/uploads/' . $newQrisImage;
                if (is_file($newPath)) {
                    @unlink($newPath);
                }
            }
            error_log('Settings save error: ' . $e->getMessage());
            $errors[] = t('settings_save_error');
        }
    }
}

$opPct = (float)($settings['operating_percent'] ?? 50.0);
$profitPct = max(0.0, 100.0 - $opPct);

include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-6">
    <?php if ($successMessage !== ''): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-semibold text-emerald-900" role="status">
            &#10004; <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-3.5 text-sm font-semibold text-red-800" role="alert">
            &#9888; <?= e($error) ?>
        </div>
    <?php endforeach; ?>

    <?php if ($settings): ?>
    <form method="post" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

        <!-- Top Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-[#1E293B] uppercase">PENGATURAN SISTEM &amp; IDENTITAS TOKO</h1>
                <p class="text-xs text-gray-500">Konfigurasi identitas gerai, format struk belanja, alokasi pos keuangan, dan pembayaran QRIS.</p>
            </div>
            <button type="submit" class="bg-[#9E3B1B] hover:bg-[#822F14] text-white text-xs font-extrabold uppercase tracking-wider px-6 py-3 rounded-xl shadow-sm transition self-start sm:self-auto">
                &#128190; SIMPAN SEMUA PENGATURAN
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Left 5 Columns: Business Identity & Receipt -->
            <section class="lg:col-span-5 bg-white rounded-2xl border border-[#E6E4DD] p-6 shadow-sm space-y-4">
                <div class="border-b border-gray-100 pb-3">
                    <h2 class="text-base font-extrabold text-[#1E293B] uppercase">IDENTITAS USAHA &amp; STRUK</h2>
                    <p class="text-xs text-gray-400">Informasi yang dicetak pada kepala struk thermal kasir</p>
                </div>

                <div>
                    <label for="business_name" class="block text-xs font-bold text-gray-700 mb-1">Nama Usaha <span class="text-red-600">*</span></label>
                    <input id="business_name" name="business_name" type="text" required
                           value="<?= e((string)($settings['business_name'] ?? '')) ?>"
                           class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-bold text-[#1E293B] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                    <span class="text-[11px] text-gray-400">Ditampilkan paling atas dengan ukuran tebal pada struk</span>
                </div>

                <div>
                    <label for="business_address" class="block text-xs font-bold text-gray-700 mb-1">Alamat Usaha</label>
                    <textarea id="business_address" name="business_address" rows="2"
                              class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]"><?= e((string)($settings['business_address'] ?? '')) ?></textarea>
                </div>

                <div>
                    <label for="table_count" class="block text-xs font-bold text-gray-700 mb-1">Jumlah Meja Makan <span class="text-red-600">*</span></label>
                    <div class="flex items-center gap-2">
                        <input id="table_count" name="table_count" type="number" min="1" step="1" required
                               value="<?= e((string)($settings['table_count'] ?? 10)) ?>"
                               class="w-32 bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-base font-extrabold text-[#1E293B] focus:bg-white">
                        <span class="text-xs font-bold text-gray-500">meja dine-in (+ opsi Bawa Pulang / TA)</span>
                    </div>
                </div>

                <div>
                    <label for="receipt_footer" class="block text-xs font-bold text-gray-700 mb-1">Catatan Kaki Struk</label>
                    <textarea id="receipt_footer" name="receipt_footer" rows="2"
                              class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]"><?= e((string)($settings['receipt_footer'] ?? '')) ?></textarea>
                    <span class="text-[11px] text-gray-400">Teks yang muncul di bagian paling bawah struk</span>
                </div>
            </section>

            <!-- Right 7 Columns: 3-Pool Allocation + QRIS Configuration -->
            <div class="lg:col-span-7 space-y-6">
                <!-- 3-Pool Allocation Card (Figma Slide 17) -->
                <section class="bg-white rounded-2xl border border-[#E6E4DD] p-6 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div>
                            <h2 class="text-base font-extrabold text-[#1E293B] uppercase">ALOKASI DANA VIRTUAL (3 POOLS)</h2>
                            <p class="text-xs text-gray-400">Aturan pembagian otomatis hasil omset penjualan ke dalam pos dana toko</p>
                        </div>
                        <span class="bg-emerald-50 text-[#1B4D3E] text-[11px] font-extrabold px-2.5 py-1 rounded-full">Real-time Otomatis</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                        <div>
                            <label for="operating_percent" class="block text-xs font-bold text-gray-700 mb-1">Persentase Pos Operasional (% dari Laba Kotor)</label>
                            <div class="relative">
                                <input id="operating_percent" name="operating_percent" type="number" min="0" max="100" step="0.01" required
                                       value="<?= e((string)($settings['operating_percent'] ?? '50.00')) ?>"
                                       oninput="updatePoolPreview(this.value)"
                                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 pr-10 text-base font-extrabold text-[#1E293B] focus:bg-white">
                                <span class="absolute right-3.5 top-2.5 text-sm font-bold text-gray-500">%</span>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1">Sisa persentase otomatis diteruskan ke pos Laba Bersih</p>
                        </div>

                        <div class="bg-[#F8F7F4] p-3.5 rounded-xl border border-[#E6E4DD] text-xs text-gray-600 leading-relaxed">
                            <strong class="text-[#1E293B] block mb-0.5">&#9881; Formula Pemisahan Otomatis:</strong>
                            Total Omset dipotong HPP (100%) menuju <strong>Restock</strong>. Laba Kotor sisanya dibagi ke <strong>Operasional</strong> &amp; <strong>Laba Bersih</strong>.
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                        <div class="bg-[#F8F7F4] p-3.5 rounded-xl border border-[#E6E4DD]">
                            <span class="text-[10px] font-bold uppercase text-gray-400">POOL 01 &bull; 100% HPP</span>
                            <div class="font-extrabold text-sm text-[#1E293B] mt-0.5">Restock Pool</div>
                            <p class="text-[11px] text-gray-500 mt-1">Belanja bahan baku, kemasan, dan bumbu dapur</p>
                        </div>
                        <div class="bg-[#F8F7F4] p-3.5 rounded-xl border border-[#E6E4DD]">
                            <span class="text-[10px] font-bold uppercase text-[#1B4D3E]">POOL 02 &bull; <span id="preview_op_pct"><?= number_format($opPct, 0) ?></span>% Laba</span>
                            <div class="font-extrabold text-sm text-[#1B4D3E] mt-0.5">Operasional</div>
                            <p class="text-[11px] text-gray-500 mt-1">Listrik, gas LPG, air, dan perawatan alat</p>
                        </div>
                        <div class="bg-[#F8F7F4] p-3.5 rounded-xl border border-[#E6E4DD]">
                            <span class="text-[10px] font-bold uppercase text-[#9E3B1B]">POOL 03 &bull; <span id="preview_pr_pct"><?= number_format($profitPct, 0) ?></span>% Laba</span>
                            <div class="font-extrabold text-sm text-[#9E3B1B] mt-0.5">Laba Bersih</div>
                            <p class="text-[11px] text-gray-500 mt-1">Keuntungan bersih toko &amp; cadangan kas pemilik</p>
                        </div>
                    </div>
                </section>

                <!-- QRIS Static Configuration Card -->
                <section class="bg-white rounded-2xl border border-[#E6E4DD] p-6 shadow-sm">
                    <div class="border-b border-gray-100 pb-3 mb-4">
                        <h2 class="text-base font-extrabold text-[#1E293B] uppercase">PEMBAYARAN DIGITAL QRIS STATIS</h2>
                        <p class="text-xs text-gray-400">Konfigurasi rekening tujuan pembayaran non-tunai kasir</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-5 items-center">
                        <div class="sm:col-span-7 space-y-3.5">
                            <div>
                                <label for="qris_bank" class="block text-xs font-bold text-gray-700 mb-1">Bank / m-Banking</label>
                                <input id="qris_bank" name="qris_bank" type="text"
                                       value="<?= e((string)($settings['qris_bank'] ?? '')) ?>" placeholder="BCA mobile / Mandiri"
                                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2 text-sm font-semibold focus:bg-white">
                            </div>
                            <div>
                                <label for="qris_owner_name" class="block text-xs font-bold text-gray-700 mb-1">Nama Pemilik Akun</label>
                                <input id="qris_owner_name" name="qris_owner_name" type="text"
                                       value="<?= e((string)($settings['qris_owner_name'] ?? '')) ?>" placeholder="Kedai Paradise / Fajar"
                                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2 text-sm font-semibold focus:bg-white">
                            </div>
                            <div>
                                <label for="qris_image" class="block text-xs font-bold text-gray-700 mb-1">Upload Gambar QRIS (Maks. 2 MB, JPG/PNG)</label>
                                <input id="qris_image" name="qris_image" type="file" accept="image/jpeg,image/png"
                                       class="block w-full rounded-xl border border-gray-300 bg-[#F8F7F4] px-3 py-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-[#1B4D3E] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white">
                            </div>
                        </div>

                        <!-- QRIS Preview Card -->
                        <div class="sm:col-span-5 bg-[#F8F7F4] border border-[#E6E4DD] rounded-2xl p-4 text-center">
                            <span class="text-[11px] font-bold uppercase text-gray-400 block mb-2">Pratinjau Gambar</span>
                            <div class="bg-white rounded-xl border border-gray-200 p-3 inline-block shadow-sm">
                                <?php if (!empty($settings['qris_image'])): ?>
                                    <img src="<?= e('uploads/' . ltrim((string)$settings['qris_image'], '/')) ?>" alt="QRIS saat ini" class="h-36 w-36 object-contain mx-auto">
                                <?php else: ?>
                                    <div class="h-32 w-32 flex flex-col items-center justify-center text-gray-400 text-xs mx-auto">
                                        <span class="text-3xl font-mono leading-none mb-1">&#9635;&#9635;<br>&#9635;&#9641;</span>
                                        <span>Belum ada QRIS</span>
                                    </div>
                                <?php endif; ?>
                                <div class="text-[11px] font-extrabold text-[#1E293B] mt-2">
                                    <?= e(($settings['qris_bank'] ?: 'QRIS') . ' – ' . ($settings['qris_owner_name'] ?: 'Kedai Paradise')) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
function updatePoolPreview(val) {
    const op = Math.min(100, Math.max(0, parseFloat(val) || 0));
    const pr = Math.max(0, 100 - op);
    document.getElementById('preview_op_pct').textContent = Math.round(op);
    document.getElementById('preview_pr_pct').textContent = Math.round(pr);
}
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>