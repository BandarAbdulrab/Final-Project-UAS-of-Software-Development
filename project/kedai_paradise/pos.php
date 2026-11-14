<?php
/**
 * Cashier POS Catalog & Active Cart Shell (SRS-F03, SRS-F04, BR-01)
 * Kedai Paradise POS (Never exposes cost_price or extra_cost)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$pdo = db();

// Generate single-use checkout token in session (Prompt 3.3 & 5.1)
if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}
$checkoutToken = $_SESSION['checkout_token'];

// Fetch active menu items WITHOUT cost_price
$menus = $pdo->query(
    'SELECT m.menu_id, m.name, m.category, m.price, m.photo, m.available,
            COUNT(g.group_id) AS group_count
     FROM menu m
     LEFT JOIN menu_modifier_groups g ON g.menu_id = m.menu_id
     WHERE m.status = 1
     GROUP BY m.menu_id, m.name, m.category, m.price, m.photo, m.available
     ORDER BY m.category ASC, m.name ASC'
)->fetchAll();

$categories = [];
foreach ($menus as $m) {
    $cat = trim((string)$m['category']) !== '' ? $m['category'] : 'Lainnya';
    if (!in_array($cat, $categories, true)) {
        $categories[] = $cat;
    }
}

$settings = $pdo->query(
    'SELECT table_count, qris_bank, qris_owner_name, qris_image FROM settings WHERE settings_id = 1 LIMIT 1'
)->fetch() ?: ['table_count' => 10, 'qris_bank' => '', 'qris_owner_name' => '', 'qris_image' => ''];

$tableCount = max(1, (int)($settings['table_count'] ?? 10));

$pageTitle = 'Kasir (POS)';
include __DIR__ . '/includes/layout_header.php';
?>

<script>
    window.CHECKOUT_TOKEN = <?= json_encode($checkoutToken) ?>;
</script>

<div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex flex-col lg:flex-row gap-6 items-start">

        <!-- Left Column: Menu Catalog (65% on Desktop) -->
        <div class="w-full lg:w-[65%] space-y-4">
            <!-- Search & Category Pills -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 space-y-3">
                <input type="text" id="pos-search" placeholder="Cari menu..."
                    class="w-full border border-gray-300 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">

                <div class="flex items-center gap-2 overflow-x-auto pb-1" id="pos-category-pills">
                    <button type="button" data-category="ALL"
                        class="cat-pill px-3.5 py-1.5 rounded-full text-xs font-semibold bg-[#1B4D3E] text-white whitespace-nowrap transition">
                        Semua
                    </button>
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" data-category="<?= e($cat) ?>"
                            class="cat-pill px-3.5 py-1.5 rounded-full text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 whitespace-nowrap transition">
                            <?= e($cat) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Menu Cards Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3.5" id="pos-catalog-grid">
                <?php foreach ($menus as $m):
                    $mid = (int)$m['menu_id'];
                    $isAvail = (int)$m['available'] === 1;
                    $hasMods = (int)$m['group_count'] > 0;
                    $cat = trim((string)$m['category']) !== '' ? $m['category'] : 'Lainnya';
                ?>
                <div class="menu-card group relative bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col justify-between transition <?= $isAvail ? 'cursor-pointer hover:shadow-md hover:border-[#1B4D3E]' : 'opacity-60 cursor-not-allowed' ?>"
                    data-menu-id="<?= $mid ?>"
                    data-name="<?= e($m['name']) ?>"
                    data-price="<?= (int)$m['price'] ?>"
                    data-available="<?= $isAvail ? '1' : '0' ?>"
                    data-has-modifiers="<?= $hasMods ? '1' : '0' ?>"
                    data-category="<?= e($cat) ?>">

                    <!-- Image / Fallback Area -->
                    <div class="relative h-32 bg-gray-100 overflow-hidden">
                        <?php if (!empty($m['photo'])): ?>
                            <img src="<?= e($m['photo']) ?>" alt="<?= e($m['name']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-emerald-50 text-[#1B4D3E] font-bold text-xs">
                                KEDAI PARADISE
                            </div>
                        <?php endif; ?>

                        <?php if ($hasMods): ?>
                            <span class="absolute top-2 left-2 bg-[#1B4D3E] text-white text-[11px] font-semibold px-2 py-0.5 rounded shadow">
                                Pilihan
                            </span>
                        <?php endif; ?>

                        <!-- Habis Overlay -->
                        <div class="habis-overlay absolute inset-0 bg-black/55 flex items-center justify-center <?= $isAvail ? 'hidden' : '' ?>">
                            <span class="bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow">
                                Habis
                            </span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-3 flex-1 flex flex-col justify-between gap-2">
                        <div>
                            <span class="text-[11px] text-gray-400 font-medium"><?= e($cat) ?></span>
                            <h3 class="text-sm font-bold text-[#2C3E50] leading-snug line-clamp-2"><?= e($m['name']) ?></h3>
                        </div>

                        <div class="flex items-center justify-between pt-1 border-t border-gray-100">
                            <span class="text-sm font-bold text-[#1B4D3E]"><?= e(formatRupiah((int)$m['price'])) ?></span>

                            <!-- Availability Quick Toggle -->
                            <button type="button"
                                class="toggle-availability-btn z-10 text-[11px] font-semibold px-2 py-1 rounded border transition <?= $isAvail ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100' : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' ?>"
                                data-toggle-availability="1"
                                data-menu-id="<?= $mid ?>"
                                data-available="<?= $isAvail ? '1' : '0' ?>"
                                title="Ubah Ketersediaan">
                                <?= $isAvail ? 'Tersedia' : 'Habis' ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Column: Active Cart Shell (35% on Desktop) -->
        <div class="w-full lg:w-[35%] bg-white rounded-xl shadow-sm border border-gray-200 p-4 sticky top-4 space-y-4">
            <div class="flex items-center justify-between border-b pb-3">
                <h2 class="text-lg font-bold text-[#2C3E50]">Keranjang</h2>
                <button type="button" onclick="window.clearCart && window.clearCart()"
                    class="text-xs text-red-600 hover:underline font-medium">Kosongkan</button>
            </div>

            <!-- Cart Line Items Container (managed by js/pos.js) -->
            <div id="cart-items" class="max-h-80 overflow-y-auto divide-y divide-gray-100"></div>

            <!-- Table Selector -->
            <div class="pt-2 border-t border-gray-200">
                <label for="pos-table" class="block text-xs font-semibold text-gray-700 mb-1">Meja / Bawa Pulang (TA)</label>
                <select id="pos-table"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                    <option value="">-- Pilih Meja / TA --</option>
                    <option value="TA">Bawa Pulang (TA)</option>
                    <?php for ($i = 1; $i <= $tableCount; $i++): ?>
                        <option value="<?= $i ?>">Meja <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Payment Method Selector Shell -->
            <div>
                <span class="block text-xs font-semibold text-gray-700 mb-1.5">Metode Pembayaran</span>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" data-method="cash"
                        class="pay-method-btn border border-gray-300 rounded-lg py-2 text-sm font-semibold text-gray-700 hover:border-[#1B4D3E]">
                        Tunai
                    </button>
                    <button type="button" data-method="qris"
                        class="pay-method-btn border border-gray-300 rounded-lg py-2 text-sm font-semibold text-gray-700 hover:border-[#1B4D3E]">
                        QRIS
                    </button>
                </div>
            </div>

            <!-- Total & Checkout Button -->
            <div class="pt-3 border-t border-gray-200 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-600">Total</span>
                    <span id="cart-total" class="text-xl font-bold text-[#1B4D3E]">Rp0</span>
                </div>
                <button type="button" id="pos-checkout-btn" disabled
                    class="w-full bg-[#1B4D3E] hover:bg-[#153d31] disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-3 rounded-lg transition">
                    Bayar
                </button>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchEl = document.getElementById('pos-search');
    const pills = document.querySelectorAll('.cat-pill');
    const cards = document.querySelectorAll('.menu-card');
    let activeCategory = 'ALL';

    function filterCatalog() {
        const q = searchEl.value.trim().toLowerCase();
        cards.forEach(card => {
            const matchName = (card.dataset.name || '').toLowerCase().includes(q);
            const matchCat = (activeCategory === 'ALL') || (card.dataset.category === activeCategory);
            card.style.display = (matchName && matchCat) ? '' : 'none';
        });
    }

    searchEl.addEventListener('input', filterCatalog);

    pills.forEach(pill => {
        pill.addEventListener('click', () => {
            activeCategory = pill.dataset.category;
            pills.forEach(p => {
                p.className = 'cat-pill px-3.5 py-1.5 rounded-full text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 whitespace-nowrap transition';
            });
            pill.className = 'cat-pill px-3.5 py-1.5 rounded-full text-xs font-semibold bg-[#1B4D3E] text-white whitespace-nowrap transition';
            filterCatalog();
        });
    });

    // AJAX Toggle Availability on Card
    document.querySelectorAll('.toggle-availability-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.stopPropagation();
            const menuId = Number(this.dataset.menuId);
            const curAvail = this.dataset.available === '1' ? 1 : 0;
            const nextAvail = curAvail === 1 ? 0 : 1;
            const card = this.closest('.menu-card');

            try {
                const res = await apiFetch('api_toggle_availability.php', {
                    method: 'POST',
                    body: JSON.stringify({ menu_id: menuId, available: nextAvail })
                });

                const isNowAvail = Number(res.available) === 1;
                this.dataset.available = isNowAvail ? '1' : '0';
                this.textContent = isNowAvail ? 'Tersedia' : 'Habis';
                this.className = 'toggle-availability-btn z-10 text-[11px] font-semibold px-2 py-1 rounded border transition ' +
                    (isNowAvail ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100' : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100');

                if (card) {
                    card.dataset.available = isNowAvail ? '1' : '0';
                    card.classList.toggle('opacity-60', !isNowAvail);
                    card.classList.toggle('cursor-not-allowed', !isNowAvail);
                    card.classList.toggle('cursor-pointer', isNowAvail);
                    const overlay = card.querySelector('.habis-overlay');
                    if (overlay) overlay.classList.toggle('hidden', isNowAvail);
                }

                showToast(isNowAvail ? 'Menu ditandai Tersedia.' : 'Menu ditandai Habis.', 'success');
            } catch (err) {
                showToast(err.message || 'Gagal mengubah ketersediaan.', 'error');
            }
        });
    });
});
</script>
<script src="js/pos.js" defer></script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>