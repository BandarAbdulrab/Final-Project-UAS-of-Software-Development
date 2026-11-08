<?php
// 1. Muat sistem fondasi utama
require_once __DIR__ . '/includes/bootstrap.php';

// 2. Simulasikan sesi pengguna sementara (karena sistem login dibuat di Hari 2)
if (!isset($_SESSION['role'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'fajar';
    $_SESSION['name'] = 'Bapak Fajar';
    $_SESSION['role'] = 'admin';
}

$pageTitle = 'Uji Coba Fondasi Hari 1 - Kedai Paradise';

// 3. Panggil Header Layout (Navigasi & Tailwind CSS)
include __DIR__ . '/includes/layout_header.php';
?>

<div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 space-y-6">
    <div class="border-b pb-4">
        <h1 class="text-2xl font-bold text-[#1B4D3E]">Uji Coba Sistem Kedai Paradise POS</h1>
        <p class="text-sm text-gray-600">Halaman ini menguji apakah fondasi Hari 1 sudah berjalan dengan baik.</p>
    </div>

    <!-- 1. Uji Tes Database -->
    <div class="p-4 rounded-md bg-gray-50 border border-gray-200">
        <h2 class="font-semibold text-base mb-2">1. Status Koneksi Database & Timezone</h2>
        <?php
        try {
            $pdo = db();
            $stmt = $pdo->query("SELECT NOW() AS server_time, DATABASE() as db_name");
            $row = $stmt->fetch();
            echo '<div class="text-sm text-green-700 bg-green-50 p-3 rounded border border-green-200 space-y-1">';
            echo '<p>✔️ <strong>Koneksi Database Berhasil!</strong> Connected to database: <code>' . e($row['db_name']) . '</code></p>';
            echo '<p>🕒 <strong>Waktu Server (WIB):</strong> ' . formatDateTimeWib($row['server_time']) . '</p>';
            echo '</div>';
        } catch (Exception $ex) {
            echo '<div class="text-sm text-red-700 bg-red-50 p-3 rounded border border-red-200">';
            echo '❌ <strong>Koneksi Gagal:</strong> ' . e($ex->getMessage());
            echo '</div>';
        }
        ?>
    </div>

    <!-- 2. Uji Helper Formatting -->
    <div class="p-4 rounded-md bg-gray-50 border border-gray-200">
        <h2 class="font-semibold text-base mb-2">2. Uji Helper Function & Bahasa</h2>
        <ul class="text-sm space-y-1 text-gray-700 list-disc list-inside">
            <li><strong>Format Rupiah (14000):</strong>
                <?= formatRupiah(14000) ?>
            </li>
            <li><strong>Teks Kamus (js_pos):</strong>
                <?= t('js_pos') ?>
            </li>
            <li><strong>Teks Kamus (js_cash_close):</strong>
                <?= t('js_cash_close') ?>
            </li>
            <li><strong>Filter Sales Invariant Constant:</strong> <code><?= SALES_FILTER ?></code></li>
        </ul>
    </div>

    <!-- 3. Uji JavaScript Common & Toast -->
    <div class="p-4 rounded-md bg-gray-50 border border-gray-200">
        <h2 class="font-semibold text-base mb-2">3. Uji JavaScript & Toast Alert</h2>
        <button onclick="showToast('Sistem Fondasi Hari 1 Berhasil Berjalan!', 'success')"
            class="bg-[#1B4D3E] text-white px-4 py-2 rounded text-sm hover:bg-[#153d31] transition">
            Klik untuk Tes Toast JS
        </button>
    </div>
</div>

<?php
// 4. Panggil Footer Layout
include __DIR__ . '/includes/layout_footer.php';
?>