<?php
/**
 * Admin Menu & Modifier Management UI (SRS-F03)
 * Kedai Paradise POS
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
requireRole(['admin']);

$pdo = db();

$menus = $pdo->query(
    'SELECT menu_id, name, category, price, cost_price, photo, available, status
     FROM menu
     ORDER BY status DESC, category ASC, name ASC'
)->fetchAll();

$categories = $pdo->query(
    'SELECT DISTINCT category FROM menu WHERE category IS NOT NULL AND category <> "" ORDER BY category ASC'
)->fetchAll(PDO::FETCH_COLUMN);

$groupsRaw = $pdo->query(
    'SELECT group_id, menu_id, name, type, is_required
     FROM menu_modifier_groups
     ORDER BY group_id ASC'
)->fetchAll();

$itemsRaw = $pdo->query(
    'SELECT modifier_id, group_id, name, extra_price, extra_cost, available
     FROM menu_modifier_items
     ORDER BY modifier_id ASC'
)->fetchAll();

$itemsByGroup = [];
foreach ($itemsRaw as $item) {
    $gid = (int)$item['group_id'];
    $itemsByGroup[$gid][] = [
        'modifier_id' => (int)$item['modifier_id'],
        'group_id' => $gid,
        'name' => $item['name'],
        'extra_price' => (int)$item['extra_price'],
        'extra_cost' => (int)$item['extra_cost'],
        'available' => (int)$item['available'],
    ];
}

$groupsByMenu = [];
foreach ($groupsRaw as $grp) {
    $mid = (int)$grp['menu_id'];
    $gid = (int)$grp['group_id'];
    $groupsByMenu[$mid][] = [
        'group_id' => $gid,
        'menu_id' => $mid,
        'name' => $grp['name'],
        'type' => $grp['type'],
        'is_required' => (int)$grp['is_required'],
        'items' => $itemsByMenuGroup = $itemsByGroup[$gid] ?? [],
    ];
}

$pageTitle = 'Manajemen Menu';
include __DIR__ . '/includes/layout_header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#2C3E50]">Manajemen Menu & Opsi</h1>
            <p class="text-sm text-gray-600">Kelola daftar menu, harga jual, HPP, ketersediaan, serta grup opsi tambahan.</p>
        </div>
        <button type="button" id="btn-add-menu"
            class="bg-[#1B4D3E] hover:bg-[#153d31] text-white font-semibold px-4 py-2.5 rounded-lg text-sm transition self-start sm:self-auto">
            + Tambah Menu
        </button>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <div class="flex-1">
            <input type="text" id="filter-search" placeholder="Cari nama menu..."
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
        </div>
        <div class="sm:w-52">
            <select id="filter-category"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
            <input type="checkbox" id="filter-inactive" class="rounded text-[#1B4D3E] focus:ring-[#1B4D3E]">
            <span>Tampilkan menu nonaktif</span>
        </label>
    </div>

    <!-- Menu Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-[#2C3E50] font-semibold">
                    <th class="py-3 px-4">Foto</th>
                    <th class="py-3 px-4">Nama Menu</th>
                    <th class="py-3 px-4">Kategori</th>
                    <th class="py-3 px-4 text-right">Harga Jual</th>
                    <th class="py-3 px-4 text-right">HPP</th>
                    <th class="py-3 px-4 text-right">Margin</th>
                    <th class="py-3 px-4 text-center">Ketersediaan</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody id="menu-table-body" class="divide-y divide-gray-100">
                <?php foreach ($menus as $m):
                    $mid = (int)$m['menu_id'];
                    $price = (int)$m['price'];
                    $cost = (int)$m['cost_price'];
                    $margin = $price - $cost;
                    $groups = $groupsByMenu[$mid] ?? [];
                    $hasBrokenMandatory = false;
                    foreach ($groups as $g) {
                        if ((int)$g['is_required'] === 1) {
                            $availCount = 0;
                            foreach ($g['items'] as $gi) {
                                if ((int)$gi['available'] === 1) {
                                    $availCount++;
                                }
                            }
                            if ($availCount === 0) {
                                $hasBrokenMandatory = true;
                                break;
                            }
                        }
                    }
                ?>
                <tr class="menu-row hover:bg-gray-50/70 <?= (int)$m['status'] === 0 ? 'opacity-60 bg-gray-50' : '' ?>"
                    data-name="<?= e(mb_strtolower($m['name'])) ?>"
                    data-category="<?= e($m['category']) ?>"
                    data-status="<?= (int)$m['status'] ?>">
                    <td class="py-3 px-4">
                        <?php if (!empty($m['photo'])): ?>
                            <img src="<?= e($m['photo']) ?>" alt="<?= e($m['name']) ?>"
                                class="w-12 h-12 rounded-lg object-cover border border-gray-200">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-lg bg-emerald-50 text-[#1B4D3E] flex items-center justify-center font-bold text-xs border border-emerald-100">
                                FOTO
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 font-semibold text-[#2C3E50]">
                        <div><?= e($m['name']) ?></div>
                        <?php if ($hasBrokenMandatory): ?>
                            <span class="inline-block mt-1 text-[11px] bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-medium">
                                Peringatan: Grup wajib tidak memiliki item tersedia!
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4">
                        <span class="bg-gray-100 text-gray-700 text-xs font-medium px-2.5 py-1 rounded-full">
                            <?= e($m['category']) ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right font-medium"><?= e(formatRupiah($price)) ?></td>
                    <td class="py-3 px-4 text-right text-gray-600"><?= e(formatRupiah($cost)) ?></td>
                    <td class="py-3 px-4 text-right font-semibold <?= $margin < 0 ? 'text-red-600' : 'text-emerald-700' ?>">
                        <?= e(formatRupiah($margin)) ?>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <button type="button"
                            class="btn-toggle-avail px-2.5 py-1 rounded-full text-xs font-semibold transition <?= (int)$m['available'] === 1 ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-red-100 text-red-700 hover:bg-red-200' ?>"
                            data-menu-id="<?= $mid ?>"
                            data-available="<?= (int)$m['available'] ?>">
                            <?= (int)$m['available'] === 1 ? 'Tersedia' : 'Habis' ?>
                        </button>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= (int)$m['status'] === 1 ? 'bg-blue-100 text-blue-800' : 'bg-gray-200 text-gray-700' ?>">
                            <?= (int)$m['status'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right whitespace-nowrap space-x-1">
                        <button type="button"
                            class="btn-manage-modifiers px-2.5 py-1.5 rounded bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-semibold border border-amber-200"
                            data-menu-id="<?= $mid ?>">
                            Pilihan (<?= count($groups) ?>)
                        </button>
                        <button type="button"
                            class="btn-edit-menu px-2.5 py-1.5 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold"
                            data-menu='<?= e(json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
                            Ubah
                        </button>
                        <button type="button"
                            class="btn-toggle-status px-2.5 py-1.5 rounded text-xs font-semibold <?= (int)$m['status'] === 1 ? 'bg-red-50 hover:bg-red-100 text-red-700' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700' ?>"
                            data-menu-id="<?= $mid ?>"
                            data-next-status="<?= (int)$m['status'] === 1 ? 0 : 1 ?>">
                            <?= (int)$m['status'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / Edit Menu Modal -->
<div id="menu-modal" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="bg-[#1B4D3E] text-white px-5 py-3.5 flex justify-between items-center">
            <h3 id="menu-modal-title" class="font-bold text-base">Tambah Menu</h3>
            <button type="button" id="menu-modal-close" class="text-white/80 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <form id="menu-form" class="p-5 space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="action" id="form-action" value="menu_create">
            <input type="hidden" name="menu_id" id="form-menu-id" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Nama Menu <span class="text-red-600">*</span></label>
                    <input type="text" name="name" id="form-name" required maxlength="100"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1B4D3E] focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Kategori <span class="text-red-600">*</span></label>
                    <input type="text" name="category" id="form-category" list="category-list" required maxlength="30" value="Lainnya"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1B4D3E] focus:outline-none">
                    <datalist id="category-list">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Foto Menu (JPEG/PNG, maks 1 MB)</label>
                    <input type="file" name="photo" id="form-photo" accept="image/jpeg,image/png"
                        class="w-full text-xs text-gray-600 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-emerald-50 file:text-[#1B4D3E] file:font-semibold">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Harga Jual (Rp) <span class="text-red-600">*</span></label>
                    <input type="number" name="price" id="form-price" required min="0" step="1" value="0"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1B4D3E] focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Harga Pokok / HPP (Rp) <span class="text-red-600">*</span></label>
                    <input type="number" name="cost_price" id="form-cost" required min="0" step="1" value="0"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#1B4D3E] focus:outline-none">
                </div>
            </div>

            <div id="cost-warning" class="hidden text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-2.5 font-medium">
                Peringatan: Harga pokok (HPP) lebih besar dari harga jual!
            </div>

            <div class="flex items-center gap-6 pt-1">
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="available" id="form-available" value="1" checked class="rounded text-[#1B4D3E]">
                    <span>Tersedia (Bisa dipesan)</span>
                </label>
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="status" id="form-status" value="1" checked class="rounded text-[#1B4D3E]">
                    <span>Status Aktif</span>
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t">
                <button type="button" id="menu-modal-cancel"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium hover:bg-gray-50">Batal</button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-[#1B4D3E] hover:bg-[#153d31] text-white text-sm font-semibold">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modifier Management Modal -->
<div id="modifier-manage-modal" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="bg-[#1B4D3E] text-white px-5 py-3.5 flex justify-between items-center">
            <div>
                <h3 id="mod-modal-title" class="font-bold text-base">Kelola Pilihan Menu</h3>
                <p class="text-xs text-emerald-100">Maksimal 5 grup per menu, dan maksimal 10 item per grup.</p>
            </div>
            <button type="button" id="mod-modal-close" class="text-white/80 hover:text-white text-2xl leading-none">&times;</button>
        </div>

        <div class="p-5 overflow-y-auto flex-1 space-y-5">
            <!-- Add Group Form -->
            <form id="form-add-group" class="bg-gray-50 border border-gray-200 rounded-lg p-3.5 flex flex-wrap items-end gap-3">
                <input type="hidden" name="menu_id" id="mod-menu-id" value="">
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Grup Baru</label>
                    <input type="text" name="name" id="new-group-name" required maxlength="60" placeholder="Misal: Tingkat Pedas"
                        class="w-full border border-gray-300 rounded px-2.5 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tipe Pilihan</label>
                    <select name="type" id="new-group-type" class="border border-gray-300 rounded px-2.5 py-1.5 text-sm">
                        <option value="single">Satu Pilihan (Radio)</option>
                        <option value="multiple">Banyak Pilihan (Checkbox)</option>
                    </select>
                </div>
                <label class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-700 pb-2 cursor-pointer">
                    <input type="checkbox" name="is_required" id="new-group-req" value="1" class="rounded text-[#1B4D3E]">
                    <span>Wajib dipilih</span>
                </label>
                <button type="submit"
                    class="bg-[#1B4D3E] hover:bg-[#153d31] text-white text-xs font-semibold px-3.5 py-2 rounded transition">
                    + Tambah Grup
                </button>
            </form>

            <!-- Groups Container -->
            <div id="mod-groups-list" class="space-y-4"></div>
        </div>
    </div>
</div>

<script>
const GROUPS_BY_MENU = <?= json_encode($groupsByMenu, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('filter-search');
    const catSelect = document.getElementById('filter-category');
    const showInactive = document.getElementById('filter-inactive');
    const rows = document.querySelectorAll('.menu-row');

    function applyTableFilters() {
        const q = searchInput.value.trim().toLowerCase();
        const cat = catSelect.value;
        const incInactive = showInactive.checked;

        rows.forEach(row => {
            const matchName = row.dataset.name.includes(q);
            const matchCat = !cat || row.dataset.category === cat;
            const matchStatus = incInactive || row.dataset.status === '1';
            row.style.display = (matchName && matchCat && matchStatus) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', applyTableFilters);
    catSelect.addEventListener('change', applyTableFilters);
    showInactive.addEventListener('change', applyTableFilters);
    applyTableFilters();

    // Cost > Price warning
    const priceInput = document.getElementById('form-price');
    const costInput = document.getElementById('form-cost');
    const costWarning = document.getElementById('cost-warning');
    function checkMarginWarning() {
        const p = Number(priceInput.value || 0);
        const c = Number(costInput.value || 0);
        costWarning.classList.toggle('hidden', !(c > p));
    }
    priceInput.addEventListener('input', checkMarginWarning);
    costInput.addEventListener('input', checkMarginWarning);

    // Open Add/Edit Menu Modal
    const menuModal = document.getElementById('menu-modal');
    const menuForm = document.getElementById('menu-form');

    document.getElementById('btn-add-menu').addEventListener('click', () => {
        menuForm.reset();
        document.getElementById('menu-modal-title').textContent = 'Tambah Menu Baru';
        document.getElementById('form-action').value = 'menu_create';
        document.getElementById('form-menu-id').value = '';
        document.getElementById('form-category').value = 'Lainnya';
        document.getElementById('form-available').checked = true;
        document.getElementById('form-status').checked = true;
        checkMarginWarning();
        menuModal.classList.remove('hidden');
    });

    document.querySelectorAll('.btn-edit-menu').forEach(btn => {
        btn.addEventListener('click', () => {
            const m = JSON.parse(btn.dataset.menu);
            menuForm.reset();
            document.getElementById('menu-modal-title').textContent = 'Ubah Menu: ' + m.name;
            document.getElementById('form-action').value = 'menu_update';
            document.getElementById('form-menu-id').value = m.menu_id;
            document.getElementById('form-name').value = m.name;
            document.getElementById('form-category').value = m.category;
            document.getElementById('form-price').value = m.price;
            document.getElementById('form-cost').value = m.cost_price;
            document.getElementById('form-available').checked = Number(m.available) === 1;
            document.getElementById('form-status').checked = Number(m.status) === 1;
            checkMarginWarning();
            menuModal.classList.remove('hidden');
        });
    });

    const closeMenuModal = () => menuModal.classList.add('hidden');
    document.getElementById('menu-modal-close').addEventListener('click', closeMenuModal);
    document.getElementById('menu-modal-cancel').addEventListener('click', closeMenuModal);

    // Submit Menu Form (multipart/form-data)
    menuForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(menuForm);
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        try {
            const res = await fetch('api_menu.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' },
                body: fd
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Gagal menyimpan menu.');
            }
            showToast(data.message || 'Berhasil disimpan', 'success');
            setTimeout(() => location.reload(), 400);
        } catch (err) {
            showToast(err.message, 'error');
        }
    });

    // Toggle Availability
    document.querySelectorAll('.btn-toggle-avail').forEach(btn => {
        btn.addEventListener('click', async () => {
            const menuId = Number(btn.dataset.menuId);
            const nextAvail = Number(btn.dataset.available) === 1 ? 0 : 1;
            try {
                await apiFetch('api_toggle_availability.php', {
                    method: 'POST',
                    body: JSON.stringify({ menu_id: menuId, available: nextAvail })
                });
                location.reload();
            } catch (err) {
                showToast(err.message || 'Gagal mengubah ketersediaan.', 'error');
            }
        });
    });

    // Toggle Active/Inactive Status
    document.querySelectorAll('.btn-toggle-status').forEach(btn => {
        btn.addEventListener('click', async () => {
            const menuId = Number(btn.dataset.menuId);
            const nextStatus = Number(btn.dataset.nextStatus);
            try {
                await apiFetch('api_menu.php', {
                    method: 'POST',
                    body: JSON.stringify({ action: 'menu_toggle_status', menu_id: menuId, status: nextStatus })
                });
                location.reload();
            } catch (err) {
                showToast(err.message || 'Gagal mengubah status.', 'error');
            }
        });
    });

    // Modifier Management Modal
    const modModal = document.getElementById('modifier-manage-modal');
    const modGroupsList = document.getElementById('mod-groups-list');
    let currentModMenuId = null;

    function renderModifierGroups(menuId) {
        currentModMenuId = Number(menuId);
        document.getElementById('mod-menu-id').value = currentModMenuId;
        const groups = GROUPS_BY_MENU[currentModMenuId] || [];

        if (groups.length === 0) {
            modGroupsList.innerHTML = '<p class="text-sm text-gray-500 text-center py-6">Belum ada grup opsi untuk menu ini.</p>';
            return;
        }

        modGroupsList.innerHTML = groups.map(g => {
            const availItemsCount = (g.items || []).filter(i => Number(i.available) === 1).length;
            const warnBanner = (Number(g.is_required) === 1 && availItemsCount === 0)
                ? `<div class="mt-2 text-xs bg-amber-50 border border-amber-200 text-amber-800 px-3 py-1.5 rounded">
                     Peringatan: Grup wajib ini belum memiliki item yang tersedia! Kasir tidak akan bisa menambahkan menu ini ke keranjang.
                   </div>`
                : '';

            const itemsRows = (g.items || []).map(item => `
                <div class="flex flex-wrap items-center justify-between gap-2 py-2 border-b border-gray-100 last:border-b-0 text-xs">
                    <div class="font-medium text-gray-800">${item.name}</div>
                    <div class="flex items-center gap-3">
                        <span>Harga: <strong>+${formatRupiah(item.extra_price)}</strong></span>
                        <span class="text-gray-500">HPP: +${formatRupiah(item.extra_cost)}</span>
                        <button type="button" class="btn-item-avail px-2 py-0.5 rounded font-semibold ${Number(item.available) === 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700'}"
                            data-item='${JSON.stringify(item).replace(/'/g, "&#39;")}'>
                            ${Number(item.available) === 1 ? 'Tersedia' : 'Habis'}
                        </button>
                        <button type="button" class="btn-delete-item text-red-600 hover:underline font-medium"
                            data-modifier-id="${item.modifier_id}">Hapus</button>
                    </div>
                </div>
            `).join('');

            return `
                <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold text-sm text-[#2C3E50]">${g.name}</span>
                            <span class="ml-2 text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-700">${g.type === 'single' ? 'Satu Pilihan' : 'Banyak Pilihan'}</span>
                            <span class="ml-1 text-xs px-2 py-0.5 rounded ${Number(g.is_required) === 1 ? 'bg-red-100 text-red-700 font-semibold' : 'bg-gray-100 text-gray-600'}">
                                ${Number(g.is_required) === 1 ? 'Wajib' : 'Opsional'}
                            </span>
                        </div>
                        <button type="button" class="btn-delete-group text-xs text-red-600 hover:underline font-semibold"
                            data-group-id="${g.group_id}">Hapus Grup</button>
                    </div>
                    ${warnBanner}
                    <div class="pl-2 border-l-2 border-emerald-700/30 space-y-1">
                        ${itemsRows || '<p class="text-xs text-gray-400">Belum ada item opsi.</p>'}
                    </div>
                    <form class="form-add-item flex flex-wrap items-end gap-2 pt-2 border-t border-gray-100" data-group-id="${g.group_id}">
                        <div class="flex-1 min-w-[140px]">
                            <label class="block text-[11px] text-gray-500">Nama Opsi</label>
                            <input type="text" name="name" required maxlength="60" placeholder="Misal: Level 3 / Telur"
                                class="w-full border border-gray-300 rounded px-2 py-1 text-xs">
                        </div>
                        <div class="w-28">
                            <label class="block text-[11px] text-gray-500">+Harga (Rp)</label>
                            <input type="number" name="extra_price" required min="0" value="0"
                                class="w-full border border-gray-300 rounded px-2 py-1 text-xs">
                        </div>
                        <div class="w-28">
                            <label class="block text-[11px] text-gray-500">+HPP (Rp)</label>
                            <input type="number" name="extra_cost" required min="0" value="0"
                                class="w-full border border-gray-300 rounded px-2 py-1 text-xs">
                        </div>
                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold px-3 py-1.5 rounded">
                            + Item
                        </button>
                    </form>
                </div>
            `;
        }).join('');

        // Bind group delete
        modGroupsList.querySelectorAll('.btn-delete-group').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Hapus grup opsi ini? Transaksi lama tidak terpengaruh.')) return;
                try {
                    await apiFetch('api_menu.php', {
                        method: 'POST',
                        body: JSON.stringify({ action: 'group_delete', group_id: Number(btn.dataset.groupId) })
                    });
                    location.reload();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });

        // Bind item add
        modGroupsList.querySelectorAll('.form-add-item').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const groupId = Number(form.dataset.groupId);
                const name = form.querySelector('[name="name"]').value;
                const extraPrice = Number(form.querySelector('[name="extra_price"]').value || 0);
                const extraCost = Number(form.querySelector('[name="extra_cost"]').value || 0);
                try {
                    await apiFetch('api_menu.php', {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'item_create',
                            group_id: groupId,
                            name: name,
                            extra_price: extraPrice,
                            extra_cost: extraCost,
                            available: 1
                        })
                    });
                    location.reload();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });

        // Bind item toggle available
        modGroupsList.querySelectorAll('.btn-item-avail').forEach(btn => {
            btn.addEventListener('click', async () => {
                const item = JSON.parse(btn.dataset.item);
                try {
                    await apiFetch('api_menu.php', {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'item_update',
                            modifier_id: item.modifier_id,
                            name: item.name,
                            extra_price: item.extra_price,
                            extra_cost: item.extra_cost,
                            available: Number(item.available) === 1 ? 0 : 1
                        })
                    });
                    location.reload();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });

        // Bind item delete
        modGroupsList.querySelectorAll('.btn-delete-item').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Hapus item opsi ini? Transaksi lama tidak terpengaruh.')) return;
                try {
                    await apiFetch('api_menu.php', {
                        method: 'POST',
                        body: JSON.stringify({ action: 'item_delete', modifier_id: Number(btn.dataset.modifierId) })
                    });
                    location.reload();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
    }

    document.querySelectorAll('.btn-manage-modifiers').forEach(btn => {
        btn.addEventListener('click', () => {
            renderModifierGroups(btn.dataset.menuId);
            modModal.classList.remove('hidden');
        });
    });

    document.getElementById('mod-modal-close').addEventListener('click', () => modModal.classList.add('hidden'));

    document.getElementById('form-add-group').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await apiFetch('api_menu.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'group_create',
                    menu_id: currentModMenuId,
                    name: document.getElementById('new-group-name').value,
                    type: document.getElementById('new-group-type').value,
                    is_required: document.getElementById('new-group-req').checked ? 1 : 0
                })
            });
            location.reload();
        } catch (err) {
            showToast(err.message, 'error');
        }
    });
});
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>