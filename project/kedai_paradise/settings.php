<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/auth_check.php';
requireRole(['admin']);

$pageTitle = t('settings_title');
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
                ':business_name' => $businessName,
                ':business_address' => $businessAddress !== '' ? $businessAddress : null,
                ':receipt_footer' => $receiptFooter !== '' ? $receiptFooter : null,
                ':table_count' => (int)$tableCountRaw,
                ':operating_percent' => number_format((float)$operatingPercentRaw, 2, '.', ''),
                ':qris_bank' => $qrisBank !== '' ? $qrisBank : null,
                ':qris_owner_name' => $qrisOwnerName !== '' ? $qrisOwnerName : null,
                ':qris_image' => $qrisImage,
            ]);

            // Delete the previous image only after the database update succeeds.
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
            $settings = array_merge($settings, [
                'business_name' => $businessName,
                'business_address' => $businessAddress,
                'receipt_footer' => $receiptFooter,
                'table_count' => $tableCountRaw,
                'operating_percent' => $operatingPercentRaw,
                'qris_bank' => $qrisBank,
                'qris_owner_name' => $qrisOwnerName,
            ]);
        }
    } else {
        $settings = array_merge($settings, [
            'business_name' => $businessName,
            'business_address' => $businessAddress,
            'receipt_footer' => $receiptFooter,
            'table_count' => $tableCountRaw,
            'operating_percent' => $operatingPercentRaw,
            'qris_bank' => $qrisBank,
            'qris_owner_name' => $qrisOwnerName,
        ]);
    }
}

include __DIR__ . '/includes/layout_header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[#1B4D3E]"><?= e(t('settings_title')) ?></h1>
        <p class="mt-1 text-sm text-gray-600"><?= e(t('settings_subtitle')) ?></p>
    </div>

    <?php if ($successMessage !== ''): ?>
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <?= e($error) ?>
        </div>
    <?php endforeach; ?>

    <?php if (!$settings): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <?= e(t('settings_record_missing')) ?>
        </div>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900"><?= e(t('settings_business_profile')) ?></h2>
                <div class="mt-4 grid gap-4">
                    <div>
                        <label for="business_name" class="mb-1 block text-sm font-medium text-gray-700"><?= e(t('settings_business_name')) ?> <span class="text-red-600">*</span></label>
                        <input id="business_name" name="business_name" type="text" required value="<?= e((string)($settings['business_name'] ?? '')) ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#1B4D3E] focus:outline-none focus:ring-2 focus:ring-green-100">
                    </div>
                    <div>
                        <label for="business_address" class="mb-1 block text-sm font-medium text-gray-700"><?= e(t('settings_business_address')) ?></label>
                        <textarea id="business_address" name="business_address" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#1B4D3E] focus:outline-none focus:ring-2 focus:ring-green-100"><?= e((string)($settings['business_address'] ?? '')) ?></textarea>
                    </div>
                    <div>
                        <label for="receipt_footer" class="mb-1 block text-sm font-medium text-gray-700"><?= e(t('settings_receipt_footer')) ?></label>
                        <textarea id="receipt_footer" name="receipt_footer" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#1B4D3E] focus:outline-none focus:ring-2 focus:ring-green-100"><?= e((string)($settings['receipt_footer'] ?? '')) ?></textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900"><?= e(t('settings_table_capacity')) ?></h2>
                <div class="mt-4 max-w-xs">
                    <label for="table_count" class="mb-1 block text-sm font-medium text-gray-700"><?= e(t('settings_table_count')) ?></label>
                    <input id="table_count" name="table_count" type="number" min="1" step="1" required value="<?= e((string)($settings['table_count'] ?? 1)) ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#1B4D3E] focus:outline-none focus:ring-2 focus:ring-green-100">
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900"><?= e(t('settings_profit_allocation')) ?></h2>
                <div class="mt-4 max-w-xs">
                    <label for="operating_percent" class="mb-1 block text-sm font-medium text-gray-700"><?= e(t('settings_operating_percent')) ?></label>
                    <div class="relative">
                        <input id="operating_percent" name="operating_percent" type="number" min="0" max="100" step="0.01" required value="<?= e((string)($settings['operating_percent'] ?? '50.00')) ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-10 text-sm focus:border-[#1B4D3E] focus:outline-none focus:ring-2 focus:ring-green-100">
                        <span class="absolute right-3 top-2 text-sm text-gray-500">%</span>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-gray-500"><?= e(t('settings_profit_help')) ?></p>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900"><?= e(t('settings_qris_configuration')) ?></h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="qris_bank" class="mb-1 block text-sm font-medium text-gray-700"><?= e(t('settings_qris_bank')) ?></label>
                        <input id="qris_bank" name="qris_bank" type="text" value="<?= e((string)($settings['qris_bank'] ?? '')) ?>" placeholder="BCA / Mandiri" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#1B4D3E] focus:outline-none focus:ring-2 focus:ring-green-100">
                    </div>
                    <div>
                        <label for="qris_owner_name" class="mb-1 block text-sm font-medium text-gray-700"><?= e(t('settings_qris_owner_name')) ?></label>
                        <input id="qris_owner_name" name="qris_owner_name" type="text" value="<?= e((string)($settings['qris_owner_name'] ?? '')) ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#1B4D3E] focus:outline-none focus:ring-2 focus:ring-green-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="qris_image" class="mb-1 block text-sm font-medium text-gray-700"><?= e(t('settings_qris_image')) ?></label>
                        <input id="qris_image" name="qris_image" type="file" accept="image/jpeg,image/png" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium">
                        <p class="mt-1 text-xs text-gray-500"><?= e(t('settings_qris_image_hint')) ?></p>
                        <?php if (!empty($settings['qris_image'])): ?>
                            <div class="mt-3">
                                <p class="mb-2 text-xs font-medium text-gray-600"><?= e(t('settings_current_qris')) ?></p>
                                <img src="<?= e('uploads/' . ltrim((string)$settings['qris_image'], '/')) ?>" alt="QRIS saat ini" class="max-h-56 max-w-full rounded-lg border border-gray-200 bg-white p-2">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-[#1B4D3E] px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#153d31] focus:outline-none focus:ring-2 focus:ring-green-300 focus:ring-offset-2">
                    <?= e(t('save')) ?>
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
