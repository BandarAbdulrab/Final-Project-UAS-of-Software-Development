<?php
/**
 * Admin Menu & Modifier Management UI (SRS-F03) - Figma Slides 8, 9, 10 Match
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
        'group_id'    => $gid,
        'name'        => $item['name'],
        'extra_price' => (int)$item['extra_price'],
        'extra_cost'  => (int)$item['extra_cost'],
        'available'   => (int)$item['available'],
    ];
}

$groupsByMenu = [];
foreach ($groupsRaw as $grp) {
    $mid = (int)$grp['menu_id'];
    $gid = (int)$grp['group_id'];
    $groupsByMenu[$mid][] = [
        'group_id'    => $gid,
        'menu_id'     => $mid,
        'name'        => $grp['name'],
        'type'        => $grp['type'],
        'is_required' => (int)$grp['is_required'],
        'items'       => $itemsByGroup[$gid] ?? [],
    ];
}

// Compute top KPIs for Figma Slide 8
$totalCatalogCount = count($menus);
$activeMenuCount = 0;
$soldOutCount = 0;
$menusWithVariants = 0;
$marginPctSum = 0.0;
$marginPctCount = 0;

foreach ($menus as $m) {
    $mid = (int)$m['menu_id'];
    if ((int)$m['status'] === 1) {
        $activeMenuCount++;
        if ((int)$m['available'] === 0) {
            $soldOutCount++;
        }
        $p = (int)$m['price'];
        $c = (int)$m['cost_price'];
        if ($p > 0) {
            $marginPctSum += (($p - $c) / $p) * 100.0;
            $marginPctCount++;
        }
    }
    if (!empty($groupsByMenu[$mid])) {
        $menusWithVariants++;
    }
}
$avgMarginPct = $marginPctCount > 0 ? round($marginPctSum / $marginPctCount, 1) : 0.0;
$activeRatioPct = $totalCatalogCount > 0 ? round(($activeMenuCount / $totalCatalogCount) * 100) : 0;

$pageTitle = 'Manajemen Katalog Menu & Harga - Kedai Paradise';
include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-[#1B4D3E]">Katalog Produk</span>
            <h1 class="text-2xl font-extrabold text-[#1E293B] uppercase">MANAJEMEN KATALOG MENU &amp; HARGA</h1>
        </div>
        <button type="button" id="btn-add-menu"
            class="bg-[#9E3B1B] hover:bg-[#822F14] text-white font-extrabold px-5 py-2.5 rounded-xl text-xs uppercase tracking-wider shadow-sm transition self-start sm:self-auto">
            + Tambah Menu Baru
        </button>
    </div>

    <!-- 4 KPI Summary Cards (Figma Slide 8) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6E4DD] flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Menu Aktif</span>
                <div class="text-3xl font-extrabold text-[#1E293B] mt-1"><?= $activeMenuCount ?></div>
                <span class="text-xs text-gray-500"><?= $activeRatioPct ?>% dari total katalog</span>
            </div>
            <span class="w-12 h-12 rounded-2xl bg-emerald-100 text-[#1B4D3E] flex items-center justify-center text-xl font-bold">&#127860;</span>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6E4DD] flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Rata-Rata Margin Menu</span>
                <div class="text-3xl font-extrabold text-[#1E293B] mt-1"><?= number_format($avgMarginPct, 1, ',', '.') ?>%</div>
                <span class="text-xs text-emerald-700 font-medium">Margin Kotor Katalog</span>
            </div>
            <span class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center text-xl font-bold">%</span>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6E4DD] flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Stok Kritis / Habis</span>
                <div class="text-3xl font-extrabold <?= $soldOutCount > 0 ? 'text-red-600' : 'text-[#1E293B]' ?> mt-1"><?= $soldOutCount ?></div>
                <span class="text-xs text-gray-500"><?= $soldOutCount > 0 ? 'Perlu pengadaan bahan' : 'Stok aman tersedia' ?></span>
            </div>
            <span class="w-12 h-12 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center text-xl font-bold">&#128722;</span>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6E4DD] flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Menu Dengan Varian</span>
                <div class="text-3xl font-extrabold text-[#1E293B] mt-1"><?= $menusWithVariants ?></div>
                <span class="text-xs text-gray-500">Level pedas, olahan, suhu</span>
            </div>
            <span class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-700 flex items-center justify-center text-xl font-bold">&#9776;</span>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 flex-1">
            <div class="flex-1 max-w-md">
                <input type="text" id="filter-search" placeholder="Cari nama menu..."
                    class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
            </div>
            <div class="sm:w-48">
                <select id="filter-category"
                    class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3 py-2 text-sm font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                    <option value="">Semua kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="inline-flex items-center gap-2 text-xs font-bold text-gray-700 cursor-pointer select-none">
                <input type="checkbox" id="filter-inactive" class="rounded text-[#1B4D3E] focus:ring-[#1B4D3E] w-4 h-4">
                <span>Tampilkan Menu Nonaktif</span>
            </label>
        </div>
        <span class="bg-[#F8F7F4] border border-gray-200 text-gray-600 text-xs font-bold px-3 py-1.5 rounded-full self-start sm:self-auto">
            <?= $totalCatalogCount ?> Menu Terdaftar
        </span>
    </div>

    <!-- Menu Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-[#F8F7F4] border-b border-[#E6E4DD] text-[11px] font-bold uppercase tracking-wider text-gray-500">
                        <th class="py-3.5 px-2.5">Foto</th>
                        <th class="py-3.5 px-2.5">Nama Menu</th>
                        <th class="py-3.5 px-2.5">Kategori</th>
                        <th class="py-3.5 px-2.5 text-right">Harga Jual</th>
                        <th class="py-3.5 px-2.5 text-right">HPP (Modal)</th>
                        <th class="py-3.5 px-2.5 text-right">Margin</th>
                        <th class="py-3.5 px-2.5 text-center">Pilihan</th>
                        <th class="py-3.5 px-2.5 text-center">Ketersediaan</th>
                        <th class="py-3.5 px-2.5 text-center">Status</th>
                        <th class="py-3.5 px-2.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody id="menu-table-body" class="divide-y divide-gray-100">
                    <?php foreach ($menus as $m):
                        $mid = (int)$m['menu_id'];
                        $price = (int)$m['price'];
                        $cost = (int)$m['cost_price'];
                        $margin = $price - $cost;
                        $marginPct = $price > 0 ? round(($margin / $price) * 100, 1) : 0;
                        $groups = $groupsByMenu[$mid] ?? [];
                        $groupNames = array_map(fn($g) => $g['name'], $groups);
                        $skuCode = '#KP-' . strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $m['category'] ?: 'MNU'), 0, 3)) . '-' . str_pad((string)$mid, 2, '0', STR_PAD_LEFT);
                        $photoSrc = !empty($m['photo']) ? ('uploads/' . ltrim((string)$m['photo'], '/')) : '';

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
                    <tr class="menu-row hover:bg-[#F8F7F4]/60 <?= (int)$m['status'] === 0 ? 'opacity-60 bg-gray-50/70' : '' ?>"
                        data-name="<?= e(mb_strtolower($m['name'])) ?>"
                        data-category="<?= e($m['category']) ?>"
                        data-status="<?= (int)$m['status'] ?>">
                        <td class="py-3.5 px-2.5">
                            <?php if ($photoSrc !== ''): ?>
                                <img src="<?= e($photoSrc) ?>" alt="<?= e($m['name']) ?>"
                                    class="w-12 h-12 rounded-xl object-cover border border-gray-200">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-xl bg-[#F8F7F4] text-[#1B4D3E] flex items-center justify-center font-bold text-base border border-[#E6E4DD]">
                                    <?= $m['category'] === 'Minuman' ? '&#129380;' : '&#127835;' ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-2.5">
                            <div class="font-extrabold text-[#1E293B]"><?= e($m['name']) ?></div>
                            <div class="text-[11px] font-mono text-gray-400"><?= e($skuCode) ?></div>
                            <?php if ($hasBrokenMandatory): ?>
                                <span class="inline-block mt-1 text-[10px] bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-bold">
                                    &#9888; Grup wajib tanpa item tersedia!
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-2.5">
                            <span class="whitespace-nowrap bg-[#F8F7F4] border border-gray-200 text-gray-700 text-xs font-bold px-2.5 py-1 rounded-full">
                                <?= e($m['category']) ?>
                            </span>
                        </td>
                        <td class="py-3.5 px-2.5 text-right whitespace-nowrap font-extrabold text-[#1E293B]"><?= e(formatRupiah($price)) ?></td>
                        <td class="py-3.5 px-2.5 text-right whitespace-nowrap text-gray-500 font-medium"><?= e(formatRupiah($cost)) ?></td>
                        <td class="py-3.5 px-2.5 text-right whitespace-nowrap">
                            <div class="font-extrabold <?= $margin < 0 ? 'text-red-600' : 'text-[#1B4D3E]' ?>"><?= e(formatRupiah($margin)) ?></div>
                            <div class="text-[11px] font-bold <?= $margin < 0 ? 'text-red-500' : 'text-emerald-600' ?>"><?= number_format($marginPct, 1, ',', '.') ?>%</div>
                        </td>
                        <td class="py-3.5 px-2.5 text-center">
                            <?php if (count($groups) > 0): ?>
                                <span class="inline-block whitespace-nowrap bg-emerald-100 text-[#1B4D3E] text-[11px] font-extrabold px-2.5 py-0.5 rounded-full">
                                    <?= count($groups) ?> Grup
                                </span>
                                <div class="text-[10px] text-gray-400 truncate max-w-[110px] mx-auto mt-0.5"><?= e(implode(', ', $groupNames)) ?></div>
                            <?php else: ?>
                                <span class="text-xs text-gray-300">--</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-2.5 text-center">
                            <button type="button"
                                class="btn-toggle-avail whitespace-nowrap px-3 py-1 rounded-full text-xs font-extrabold transition <?= (int)$m['available'] === 1 ? 'bg-emerald-100 text-[#1B4D3E] hover:bg-emerald-200' : 'bg-red-100 text-red-700 hover:bg-red-200' ?>"
                                data-menu-id="<?= $mid ?>"
                                data-available="<?= (int)$m['available'] ?>">
                                &bull; <?= (int)$m['available'] === 1 ? 'Tersedia' : 'Habis' ?>
                            </button>
                        </td>
                        <td class="py-3.5 px-2.5 text-center">
                            <span class="whitespace-nowrap px-2.5 py-1 rounded-full text-xs font-bold <?= (int)$m['status'] === 1 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-gray-200 text-gray-600' ?>">
                                &bull; <?= (int)$m['status'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td class="py-3.5 px-2.5 text-right whitespace-nowrap space-x-1">
                            <button type="button"
                                class="btn-manage-modifiers px-2.5 py-1.5 rounded-lg bg-orange-50 hover:bg-orange-100 text-[#9E3B1B] text-xs font-extrabold border border-orange-200"
                                data-menu-id="<?= $mid ?>"
                                data-menu-name="<?= e($m['name']) ?>"
                                data-menu-price="<?= $price ?>"
                                data-menu-cost="<?= $cost ?>">
                                &#9776; Kelola Pilihan
                            </button>
                            <button type="button"
                                class="btn-edit-menu px-2.5 py-1.5 rounded-lg bg-[#F8F7F4] hover:bg-gray-200 text-gray-700 border border-gray-200 text-xs font-bold"
                                data-menu='<?= e(json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
                                Ubah
                            </button>
                            <button type="button"
                                class="btn-toggle-status px-2.5 py-1.5 rounded-lg text-xs font-bold border <?= (int)$m['status'] === 1 ? 'bg-white hover:bg-red-50 text-gray-600 border-gray-200' : 'bg-emerald-50 hover:bg-emerald-100 text-[#1B4D3E] border-emerald-200' ?>"
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
</div>

<!-- Add / Edit Menu Modal (Figma Slide 9) -->
<div id="menu-modal" class="fixed inset-0 z-50 hidden bg-black/55 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-[#E6E4DD]">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl bg-orange-50 text-[#9E3B1B] flex items-center justify-center font-bold">&#9998;</span>
                <div>
                    <h3 id="menu-modal-title" class="font-extrabold text-base text-[#1E293B]">Ubah Menu</h3>
                    <p class="text-[11px] text-gray-400">Konfigurasi produk, harga jual, dan HPP</p>
                </div>
            </div>
            <button type="button" id="menu-modal-close" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
        </div>
        <form id="menu-form" class="p-6 space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="action" id="form-action" value="menu_create">
            <input type="hidden" name="menu_id" id="form-menu-id" value="">

            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-xs font-bold text-gray-700 mb-1">
                        <span>Nama Menu <span class="text-red-600">*</span></span>
                        <span class="text-gray-400 font-normal">Wajib diisi</span>
                    </div>
                    <input type="text" name="name" id="form-name" required maxlength="100"
                        class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[#1B4D3E] focus:outline-none">
                </div>

                <div>
                    <div class="flex justify-between text-xs font-bold text-gray-700 mb-1">
                        <span>Kategori <span class="text-red-600">*</span></span>
                        <span class="text-gray-400 font-normal">Maksimal 30 karakter</span>
                    </div>
                    <input type="text" name="category" id="form-category" list="category-list" autocomplete="off" required maxlength="30" value="Makanan"
                        class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[#1B4D3E] focus:outline-none">
                    <datalist id="category-list">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Harga Jual (Rp) <span class="text-red-600">*</span></label>
                        <input type="number" name="price" id="form-price" required min="0" step="1" value="0"
                            class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-base font-extrabold text-[#1E293B] focus:bg-white focus:ring-2 focus:ring-[#1B4D3E] focus:outline-none">
                        <span class="text-[11px] text-gray-400">Harga untuk kasir &amp; struk</span>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Harga Modal / HPP (Rp) <span class="text-red-600">*</span></label>
                        <input type="number" name="cost_price" id="form-cost" required min="0" step="1" value="0"
                            class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-base font-extrabold text-[#1E293B] focus:bg-white focus:ring-2 focus:ring-[#1B4D3E] focus:outline-none">
                        <span class="text-[11px] text-gray-400">Estimasi biaya bahan pokok</span>
                    </div>
                </div>

                <div id="cost-warning" class="hidden text-xs bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-3.5 space-y-1">
                    <div class="font-extrabold uppercase text-amber-900">&#9888; Peringatan Margin Negatif</div>
                    <p>Harga jual lebih rendah dari harga modal (HPP). Menjual menu ini akan mengakibatkan kerugian pada laporan margin kotor harian.</p>
                </div>

                <div>
                    <div class="flex justify-between text-xs font-bold text-gray-700 mb-1">
                        <span>Foto Menu</span>
                        <span class="text-gray-400 font-normal">JPG/PNG, maksimal 1 MB</span>
                    </div>
                    <input type="file" name="photo" id="form-photo" accept="image/jpeg,image/png"
                        class="w-full text-xs text-gray-600 bg-[#F8F7F4] border border-gray-300 rounded-xl p-2 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-[#1B4D3E] file:text-white file:font-bold">
                </div>

                <div class="grid grid-cols-2 gap-3 pt-1">
                    <label class="flex items-center justify-between bg-[#F8F7F4] border border-gray-200 rounded-xl p-3 cursor-pointer">
                        <div>
                            <div class="text-xs font-extrabold text-gray-800">Tersedia</div>
                            <div class="text-[10px] text-gray-400">Tampil di layar kasir</div>
                        </div>
                        <input type="checkbox" name="available" id="form-available" value="1" checked class="rounded text-[#1B4D3E] w-4 h-4">
                    </label>
                    <label class="flex items-center justify-between bg-[#F8F7F4] border border-gray-200 rounded-xl p-3 cursor-pointer">
                        <div>
                            <div class="text-xs font-extrabold text-gray-800">Aktif</div>
                            <div class="text-[10px] text-gray-400">Termasuk katalog</div>
                        </div>
                        <input type="checkbox" name="status" id="form-status" value="1" checked class="rounded text-[#1B4D3E] w-4 h-4">
                    </label>
                </div>
            </div>

            <div class="flex justify-between gap-3 pt-4 border-t border-gray-100">
                <button type="button" id="menu-modal-cancel"
                    class="px-5 py-2.5 rounded-xl border border-gray-300 text-xs font-bold text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="submit"
                    class="px-6 py-2.5 rounded-xl bg-[#9E3B1B] hover:bg-[#822F14] text-white text-xs font-extrabold uppercase tracking-wider shadow-sm transition">&#128190; Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modifier Management Modal (Figma Slide 10) -->
<div id="modifier-manage-modal" class="fixed inset-0 z-50 hidden bg-black/55 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden border border-[#E6E4DD]">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-[#F8F7F4]">
            <div>
                <h3 id="mod-modal-title" class="font-extrabold text-base text-[#1E293B] uppercase">KELOLA PILIHAN &amp; MODIFIERS</h3>
                <p id="mod-modal-subtitle" class="text-xs text-gray-500 mt-0.5">Batas: Maksimal 5 Grup per Menu, Maksimal 10 Pilihan per Grup</p>
            </div>
            <button type="button" id="mod-modal-close" class="text-gray-400 hover:text-gray-700 text-2xl leading-none font-bold">&times;</button>
        </div>

        <div class="p-6 overflow-y-auto flex-1 space-y-5">
            <!-- Add Group Form -->
            <form id="form-add-group" class="bg-[#F8F7F4] border border-[#E6E4DD] rounded-xl p-4 flex flex-wrap items-end gap-3">
                <input type="hidden" name="menu_id" id="mod-menu-id" value="">
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nama Grup Baru</label>
                    <input type="text" name="name" id="new-group-name" required maxlength="60" placeholder="Misal: Tingkat Pedas"
                        class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Tipe Pilihan</label>
                    <select name="type" id="new-group-type" class="bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm font-semibold">
                        <option value="single">Single Choice (Pilih 1)</option>
                        <option value="multiple">Multi Choice (Bebas)</option>
                    </select>
                </div>
                <label class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-700 pb-2.5 cursor-pointer">
                    <input type="checkbox" name="is_required" id="new-group-req" value="1" class="rounded text-[#1B4D3E] w-4 h-4">
                    <span>Wajib Diisi</span>
                </label>
                <button type="submit"
                    class="bg-[#1B4D3E] hover:bg-[#153d31] text-white text-xs font-extrabold px-4 py-2.5 rounded-xl transition">
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

function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

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

    const menuModal = document.getElementById('menu-modal');
    const menuForm = document.getElementById('menu-form');

    document.getElementById('btn-add-menu').addEventListener('click', () => {
        menuForm.reset();
        document.getElementById('menu-modal-title').textContent = 'Tambah Menu Baru';
        document.getElementById('form-action').value = 'menu_create';
        document.getElementById('form-menu-id').value = '';
        document.getElementById('form-category').value = 'Makanan';
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

    // --- Modifier Management Modal (Stay-open + Inline Edit for Groups & Items) ---
    const modModal = document.getElementById('modifier-manage-modal');
    const modGroupsList = document.getElementById('mod-groups-list');
    let currentModMenu = { id: null, name: '', price: 0, cost: 0 };
    let modModalDirty = false;

    function closeModifierModal() {
        modModal.classList.add('hidden');
        if (modModalDirty) {
            location.reload();
        }
    }

    function applyUpdatedGroups(groups, toastMsg) {
        GROUPS_BY_MENU[currentModMenu.id] = groups || [];
        modModalDirty = true;
        renderModifierGroups();
        if (toastMsg) showToast(toastMsg, 'success');
    }

    function renderModifierGroups() {
        const groups = GROUPS_BY_MENU[currentModMenu.id] || [];
        document.getElementById('mod-menu-id').value = currentModMenu.id;
        document.getElementById('mod-modal-title').textContent = 'KELOLA PILIHAN & MODIFIERS: ' + (currentModMenu.name || '');
        document.getElementById('mod-modal-subtitle').textContent =
            `Harga Dasar: ${formatRupiah(Number(currentModMenu.price || 0))} | HPP: ${formatRupiah(Number(currentModMenu.cost || 0))} • ${groups.length}/5 Grup Terpakai`;

        if (groups.length === 0) {
            modGroupsList.innerHTML = '<p class="text-sm text-gray-500 text-center py-6 bg-[#F8F7F4] rounded-xl">Belum ada grup opsi untuk menu ini.</p>';
            return;
        }

        modGroupsList.innerHTML = groups.map((g, idx) => {
            const availItemsCount = (g.items || []).filter(i => Number(i.available) === 1).length;
            const warnBanner = (Number(g.is_required) === 1 && availItemsCount === 0)
                ? `<div class="mt-2 text-xs bg-amber-50 border border-amber-200 text-amber-800 px-3 py-1.5 rounded-lg font-medium">
                     &#9888; Peringatan: Grup wajib ini belum memiliki item yang tersedia!
                   </div>`
                : '';

            const itemsRows = (g.items || []).map(item => `
                <div class="bg-[#F8F7F4] rounded-xl px-3 py-2.5 text-xs space-y-2" data-item-wrapper="${item.modifier_id}">
                    <div class="flex flex-wrap items-center justify-between gap-2 item-view-row">
                        <div class="font-bold text-gray-800">${escHtml(item.name)}</div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span>Harga Tambahan: <strong>+${formatRupiah(item.extra_price)}</strong></span>
                            <span class="text-gray-500">HPP: +${formatRupiah(item.extra_cost)}</span>
                            <button type="button" class="btn-item-avail px-2.5 py-0.5 rounded-full font-extrabold ${Number(item.available) === 1 ? 'bg-emerald-100 text-[#1B4D3E]' : 'bg-red-100 text-red-700'}"
                                data-item='${escHtml(JSON.stringify(item))}'>
                                ${Number(item.available) === 1 ? 'Tersedia' : 'Habis'}
                            </button>
                            <button type="button" class="btn-toggle-edit-item text-[#1B4D3E] hover:underline font-bold"
                                data-modifier-id="${item.modifier_id}">&#9998; Ubah</button>
                            <button type="button" class="btn-delete-item text-red-600 hover:underline font-bold"
                                data-modifier-id="${item.modifier_id}">&#128465; Hapus</button>
                        </div>
                    </div>
                    <form class="form-edit-item hidden flex flex-wrap items-end gap-2 pt-2 border-t border-gray-200"
                          data-modifier-id="${item.modifier_id}" data-available="${Number(item.available)}">
                        <div class="flex-1 min-w-[140px]">
                            <label class="block text-[10px] font-bold text-gray-500 mb-0.5">Nama Pilihan</label>
                            <input type="text" name="name" required maxlength="60" value="${escHtml(item.name)}"
                                class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs">
                        </div>
                        <div class="w-24">
                            <label class="block text-[10px] font-bold text-gray-500 mb-0.5">+Harga (Rp)</label>
                            <input type="number" name="extra_price" required min="0" value="${Number(item.extra_price)}"
                                class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs">
                        </div>
                        <div class="w-24">
                            <label class="block text-[10px] font-bold text-gray-500 mb-0.5">+HPP (Rp)</label>
                            <input type="number" name="extra_cost" required min="0" value="${Number(item.extra_cost)}"
                                class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs">
                        </div>
                        <div class="flex gap-1.5">
                            <button type="submit" class="bg-[#1B4D3E] hover:bg-[#153d31] text-white text-xs font-bold px-3 py-1.5 rounded-lg">Simpan</button>
                            <button type="button" class="btn-cancel-edit-item bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-bold px-2.5 py-1.5 rounded-lg">Batal</button>
                        </div>
                    </form>
                </div>
            `).join('');

            return `
                <div class="border border-[#E6E4DD] rounded-2xl p-4 space-y-3 bg-white shadow-sm" data-group-wrapper="${g.group_id}">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="w-6 h-6 rounded-full bg-[#1B4D3E] text-white text-xs font-extrabold flex items-center justify-center">${idx + 1}</span>
                            <span class="font-extrabold text-sm text-[#1E293B]">GRUP ${idx + 1}: ${escHtml(g.name)}</span>
                            <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-700">${g.type === 'single' ? 'Single Choice (Pilih 1)' : 'Multi Choice (Bebas)'}</span>
                            <span class="text-[11px] font-extrabold px-2.5 py-0.5 rounded-full ${Number(g.is_required) === 1 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'}">
                                ${Number(g.is_required) === 1 ? 'Wajib Diisi' : 'Opsional'}
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" class="btn-toggle-edit-group text-xs text-[#1B4D3E] hover:underline font-bold"
                                data-group-id="${g.group_id}">&#9998; Ubah Grup</button>
                            <button type="button" class="btn-delete-group text-xs text-red-600 hover:underline font-bold"
                                data-group-id="${g.group_id}">Hapus Grup</button>
                        </div>
                    </div>

                    <form class="form-edit-group hidden bg-[#F8F7F4] border border-gray-200 rounded-xl p-3 flex flex-wrap items-end gap-2.5" data-group-id="${g.group_id}">
                        <div class="flex-1 min-w-[160px]">
                            <label class="block text-[11px] font-bold text-gray-600 mb-0.5">Nama Grup</label>
                            <input type="text" name="name" required maxlength="60" value="${escHtml(g.name)}"
                                class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 mb-0.5">Tipe</label>
                            <select name="type" class="bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold">
                                <option value="single" ${g.type === 'single' ? 'selected' : ''}>Single Choice (Pilih 1)</option>
                                <option value="multiple" ${g.type === 'multiple' ? 'selected' : ''}>Multi Choice (Bebas)</option>
                            </select>
                        </div>
                        <label class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-700 pb-1.5 cursor-pointer">
                            <input type="checkbox" name="is_required" value="1" ${Number(g.is_required) === 1 ? 'checked' : ''} class="rounded text-[#1B4D3E] w-4 h-4">
                            <span>Wajib Diisi</span>
                        </label>
                        <div class="flex gap-1.5">
                            <button type="submit" class="bg-[#1B4D3E] hover:bg-[#153d31] text-white text-xs font-bold px-3 py-1.5 rounded-lg">Simpan</button>
                            <button type="button" class="btn-cancel-edit-group bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-bold px-2.5 py-1.5 rounded-lg">Batal</button>
                        </div>
                    </form>

                    ${warnBanner}
                    <div class="space-y-1.5">
                        ${itemsRows || '<p class="text-xs text-gray-400">Belum ada item opsi.</p>'}
                    </div>
                    <form class="form-add-item flex flex-wrap items-end gap-2 pt-3 border-t border-gray-100" data-group-id="${g.group_id}">
                        <div class="flex-1 min-w-[140px]">
                            <label class="block text-[11px] font-bold text-gray-500 mb-0.5">Nama Pilihan Baru</label>
                            <input type="text" name="name" required maxlength="60" placeholder="Misal: Level 3 / Telur Tambahan"
                                class="w-full bg-[#F8F7F4] border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs">
                        </div>
                        <div class="w-28">
                            <label class="block text-[11px] font-bold text-gray-500 mb-0.5">+Harga (Rp)</label>
                            <input type="number" name="extra_price" required min="0" value="0"
                                class="w-full bg-[#F8F7F4] border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs">
                        </div>
                        <div class="w-28">
                            <label class="block text-[11px] font-bold text-gray-500 mb-0.5">+HPP (Rp)</label>
                            <input type="number" name="extra_cost" required min="0" value="0"
                                class="w-full bg-[#F8F7F4] border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs">
                        </div>
                        <button type="submit" class="bg-[#9E3B1B] hover:bg-[#822F14] text-white text-xs font-extrabold px-3.5 py-2 rounded-lg">
                            + Tambah Pilihan Item
                        </button>
                    </form>
                </div>
            `;
        }).join('');

        // Toggle & submit inline Group Edit
        modGroupsList.querySelectorAll('.btn-toggle-edit-group').forEach(btn => {
            btn.addEventListener('click', () => {
                const wrapper = btn.closest('[data-group-wrapper]');
                const form = wrapper.querySelector('.form-edit-group');
                form.classList.toggle('hidden');
            });
        });
        modGroupsList.querySelectorAll('.btn-cancel-edit-group').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('.form-edit-group').classList.add('hidden');
            });
        });
        modGroupsList.querySelectorAll('.form-edit-group').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                try {
                    const res = await apiFetch('api_menu.php', {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'group_update',
                            group_id: Number(form.dataset.groupId),
                            name: form.querySelector('[name="name"]').value,
                            type: form.querySelector('[name="type"]').value,
                            is_required: form.querySelector('[name="is_required"]').checked ? 1 : 0
                        })
                    });
                    applyUpdatedGroups(res.groups, res.message || 'Grup diperbarui.');
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });

        // Delete Group
        modGroupsList.querySelectorAll('.btn-delete-group').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Hapus grup opsi ini? Transaksi lama tidak terpengaruh.')) return;
                try {
                    const res = await apiFetch('api_menu.php', {
                        method: 'POST',
                        body: JSON.stringify({ action: 'group_delete', group_id: Number(btn.dataset.groupId) })
                    });
                    applyUpdatedGroups(res.groups, res.message || 'Grup dihapus.');
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });

        // Add Item
        modGroupsList.querySelectorAll('.form-add-item').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const groupId = Number(form.dataset.groupId);
                const name = form.querySelector('[name="name"]').value;
                const extraPrice = Number(form.querySelector('[name="extra_price"]').value || 0);
                const extraCost = Number(form.querySelector('[name="extra_cost"]').value || 0);
                try {
                    const res = await apiFetch('api_menu.php', {
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
                    applyUpdatedGroups(res.groups, res.message || 'Item opsi ditambahkan.');
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });

        // Toggle & submit inline Item Edit
        modGroupsList.querySelectorAll('.btn-toggle-edit-item').forEach(btn => {
            btn.addEventListener('click', () => {
                const wrapper = btn.closest('[data-item-wrapper]');
                wrapper.querySelector('.form-edit-item').classList.toggle('hidden');
            });
        });
        modGroupsList.querySelectorAll('.btn-cancel-edit-item').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('.form-edit-item').classList.add('hidden');
            });
        });
        modGroupsList.querySelectorAll('.form-edit-item').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                try {
                    const res = await apiFetch('api_menu.php', {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'item_update',
                            modifier_id: Number(form.dataset.modifierId),
                            name: form.querySelector('[name="name"]').value,
                            extra_price: Number(form.querySelector('[name="extra_price"]').value || 0),
                            extra_cost: Number(form.querySelector('[name="extra_cost"]').value || 0),
                            available: Number(form.dataset.available)
                        })
                    });
                    applyUpdatedGroups(res.groups, res.message || 'Item opsi diperbarui.');
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });

        // Toggle Item Availability
        modGroupsList.querySelectorAll('.btn-item-avail').forEach(btn => {
            btn.addEventListener('click', async () => {
                const item = JSON.parse(btn.dataset.item);
                try {
                    const res = await apiFetch('api_menu.php', {
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
                    applyUpdatedGroups(res.groups, res.message || 'Status opsi diperbarui.');
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });

        // Delete Item
        modGroupsList.querySelectorAll('.btn-delete-item').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Hapus item opsi ini? Transaksi lama tidak terpengaruh.')) return;
                try {
                    const res = await apiFetch('api_menu.php', {
                        method: 'POST',
                        body: JSON.stringify({ action: 'item_delete', modifier_id: Number(btn.dataset.modifierId) })
                    });
                    applyUpdatedGroups(res.groups, res.message || 'Item opsi dihapus.');
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
    }

    document.querySelectorAll('.btn-manage-modifiers').forEach(btn => {
        btn.addEventListener('click', () => {
            currentModMenu = {
                id: Number(btn.dataset.menuId),
                name: btn.dataset.menuName || '',
                price: Number(btn.dataset.menuPrice || 0),
                cost: Number(btn.dataset.menuCost || 0)
            };
            modModalDirty = false;
            renderModifierGroups();
            modModal.classList.remove('hidden');
        });
    });

    document.getElementById('mod-modal-close').addEventListener('click', closeModifierModal);
    modModal.addEventListener('click', (e) => {
        if (e.target === modModal) closeModifierModal();
    });

    document.getElementById('form-add-group').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const res = await apiFetch('api_menu.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'group_create',
                    menu_id: currentModMenu.id,
                    name: document.getElementById('new-group-name').value,
                    type: document.getElementById('new-group-type').value,
                    is_required: document.getElementById('new-group-req').checked ? 1 : 0
                })
            });
            document.getElementById('new-group-name').value = '';
            document.getElementById('new-group-req').checked = false;
            applyUpdatedGroups(res.groups, res.message || 'Grup opsi ditambahkan.');
        } catch (err) {
            showToast(err.message, 'error');
        }
    });
});
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
