<?php
if (!isset($pageTitle)) { $pageTitle = 'Kedai Paradise POS'; }
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? 'Staff';
$currentFile = basename($_SERVER['PHP_SELF']);
$dbMode = function_exists('getActiveDbMode') ? getActiveDbMode() : 'live';

// Inject JS translation strings (only keys starting with js_)
$jsLangs = [];
$allLangs = require __DIR__ . '/../lang/id.php';
foreach ($allLangs as $k => $v) {
    if (str_starts_with($k, 'js_')) {
        $jsLangs[$k] = $v;
    }
}

// Fetch active queue badge count for navbar
$navQueueCount = 0;
try {
    $navQueueCount = (int)db()->query("SELECT COUNT(*) FROM transactions WHERE status = 'processing'")->fetchColumn();
} catch (Throwable $e) {
    $navQueueCount = 0;
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

// Use explicit $printPeriodLabel set by report pages when available
if (!empty($printPeriodLabel)) {
    $printPeriod = (string)$printPeriodLabel;
} elseif (!empty($_GET['from']) || !empty($_GET['to'])) {
    $fFmt = !empty($_GET['from']) ? date('d/m/Y', strtotime((string)$_GET['from'])) : '-';
    $tFmt = !empty($_GET['to']) ? date('d/m/Y', strtotime((string)$_GET['to'])) : '-';
    $printPeriod = $fFmt . ' s/d ' . $tFmt;
} elseif ($currentFile === 'history.php' && $role === 'cashier') {
    $printPeriod = date('d/m/Y') . ' (Hari Ini)';
} else {
    $printPeriod = date('d/m/Y');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/print.css">
    <script>
        window.CSRF_TOKEN = '<?= generateCsrfToken() ?>';
        window.I18N = <?= json_encode($jsLangs) ?>;
    </script>
</head>
<body class="bg-[#F8F7F4] text-[#1E293B] font-sans antialiased min-h-screen flex flex-col">
    <!-- Top Navbar matching Figma -->
    <nav class="bg-[#1B4D3E] text-white shadow-md border-b border-white/10 print:hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between min-h-[4.25rem] py-2 gap-3">
                <!-- Brand & Nav Links -->
                <div class="flex items-center gap-5 flex-wrap">
                    <a href="dashboard.php" class="flex items-center gap-2.5 group">
                        <span class="w-9 h-9 rounded-lg bg-white/15 flex items-center justify-center text-white font-bold text-sm shadow-inner group-hover:bg-white/20 transition">
                            &#127860;
                        </span>
                        <div class="leading-tight">
                            <div class="font-extrabold text-sm sm:text-base tracking-wider text-white flex items-center gap-2">
                                KEDAI PARADISE
                                <?php if ($dbMode === 'test'): ?>
                                    <span class="bg-amber-400 text-amber-950 text-[10px] font-extrabold px-2 py-0.5 rounded uppercase tracking-normal">DEMO DB</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[10px] tracking-widest uppercase text-emerald-200/90 font-medium">Kandang Gampang, Purbalingga</div>
                        </div>
                    </a>

                    <div class="hidden xl:flex items-center flex-wrap gap-1 text-xs font-medium">
                        <?php foreach ($navItems as [$file, $key]):
                            $isActive = ($currentFile === $file);
                        ?>
                            <a href="<?= e($file) ?>" class="px-2.5 py-1.5 rounded-lg whitespace-nowrap transition flex items-center gap-1.5 <?= $isActive ? 'bg-white/15 text-white font-semibold shadow-sm ring-1 ring-white/25' : 'text-emerald-50/85 hover:bg-white/10 hover:text-white' ?>">
                                <span><?= e(t($key)) ?></span>
                                <?php if ($file === 'queue.php' && $navQueueCount > 0): ?>
                                    <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.2 rounded-full leading-tight"><?= $navQueueCount ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right Status, User Profile & Logout -->
                <div class="flex items-center gap-3 ml-auto">
                    <div class="hidden md:flex items-center gap-2 text-[11px] text-emerald-100/90 bg-black/15 px-3 py-1.5 rounded-full border border-white/10">
                        <span id="liveWibClock"><?= e(date('d/m/Y H:i:s')) ?> WIB</span>
                        <span class="text-emerald-400 font-bold">&bull; Online</span>
                    </div>

                    <a href="account.php" class="flex items-center gap-2 bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg transition border border-white/10">
                        <span class="text-xs font-semibold text-white whitespace-nowrap"><?= e($name) ?></span>
                        <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded <?= $role === 'admin' ? 'bg-emerald-950/70 text-emerald-200' : 'bg-amber-400/20 text-amber-200' ?>">
                            <?= e(t('role_' . $role)) ?>
                        </span>
                    </a>

                    <a href="logout.php" class="bg-[#9E3B1B] hover:bg-[#822F14] text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition whitespace-nowrap">
                        &rarr; <?= e(t('js_logout')) ?>
                    </a>

                    <button type="button" id="navToggle" class="xl:hidden p-2 rounded-lg hover:bg-white/10 text-white" aria-label="Menu" aria-expanded="false">&#9776;</button>
                </div>
            </div>

            <!-- Mobile / Tablet Collapsible Nav -->
            <div id="mobileNav" class="hidden xl:hidden pb-3 pt-2 border-t border-white/10 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-1.5 text-xs">
                <?php foreach ($navItems as [$file, $key]): ?>
                    <a href="<?= e($file) ?>" class="px-3 py-2 rounded-lg flex items-center justify-between hover:bg-white/10 <?= $currentFile === $file ? 'bg-white/15 font-semibold' : '' ?>">
                        <span><?= e(t($key)) ?></span>
                        <?php if ($file === 'queue.php' && $navQueueCount > 0): ?>
                            <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $navQueueCount ?></span>
                        <?php endif; ?>
                    </a>
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
        // Live WIB Clock
        setInterval(function () {
            var el = document.getElementById('liveWibClock');
            if (!el) return;
            var now = new Date();
            var fmt = new Intl.DateTimeFormat('id-ID', {
                timeZone: 'Asia/Jakarta',
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit',
                hour12: false
            }).format(now).replace(/\./g, ':').replace(',', '');
            el.textContent = fmt + ' WIB';
        }, 1000);
    </script>

    <!-- Main Content Container -->
    <main class="flex-grow max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
        <!-- Print-Only Header -->
        <div class="print-header hidden print:block">
            <h1><?= e($settings['business_name'] ?? 'Kedai Paradise') ?></h1>
            <h2><?= e($pageTitle ?? 'Laporan') ?></h2>
            <div class="print-meta">
                <span><strong>Periode:</strong> <?= e($printPeriod) ?></span>
                <span><strong>Dicetak oleh:</strong> <?= e($_SESSION['name'] ?? $_SESSION['username'] ?? '-') ?></span>
                <span><strong>Waktu Cetak:</strong> <?= e(date('d/m/Y H:i')) ?> WIB</span>
            </div>
        </div>
