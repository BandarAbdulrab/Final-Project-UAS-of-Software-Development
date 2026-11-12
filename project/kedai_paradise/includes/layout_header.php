<?php
if (!isset($pageTitle)) { $pageTitle = 'Kedai Paradise POS'; }
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? 'Staff';
$currentFile = basename($_SERVER['PHP_SELF']);

// Inject JS translation strings (only keys starting with js_)
$jsLangs = [];
$allLangs = require __DIR__ . '/../lang/id.php';
foreach ($allLangs as $k => $v) {
    if (str_starts_with($k, 'js_')) {
        $jsLangs[$k] = $v;
    }
}

$navAdmin = [
    ['dashboard.php', 'js_dashboard'], ['pos.php', 'js_pos'], ['queue.php', 'js_queue'],
    ['history.php', 'js_history'], ['cash_close.php', 'js_cash_close'], ['menu_manage.php', 'js_menu'],
    ['finance.php', 'js_finance'], ['reports.php', 'js_reports'], ['menu_performance.php', 'js_menu_performance'],
    ['users.php', 'js_users'], ['settings.php', 'js_settings'],
];
$navCashier = [
    ['dashboard.php', 'js_dashboard'], ['pos.php', 'js_pos'], ['queue.php', 'js_queue'],
    ['history.php', 'js_history'], ['cash_close.php', 'js_cash_close'],
];
$navItems = $role === 'admin' ? $navAdmin : ($role === 'cashier' ? $navCashier : []);
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
                    <div class="hidden xl:flex space-x-1 text-sm">
                        <?php foreach ($navItems as [$file, $key]): ?>
                            <a href="<?= e($file) ?>" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile === $file ? 'bg-[#153d31]' : '' ?>"><?= e(t($key)) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="account.php" class="text-sm font-medium hover:underline"><?= e($name) ?> (<?= e(t('role_' . $role)) ?>)</a>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1.5 rounded transition"><?= e(t('js_logout')) ?></a>
                    <button type="button" id="navToggle" class="xl:hidden p-2 rounded hover:bg-[#153d31]" aria-label="Menu" aria-expanded="false">&#9776;</button>
                </div>
            </div>
            <div id="mobileNav" class="hidden pb-3 grid gap-1 text-sm">
                <?php foreach ($navItems as [$file, $key]): ?>
                    <a href="<?= e($file) ?>" class="px-3 py-2 rounded hover:bg-[#153d31] <?= $currentFile === $file ? 'bg-[#153d31]' : '' ?>"><?= e(t($key)) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
    <script>
        document.getElementById('navToggle').addEventListener('click', function () {
            var m = document.getElementById('mobileNav');
            m.classList.toggle('hidden');
            this.setAttribute('aria-expanded', m.classList.contains('hidden') ? 'false' : 'true');
        });
    </script>

    <!-- Main Content Container -->
    <main class="flex-grow max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
