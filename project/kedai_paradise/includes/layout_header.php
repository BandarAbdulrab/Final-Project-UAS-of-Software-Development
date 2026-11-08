<?php
if (!isset($pageTitle)) { $pageTitle = 'Kedai Paradise POS'; }
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? 'Staff';
$currentFile = basename($_SERVER['PHP_SELF']);

// Injeksi string terjemahan JS (hanya key berawalan js_)
$jsLangs = [];
$allLangs = require __DIR__ . '/../lang/id.php';
foreach ($allLangs as $k => $v) {
    if (str_starts_with($k, 'js_')) {
        $jsLangs[$k] = $v;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        window.CSRF_TOKEN = '<?= generateCsrfToken() ?>';
        window.I18N = <?= json_encode($jsLangs) ?>;
    </script>
</head>
<body class="bg-gray-50 text-[#2C3E50] font-sans antialiased min-h-screen flex flex-col">
    <!-- Navbar -->
    <nav class="bg-[#1B4D3E] text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-6">
                    <span class="font-bold text-lg tracking-wider">KEDAI PARADISE</span>
                    <div class="hidden md:flex space-x-2 text-sm">
                        <?php if ($role === 'admin'): ?>
                            <a href="dashboard.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='dashboard.php'?'bg-[#153d31]':'' ?>">Dasbor</a>
                            <a href="pos.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='pos.php'?'bg-[#153d31]':'' ?>">Kasir</a>
                            <a href="queue.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='queue.php'?'bg-[#153d31]':'' ?>">Antrian</a>
                            <a href="history.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='history.php'?'bg-[#153d31]':'' ?>">Riwayat</a>
                            <a href="cash_close.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='cash_close.php'?'bg-[#153d31]':'' ?>">Tutup Kasir</a>
                            <a href="menu_manage.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='menu_manage.php'?'bg-[#153d31]':'' ?>">Menu</a>
                            <a href="finance.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='finance.php'?'bg-[#153d31]':'' ?>">Keuangan</a>
                            <a href="reports.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='reports.php'?'bg-[#153d31]':'' ?>">Laporan</a>
                        <?php elseif ($role === 'cashier'): ?>
                            <a href="pos.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='pos.php'?'bg-[#153d31]':'' ?>">Kasir</a>
                            <a href="queue.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='queue.php'?'bg-[#153d31]':'' ?>">Antrian</a>
                            <a href="history.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='history.php'?'bg-[#153d31]':'' ?>">Riwayat</a>
                            <a href="cash_close.php" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile==='cash_close.php'?'bg-[#153d31]':'' ?>">Tutup Kasir</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="account.php" class="text-sm font-medium hover:underline"><?= e($name) ?> (<?= e($role) ?>)</a>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1.5 rounded transition">Keluar</a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Container -->
    <main class="flex-grow max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">