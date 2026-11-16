<?php
/**
 * Live Kitchen Queue Dashboard (SRS-F08, BR-07)
 * Kedai Paradise POS
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$pdo = db();

// Fetch active processing orders
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

// Fetch completed orders today
$completedToday = $pdo->query(
    'SELECT t.transaction_id, t.order_no, t.table_no, t.date, t.completed_at, t.duration_seconds, u.name AS cashier_name
     FROM transactions t
     LEFT JOIN users u ON u.user_id = t.user_id
     WHERE t.status = "completed" AND t.order_date = CURDATE()
     ORDER BY t.completed_at DESC LIMIT 20'
)->fetchAll();

$pageTitle = 'Antrian Dapur';
include __DIR__ . '/includes/layout_header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-[#2C3E50]">Antrian Pesanan Aktif</h1>
            <p class="text-sm text-gray-600">Tiket dapur diperbarui otomatis setiap 15 detik.</p>
        </div>
        <button onclick="location.reload()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3.5 py-2 rounded-lg text-sm font-semibold transition">
            Muat Ulang
        </button>
    </div>

    <!-- Active Tickets Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="queue-grid">
        <?php if (empty($activeOrders)): ?>
            <div class="col-span-full bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center text-gray-400">
                Tidak ada antrian pesanan yang sedang diproses.
            </div>
        <?php else: ?>
            <?php foreach ($activeOrders as $tx): 
                $tid = (int)$tx['transaction_id'];
                $items = $activeItemsByTx[$tid] ?? [];
                $timestamp = strtotime($tx['date']);
            ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex flex-col justify-between space-y-4 border-l-4 border-l-[#1B4D3E]"
                 data-timestamp="<?= $timestamp ?>">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-gray-400">No. Pesanan</span>
                        <h2 class="text-3xl font-extrabold text-[#1B4D3E]">#<?= (int)$tx['order_no'] ?></h2>
                    </div>
                    <span class="bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1 rounded-full">
                        Meja: <?= e($tx['table_no']) ?>
                    </span>
                </div>

                <div class="space-y-2 py-2 border-y border-gray-100 flex-1">
                    <?php foreach ($items as $item): 
                        $mods = $activeModsByOrder[(int)$item['order_id']] ?? [];
                    ?>
                        <div class="text-sm">
                            <div class="font-semibold text-[#2C3E50]">
                                <span class="text-[#1B4D3E] font-bold"><?= (int)$item['quantity'] ?>x</span> <?= e($item['menu_name']) ?>
                            </div>
                            <?php if (!empty($mods)): ?>
                                <div class="text-xs text-gray-500 pl-4">[<?= e(implode(', ', $mods)) ?>]</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <div class="text-xs text-gray-500">
                        <span>Waktu: <?= e(date('H:i', $timestamp)) ?></span><br>
                        <span class="timer-display font-semibold text-amber-700" data-time="<?= $timestamp ?>">Lama: 00:00</span>
                    </div>
                    <button type="button" class="btn-complete-order bg-[#1B4D3E] hover:bg-[#153d31] text-white text-xs font-bold px-4 py-2.5 rounded-lg transition"
                        data-transaction-id="<?= $tid ?>">
                        Tandai Selesai
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Completed Today Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-4">
        <h3 class="text-lg font-bold text-[#2C3E50]">Pesanan Selesai Hari Ini</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-[#2C3E50] font-semibold">
                        <th class="py-2.5 px-4">No. Pesanan</th>
                        <th class="py-2.5 px-4">Meja</th>
                        <th class="py-2.5 px-4">Waktu Pesan</th>
                        <th class="py-2.5 px-4">Waktu Selesai</th>
                        <th class="py-2.5 px-4">Durasi</th>
                        <th class="py-2.5 px-4">Kasir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($completedToday)): ?>
                        <tr><td colspan="6" class="py-6 text-center text-gray-400 text-xs">Belum ada pesanan yang selesai hari ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($completedToday as $ct): 
                            $durSec = (int)$ct['duration_seconds'];
                            $durMmSs = sprintf('%02d:%02d', floor($durSec / 60), $durSec % 60);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2.5 px-4 font-bold text-[#1B4D3E]">#<?= (int)$ct['order_no'] ?></td>
                            <td class="py-2.5 px-4"><?= e($ct['table_no']) ?></td>
                            <td class="py-2.5 px-4"><?= e(date('H:i:s', strtotime($ct['date']))) ?></td>
                            <td class="py-2.5 px-4"><?= e(date('H:i:s', strtotime($ct['completed_at']))) ?></td>
                            <td class="py-2.5 px-4 font-medium text-emerald-700"><?= $durMmSs ?></td>
                            <td class="py-2.5 px-4 text-gray-600"><?= e($ct['cashier_name'] ?? '-') ?></td>
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
    // Live elapsed timer ticker
    setInterval(() => {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.timer-display').forEach(el => {
            const start = Number(el.dataset.time);
            const diff = Math.max(0, now - start);
            const mm = String(Math.floor(diff / 60)).padStart(2, '0');
            const ss = String(diff % 60).padStart(2, '0');
            el.textContent = `Lama: ${mm}:${ss}`;
        });
    }, 1000);

    // Auto-refresh queue every 15 seconds
    setInterval(() => {
        if (!document.hidden) {
            location.reload();
        }
    }, 15000);

    // Complete order button handler
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