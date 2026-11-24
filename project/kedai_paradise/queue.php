<?php
/**
 * Live Kitchen Queue Dashboard (SRS-F08, BR-07) - Figma Slide 26 Design Match
 * Kedai Paradise POS
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$pdo = db();

$activeOrders = $pdo->query(
    'SELECT t.transaction_id, t.order_no, t.date, t.table_no, u.name AS cashier_name
     FROM transactions t
     LEFT JOIN users u ON u.user_id = t.user_id
     WHERE t.status = "processing"
     ORDER BY t.date ASC'
)->fetchAll();

$activeTxIds = array_column($activeOrders, 'transaction_id');
$activeItemsByTx = [];
$activeModsByOrder = [];
$orderIds = [];

if (!empty($activeTxIds)) {
    $in = implode(',', array_fill(0, count($activeTxIds), '?'));
    $stmtItems = $pdo->prepare("SELECT o.*, m.name AS menu_name FROM orders o JOIN menu m ON m.menu_id = o.menu_id WHERE o.transaction_id IN ($in)");
    $stmtItems->execute($activeTxIds);
    while ($row = $stmtItems->fetch()) {
        $activeItemsByTx[(int)$row['transaction_id']][] = $row;
        $orderIds[] = (int)$row['order_id'];
    }

    if (!empty($orderIds)) {
        $inO = implode(',', array_fill(0, count($orderIds), '?'));
        $stmtMods = $pdo->prepare("SELECT * FROM order_item_modifiers WHERE order_id IN ($inO)");
        $stmtMods->execute($orderIds);
        while ($m = $stmtMods->fetch()) {
            $activeModsByOrder[(int)$m['order_id']][] = $m['modifier_name'];
        }
    }
}

$completedToday = $pdo->query(
    'SELECT t.transaction_id, t.order_no, t.table_no, t.date, t.completed_at, t.duration_seconds, u.name AS cashier_name
     FROM transactions t
     LEFT JOIN users u ON u.user_id = t.user_id
     WHERE t.status = "completed" AND t.order_date = CURDATE()
     ORDER BY t.completed_at DESC LIMIT 20'
)->fetchAll();

$todayYmd = date('Y-m-d');
$pageTitle = 'Antrian Pesanan Masuk - Kedai Paradise';
include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-6">
    <!-- Top Header Banner -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-xl sm:text-2xl font-extrabold text-[#1E293B] uppercase tracking-wide">ANTRIAN PESANAN MASUK</h1>
            <span class="bg-[#F8F7F4] border border-gray-200 text-gray-700 text-xs font-extrabold px-3 py-1 rounded-full">
                <?= count($activeOrders) ?> Pesanan Aktif
            </span>
        </div>
        <div class="flex items-center gap-2.5">
            <span class="bg-emerald-50 text-[#1B4D3E] border border-emerald-200 text-xs font-bold px-3 py-2 rounded-xl flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Pembaruan Otomatis: <strong id="refresh-countdown">15s</strong></span>
            </span>
            <button onclick="location.reload()" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                &#8635; Perbarui Manual
            </button>
        </div>
    </div>

    <!-- Section Subheader -->
    <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-gray-500 px-1">
        <span>&#9832; Tiket Dapur Berjalan</span>
        <span class="normal-case font-medium text-gray-400">Prioritaskan kartu dengan indikator merah</span>
    </div>

    <!-- Active Tickets Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" id="queue-grid">
        <?php if (empty($activeOrders)): ?>
            <div class="col-span-full bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-12 text-center text-gray-400">
                Tidak ada antrian pesanan yang sedang diproses saat ini.
            </div>
        <?php else: ?>
            <?php foreach ($activeOrders as $tx):
                $tid = (int)$tx['transaction_id'];
                $items = $activeItemsByTx[$tid] ?? [];
                $timestamp = strtotime($tx['date']);
                $isOlderDay = (date('Y-m-d', $timestamp) !== $todayYmd);
                $elapsedSec = max(0, time() - $timestamp);
                $isTakeAway = ($tx['table_no'] === 'TA');
                $isUrgent = ($elapsedSec >= 900); // >= 15 mins
                $isWarn   = (!$isUrgent && $elapsedSec >= 480); // >= 8 mins
                $topBorderClass = $isUrgent ? 'border-t-4 border-t-red-600 ring-1 ring-red-200' : ($isWarn ? 'border-t-4 border-t-amber-600' : 'border-t-4 border-t-[#1B4D3E]');
            ?>
            <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-5 flex flex-col justify-between space-y-4 <?= $topBorderClass ?>"
                 data-timestamp="<?= $timestamp ?>">
                <div>
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <?php if ($isUrgent): ?>
                                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-red-600">Perhatian Segera &bull;</span>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                        <?= $isTakeAway ? 'Tiket Bungkus' : 'Tiket Meja' ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($isOlderDay): ?>
                                    <span class="text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 px-2 py-0.5 rounded-full">
                                        <?= e(date('d/m/Y', $timestamp)) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h2 class="text-3xl font-extrabold text-[#1E293B]">No. <?= str_pad((string)$tx['order_no'], 3, '0', STR_PAD_LEFT) ?></h2>
                        </div>

                        <?php if ($isTakeAway): ?>
                            <span class="bg-amber-100 text-amber-900 border border-amber-300 text-sm font-extrabold px-3.5 py-1.5 rounded-xl whitespace-nowrap">
                                &#128717; TA
                            </span>
                        <?php else: ?>
                            <span class="bg-emerald-100 text-[#1B4D3E] text-sm font-extrabold px-3.5 py-1.5 rounded-xl whitespace-nowrap">
                                Meja <?= str_pad((string)$tx['table_no'], 2, '0', STR_PAD_LEFT) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Time Bar -->
                    <div class="mt-3 flex items-center justify-between text-xs px-3 py-1.5 rounded-lg <?= $isUrgent ? 'bg-red-50 text-red-700' : ($isWarn ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50/70 text-[#1B4D3E]') ?>">
                        <span>&#128337; Masuk: <strong><?= e($isOlderDay ? date('d/m H:i', $timestamp) : date('H:i', $timestamp)) ?></strong></span>
                        <span class="timer-display font-extrabold" data-time="<?= $timestamp ?>">Lama: 00:00</span>
                    </div>
                </div>

                <!-- Ordered Items -->
                <div class="space-y-2 py-2 flex-1">
                    <?php foreach ($items as $item):
                        $mods = $activeModsByOrder[(int)$item['order_id']] ?? [];
                    ?>
                        <div class="bg-[#F8F7F4] p-3 rounded-xl border border-gray-100">
                            <div class="font-extrabold text-sm text-[#1E293B]">
                                <?= (int)$item['quantity'] ?>x <?= e($item['menu_name']) ?>
                            </div>
                            <?php if (!empty($mods)): ?>
                                <div class="text-xs font-semibold text-[#9E3B1B] mt-0.5">&equiv; [<?= e(implode(', ', $mods)) ?>]</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn-complete-order w-full bg-[#1B4D3E] hover:bg-[#153d31] text-white text-xs font-extrabold uppercase tracking-wider py-3.5 rounded-xl shadow-sm transition flex items-center justify-center gap-2"
                    data-transaction-id="<?= $tid ?>">
                    <span>&#10004; TANDAI SELESAI</span>
                </button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Completed Today Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-6 space-y-4">
        <div>
            <h3 class="text-base font-extrabold text-[#1E293B] uppercase tracking-wide">PESANAN SELESAI HARI INI</h3>
            <p class="text-xs text-gray-500">Log audit performa pelayanan dan durasi masak per tiket</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-[#F8F7F4] border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                        <th class="py-3 px-4">No. Pesanan</th>
                        <th class="py-3 px-4">Meja / TA</th>
                        <th class="py-3 px-4">Waktu Pesan</th>
                        <th class="py-3 px-4">Waktu Selesai</th>
                        <th class="py-3 px-4">Durasi Pengerjaan</th>
                        <th class="py-3 px-4">Kasir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($completedToday)): ?>
                        <tr><td colspan="6" class="py-8 text-center text-gray-400 text-xs">Belum ada pesanan yang selesai hari ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($completedToday as $ct):
                            $durSec = (int)$ct['duration_seconds'];
                            $durMmSs = sprintf('%02d:%02d', floor($durSec / 60), $durSec % 60);
                            $isTa = ($ct['table_no'] === 'TA');
                        ?>
                        <tr class="hover:bg-[#F8F7F4]/60">
                            <td class="py-3 px-4 font-extrabold text-base text-[#1E293B]"><?= str_pad((string)$ct['order_no'], 3, '0', STR_PAD_LEFT) ?></td>
                            <td class="py-3 px-4">
                                <span class="text-xs font-bold px-2.5 py-1 rounded-lg <?= $isTa ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-[#1B4D3E]' ?>">
                                    <?= $isTa ? 'TA' : 'Meja ' . str_pad((string)$ct['table_no'], 2, '0', STR_PAD_LEFT) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-gray-600"><?= e(date('H:i', strtotime($ct['date']))) ?> WIB</td>
                            <td class="py-3 px-4 text-gray-600"><?= e(date('H:i', strtotime($ct['completed_at']))) ?> WIB</td>
                            <td class="py-3 px-4">
                                <span class="bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold px-2.5 py-1 rounded-full">
                                    &bull; <?= $durMmSs ?> (<?= $durSec ?> dtk)
                                </span>
                            </td>
                            <td class="py-3 px-4 font-semibold text-gray-700"><?= e($ct['cashier_name'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function formatElapsed(diff) {
        if (diff >= 3600) {
            const h = Math.floor(diff / 3600);
            const m = Math.floor((diff % 3600) / 60);
            return `${h}j ${String(m).padStart(2, '0')}m`;
        }
        const mm = String(Math.floor(diff / 60)).padStart(2, '0');
        const ss = String(diff % 60).padStart(2, '0');
        return `${mm}:${ss}`;
    }

    function updateTimers() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.timer-display').forEach(el => {
            const start = Number(el.dataset.time);
            const diff = Math.max(0, now - start);
            el.textContent = `Lama: ${formatElapsed(diff)}`;
        });
    }
    updateTimers();
    setInterval(updateTimers, 1000);

    let secondsLeft = 15;
    const countEl = document.getElementById('refresh-countdown');
    setInterval(() => {
        if (document.hidden) return;
        secondsLeft -= 1;
        if (countEl) countEl.textContent = secondsLeft + 's';
        if (secondsLeft <= 0) {
            location.reload();
        }
    }, 1000);

    document.querySelectorAll('.btn-complete-order').forEach(btn => {
        btn.addEventListener('click', async function () {
            const txId = Number(this.dataset.transactionId);
            try {
                const res = await apiFetch('api_complete_order.php', {
                    method: 'POST',
                    body: JSON.stringify({ transaction_id: txId })
                });
                showToast(res.message || 'Pesanan selesai.', 'success');
                setTimeout(() => location.reload(), 300);
            } catch (err) {
                showToast(err.message || 'Gagal menyelesaikan pesanan.', 'error');
            }
        });
    });
});
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
