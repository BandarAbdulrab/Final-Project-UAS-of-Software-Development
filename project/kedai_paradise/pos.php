<?php
/**
 * Cashier POS Catalog & Active Cart Shell (SRS-F03, SRS-F04, BR-01) - Figma Design Match
 * Kedai Paradise POS (Never exposes cost_price or extra_cost)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$pdo = db();

if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}
$checkoutToken = $_SESSION['checkout_token'];

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
$qrisImageUrl = !empty($settings['qris_image']) ? ('uploads/' . ltrim((string)$settings['qris_image'], '/')) : '';

$pageTitle = 'Kasir (POS) - Kedai Paradise';
include __DIR__ . '/includes/layout_header.php';
?>

<script>
    window.CHECKOUT_TOKEN = <?= json_encode($checkoutToken) ?>;
    window.QRIS_IMAGE = <?= json_encode($qrisImageUrl) ?>;
    window.QRIS_BANK = <?= json_encode((string)($settings['qris_bank'] ?? 'BCA')) ?>;
    window.QRIS_OWNER = <?= json_encode((string)($settings['qris_owner_name'] ?? 'Kedai Paradise')) ?>;
</script>

<div class="flex flex-col lg:flex-row gap-6 items-start">

    <!-- Left Column: Menu Catalog (65% on Desktop) -->
    <div class="w-full lg:w-[65%] space-y-4">
        <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-4 space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="font-extrabold text-sm uppercase tracking-wider text-[#1E293B]">&#127860; Katalog Menu</span>
                    <span class="bg-emerald-50 text-[#1B4D3E] text-xs font-bold px-2.5 py-0.5 rounded-full"><?= count($menus) ?> Item</span>
                </div>
            </div>

            <input type="text" id="pos-search" placeholder="Cari nama makanan atau minuman..."
                class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">

            <div class="flex items-center gap-2 overflow-x-auto pb-1" id="pos-category-pills">
                <button type="button" data-category="ALL"
                    class="cat-pill px-4 py-2 rounded-full text-xs font-bold bg-[#1B4D3E] text-white whitespace-nowrap transition shadow-sm">
                    &#9638; Semua
                </button>
                <?php foreach ($categories as $cat): ?>
                    <button type="button" data-category="<?= e($cat) ?>"
                        class="cat-pill px-4 py-2 rounded-full text-xs font-bold bg-[#F8F7F4] hover:bg-gray-200 text-gray-700 border border-gray-200 whitespace-nowrap transition">
                        <?= e($cat) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Menu Cards Grid (Figma Slide 20) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3.5" id="pos-catalog-grid">
            <?php foreach ($menus as $m):
                $mid = (int)$m['menu_id'];
                $isAvail = (int)$m['available'] === 1;
                $hasMods = (int)$m['group_count'] > 0;
                $cat = trim((string)$m['category']) !== '' ? $m['category'] : 'Lainnya';
                $photoSrc = !empty($m['photo']) ? ('uploads/' . ltrim((string)$m['photo'], '/')) : '';
            ?>
            <div class="menu-card group relative bg-white rounded-2xl border border-[#E6E4DD] shadow-sm overflow-hidden flex flex-col justify-between transition <?= $isAvail ? 'cursor-pointer hover:shadow-md hover:border-[#1B4D3E]' : 'opacity-65 cursor-not-allowed' ?>"
                data-menu-id="<?= $mid ?>"
                data-name="<?= e($m['name']) ?>"
                data-price="<?= (int)$m['price'] ?>"
                data-available="<?= $isAvail ? '1' : '0' ?>"
                data-has-modifiers="<?= $hasMods ? '1' : '0' ?>"
                data-category="<?= e($cat) ?>">

                <!-- Image / Fallback Area -->
                <div class="relative h-32 bg-[#F8F7F4] overflow-hidden">
                    <?php if ($photoSrc !== ''): ?>
                        <img src="<?= e($photoSrc) ?>" alt="<?= e($m['name']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-emerald-50 to-amber-50/40 text-[#1B4D3E] p-3 text-center">
                            <span class="text-2xl mb-1"><?= $cat === 'Minuman' ? '&#129380;' : '&#127835;' ?></span>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#1B4D3E]/70"><?= e($cat) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($hasMods): ?>
                        <span class="absolute top-2 right-2 bg-amber-100 text-amber-900 border border-amber-300 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full shadow-sm">
                            Pilihan
                        </span>
                    <?php endif; ?>

                    <!-- Habis Overlay -->
                    <div class="habis-overlay absolute inset-0 bg-white/75 backdrop-blur-[1px] flex items-center justify-center <?= $isAvail ? 'hidden' : '' ?>">
                        <span class="bg-red-600 text-white text-xs font-extrabold px-3.5 py-1 rounded-full uppercase tracking-wider shadow">
                            HABIS
                        </span>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="p-3.5 flex-1 flex flex-col justify-between gap-2">
                    <div>
                        <div class="flex items-start justify-between gap-1">
                            <h3 class="text-sm font-bold text-[#1E293B] leading-snug line-clamp-2"><?= e($m['name']) ?></h3>
                            <span class="text-[#9E3B1B] font-extrabold text-base leading-none">+</span>
                        </div>
                        <div class="text-base font-extrabold text-[#1B4D3E] mt-1"><?= e(formatRupiah((int)$m['price'])) ?></div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                        <span class="text-[11px] text-gray-400 font-medium">Status Menu</span>

                        <button type="button"
                            class="toggle-availability-btn z-10 text-[11px] font-bold px-2.5 py-0.5 rounded-full border transition <?= $isAvail ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100' : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' ?>"
                            data-toggle-availability="1"
                            data-menu-id="<?= $mid ?>"
                            data-available="<?= $isAvail ? '1' : '0' ?>"
                            title="Klik untuk ubah Tersedia / Habis">
                            &bull; <?= $isAvail ? 'Tersedia' : 'Habis' ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right Column: Active Cart Shell (35% on Desktop) -->
    <div class="w-full lg:w-[35%] bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-5 sticky top-4 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <div>
                <h2 class="text-base font-extrabold text-[#1E293B] uppercase tracking-wide">Pesanan Saat Ini</h2>
                <p class="text-xs text-gray-400">Keranjang Kasir Aktif</p>
            </div>
            <button type="button" onclick="window.clearCart && window.clearCart()"
                class="text-xs text-red-600 hover:bg-red-50 px-2.5 py-1 rounded-lg font-bold transition">
                &#128465; Kosongkan
            </button>
        </div>

        <!-- Cart Line Items Container -->
        <div id="cart-items" class="max-h-72 overflow-y-auto divide-y divide-gray-100"></div>

        <!-- Table Selector -->
        <div class="pt-3 border-t border-gray-100">
            <label for="pos-table" class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1.5">Meja / Bawa Pulang (TA)</label>
            <select id="pos-table"
                class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                <option value="">-- Pilih Nomor Meja / TA --</option>
                <option value="TA">&#128717; Bawa Pulang (TA)</option>
                <?php for ($i = 1; $i <= $tableCount; $i++): ?>
                    <option value="<?= $i ?>">Meja <?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <!-- Payment Method Selector -->
        <div>
            <span class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1.5">Metode Pembayaran</span>
            <div class="grid grid-cols-2 gap-2.5">
                <button type="button" data-method="cash"
                    class="pay-method-btn border-2 border-gray-200 rounded-xl py-2.5 text-sm font-bold text-gray-700 hover:border-[#1B4D3E] transition flex items-center justify-center gap-1.5">
                    <span>&#128181;</span> <span>Tunai</span>
                </button>
                <button type="button" data-method="qris"
                    class="pay-method-btn border-2 border-gray-200 rounded-xl py-2.5 text-sm font-bold text-gray-700 hover:border-[#1B4D3E] transition flex items-center justify-center gap-1.5">
                    <span>&#128241;</span> <span>QRIS</span>
                </button>
            </div>
        </div>

        <!-- Total & Checkout Button -->
        <div class="pt-3 border-t border-gray-200 space-y-3">
            <div class="flex items-center justify-between bg-[#F8F7F4] p-3.5 rounded-xl">
                <span class="text-xs font-extrabold uppercase tracking-wider text-gray-600">Total Pembayaran</span>
                <span id="cart-total" class="text-2xl font-extrabold text-[#1B4D3E]">Rp0</span>
            </div>
            <button type="button" id="pos-checkout-btn" disabled
                class="w-full bg-[#9E3B1B] hover:bg-[#822F14] disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-extrabold py-3.5 rounded-xl shadow-sm transition uppercase tracking-wider text-sm">
                Selesaikan Transaksi &rarr;
            </button>
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
                p.className = 'cat-pill px-4 py-2 rounded-full text-xs font-bold bg-[#F8F7F4] hover:bg-gray-200 text-gray-700 border border-gray-200 whitespace-nowrap transition';
            });
            pill.className = 'cat-pill px-4 py-2 rounded-full text-xs font-bold bg-[#1B4D3E] text-white whitespace-nowrap transition shadow-sm';
            filterCatalog();
        });
    });

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
                this.innerHTML = '&bull; ' + (isNowAvail ? 'Tersedia' : 'Habis');
                this.className = 'toggle-availability-btn z-10 text-[11px] font-bold px-2.5 py-0.5 rounded-full border transition ' +
                    (isNowAvail ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100' : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100');

                if (card) {
                    card.dataset.available = isNowAvail ? '1' : '0';
                    card.classList.toggle('opacity-65', !isNowAvail);
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