/**
 * pos.js
 * Pengendali Modal Pemilihan Modifier, Kalkulasi Harga Satuan Efektif (BR-02),
 * dan Keranjang Belanja Reaktif + Penggabungan Baris Identik (BR-17, BR-20).
 */

(function () {
  "use strict";

  let cart = [];
  let activeModalMenu = null;

  // Utilitas escape HTML untuk mencegah XSS saat merender data dinamis
  function escapeHtml(str) {
    if (str === null || str === undefined) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // Membangun struktur DOM Modal Pilihan Modifier jika belum tersedia di pos.php
  function ensureModalExists() {
    if (document.getElementById("modifier-modal")) return;

    const modalHtml = `
            <div id="modifier-modal" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                    <div class="bg-[#1B4D3E] text-white px-5 py-3.5 flex justify-between items-center">
                        <div>
                            <h3 id="modal-menu-title" class="font-bold text-base">Pilihan Menu</h3>
                            <p id="modal-base-price" class="text-xs text-emerald-100">Harga dasar: Rp0</p>
                        </div>
                        <button type="button" id="modal-close-btn" class="text-white/80 hover:text-white text-2xl leading-none font-bold">&times;</button>
                    </div>
                    <div id="modal-groups-body" class="p-5 overflow-y-auto space-y-4 flex-1"></div>
                    <div class="bg-gray-50 border-t px-5 py-3.5 flex items-center justify-between gap-3">
                        <div>
                            <span class="block text-xs text-gray-500">Harga Satuan</span>
                            <span id="modal-effective-price" class="text-lg font-bold text-[#1B4D3E]">Rp0</span>
                        </div>
                        <button type="button" id="modal-add-btn" disabled
                            class="bg-[#1B4D3E] hover:bg-[#153d31] disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold text-sm px-4 py-2.5 rounded-lg transition">
                            Tambah ke Keranjang
                        </button>
                    </div>
                </div>
            </div>
        `;
    document.body.insertAdjacentHTML("beforeend", modalHtml);

    document
      .getElementById("modal-close-btn")
      .addEventListener("click", closeModifierModal);
    document
      .getElementById("modifier-modal")
      .addEventListener("click", function (e) {
        if (e.target === this) closeModifierModal();
      });
    document
      .getElementById("modal-add-btn")
      .addEventListener("click", handleModalConfirm);
  }

  function closeModifierModal() {
    const modal = document.getElementById("modifier-modal");
    if (modal) modal.classList.add("hidden");
    activeModalMenu = null;
  }

  // Membuka modal dan merender grup single (radio) & multiple (checkbox)
  function openModifierModal(data, fallbackMenuName) {
    ensureModalExists();

    const menuName = data.menu_name || fallbackMenuName || "Menu";
    activeModalMenu = {
      menu_id: Number(data.menu_id),
      name: menuName,
      base_price: Number(data.base_price),
      groups: data.groups || [],
    };

    document.getElementById("modal-menu-title").textContent = menuName;
    document.getElementById("modal-base-price").textContent =
      "Harga dasar: " + formatRupiah(activeModalMenu.base_price);

    const body = document.getElementById("modal-groups-body");
    body.innerHTML = "";

    if (activeModalMenu.groups.length === 0) {
      body.innerHTML =
        '<p class="text-sm text-gray-500">Tidak ada pilihan tambahan untuk menu ini.</p>';
    } else {
      activeModalMenu.groups.forEach((group) => {
        const groupEl = document.createElement("div");
        groupEl.className = "border border-gray-200 rounded-lg p-3";

        const badgeHtml =
          Number(group.is_required) === 1
            ? '<span class="bg-red-100 text-red-700 text-xs font-semibold px-2 py-0.5 rounded">Wajib</span>'
            : '<span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded">Opsional</span>';

        const hintText =
          group.type === "single" ? "Pilih 1" : "Boleh pilih lebih dari 1";

        let itemsHtml = "";
        if (!group.items || group.items.length === 0) {
          itemsHtml =
            '<p class="text-xs text-red-600 mt-2">Opsi sedang habis.</p>';
        } else {
          const inputType = group.type === "single" ? "radio" : "checkbox";
          const inputName = `mod_group_${group.group_id}`;

          itemsHtml = '<div class="mt-2 space-y-1.5">';
          group.items.forEach((item) => {
            const extraLabel =
              Number(item.extra_price) > 0
                ? `<span class="text-xs font-medium text-gray-600">+${formatRupiah(item.extra_price)}</span>`
                : '<span class="text-xs text-gray-400">Gratis</span>';

            itemsHtml += `
                            <label class="flex items-center justify-between p-2 rounded border border-transparent hover:bg-gray-50 hover:border-gray-200 cursor-pointer">
                                <span class="flex items-center gap-2.5">
                                    <input type="${inputType}"
                                           name="${inputName}"
                                           value="${item.modifier_id}"
                                           data-group-id="${group.group_id}"
                                           data-name="${escapeHtml(item.name)}"
                                           data-extra-price="${Number(item.extra_price)}"
                                           class="modifier-option-input w-4 h-4 text-[#1B4D3E] focus:ring-[#1B4D3E]">
                                    <span class="text-sm text-gray-800">${escapeHtml(item.name)}</span>
                                </span>
                                ${extraLabel}
                            </label>
                        `;
          });
          itemsHtml += "</div>";
        }

        groupEl.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-semibold text-sm text-[#2C3E50]">${escapeHtml(group.name)}</h4>
                            <p class="text-[11px] text-gray-400">${hintText}</p>
                        </div>
                        ${badgeHtml}
                    </div>
                    ${itemsHtml}
                `;
        body.appendChild(groupEl);
      });
    }

    body.querySelectorAll(".modifier-option-input").forEach((input) => {
      input.addEventListener("change", updateModalState);
    });

    updateModalState();
    document.getElementById("modifier-modal").classList.remove("hidden");
  }

  // Hitung harga satuan efektif & validasi grup wajib (BR-02 & BR-17)
  function updateModalState() {
    if (!activeModalMenu) return;

    const body = document.getElementById("modal-groups-body");
    let effectivePrice = activeModalMenu.base_price;

    body.querySelectorAll(".modifier-option-input:checked").forEach((input) => {
      effectivePrice += Number(input.dataset.extraPrice || 0);
    });

    document.getElementById("modal-effective-price").textContent =
      formatRupiah(effectivePrice);

    let allMandatorySatisfied = true;
    for (const group of activeModalMenu.groups) {
      if (Number(group.is_required) === 1) {
        const checkedCount = body.querySelectorAll(
          `input[name="mod_group_${group.group_id}"]:checked`,
        ).length;
        if (checkedCount === 0) {
          allMandatorySatisfied = false;
          break;
        }
      }
    }

    document.getElementById("modal-add-btn").disabled = !allMandatorySatisfied;
  }

  // Konfirmasi pilihan modal & tambahkan ke keranjang
  function handleModalConfirm() {
    if (!activeModalMenu) return;

    const body = document.getElementById("modal-groups-body");
    const selectedModifiers = [];

    body.querySelectorAll(".modifier-option-input:checked").forEach((input) => {
      selectedModifiers.push({
        modifier_id: Number(input.value),
        name: input.dataset.name,
        extra_price: Number(input.dataset.extraPrice || 0),
      });
    });

    addToCart({
      menu_id: activeModalMenu.menu_id,
      name: activeModalMenu.name,
      base_price: activeModalMenu.base_price,
      quantity: 1,
      modifiers: selectedModifiers,
    });

    closeModifierModal();
  }

  // Menambahkan item ke keranjang dengan penggabungan baris jika menu_id & modifier_ids identik (BR-20)
  function addToCart(itemInput) {
    const sortedModifiers = [...(itemInput.modifiers || [])].sort(
      (a, b) => a.modifier_id - b.modifier_id,
    );
    const modSignature = sortedModifiers.map((m) => m.modifier_id).join(",");

    const extraTotal = sortedModifiers.reduce(
      (sum, m) => sum + Number(m.extra_price),
      0,
    );
    const unitPrice = Number(itemInput.base_price) + extraTotal;
    const qtyToAdd = Number(itemInput.quantity) || 1;

    const existingIndex = cart.findIndex((line) => {
      if (line.menu_id !== Number(itemInput.menu_id)) return false;
      const lineSignature = line.modifiers.map((m) => m.modifier_id).join(",");
      return lineSignature === modSignature;
    });

    if (existingIndex !== -1) {
      cart[existingIndex].quantity += qtyToAdd;
      cart[existingIndex].subtotal =
        cart[existingIndex].unit_price * cart[existingIndex].quantity;
    } else {
      cart.push({
        menu_id: Number(itemInput.menu_id),
        name: itemInput.name,
        base_price: Number(itemInput.base_price),
        quantity: qtyToAdd,
        modifiers: sortedModifiers,
        unit_price: unitPrice,
        subtotal: unitPrice * qtyToAdd,
      });
    }

    renderCart();
  }

  // Render ulang UI keranjang dan hitung total keseluruhan secara reaktif
  function renderCart() {
    const container =
      document.getElementById("cart-items") ||
      document.getElementById("cart-items-container");
    const totalEl = document.getElementById("cart-total");

    const grandTotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
    if (totalEl) {
      totalEl.textContent = formatRupiah(grandTotal);
    }

    if (!container) return;

    if (cart.length === 0) {
      container.innerHTML =
        '<p class="text-sm text-gray-400 text-center py-8">Keranjang masih kosong</p>';
      document.dispatchEvent(
        new CustomEvent("pos:cartUpdated", {
          detail: { cart, total: grandTotal },
        }),
      );
      return;
    }

    container.innerHTML = cart
      .map((item, idx) => {
        const modText =
          item.modifiers.length > 0
            ? `<div class="text-xs text-gray-500 pl-3 mt-0.5">[${escapeHtml(item.modifiers.map((m) => m.name).join(", "))}]</div>`
            : "";

        return `
                <div class="py-3 border-b border-gray-100 last:border-b-0">
                    <div class="flex justify-between items-start gap-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-[#2C3E50] truncate">${escapeHtml(item.name)}</p>
                            ${modText}
                        </div>
                        <span class="text-sm font-bold text-[#1B4D3E] whitespace-nowrap">${formatRupiah(item.subtotal)}</span>
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-xs text-gray-500">${formatRupiah(item.unit_price)} / porsi</span>
                        <div class="flex items-center gap-1.5">
                            <button type="button" data-action="dec" data-idx="${idx}"
                                class="w-7 h-7 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-sm flex items-center justify-center">-</button>
                            <span class="w-7 text-center text-sm font-semibold">${item.quantity}</span>
                            <button type="button" data-action="inc" data-idx="${idx}"
                                class="w-7 h-7 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-sm flex items-center justify-center">+</button>
                            <button type="button" data-action="del" data-idx="${idx}" title="Hapus"
                                class="ml-1.5 px-2 py-1 rounded bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium">Hapus</button>
                        </div>
                    </div>
                </div>
            `;
      })
      .join("");

    container.querySelectorAll("button[data-action]").forEach((btn) => {
      btn.addEventListener("click", function () {
        const idx = Number(this.dataset.idx);
        const action = this.dataset.action;

        if (action === "inc") {
          cart[idx].quantity += 1;
          cart[idx].subtotal = cart[idx].unit_price * cart[idx].quantity;
        } else if (action === "dec") {
          if (cart[idx].quantity > 1) {
            cart[idx].quantity -= 1;
            cart[idx].subtotal = cart[idx].unit_price * cart[idx].quantity;
          } else {
            cart.splice(idx, 1);
          }
        } else if (action === "del") {
          cart.splice(idx, 1);
        }

        renderCart();
      });
    });

    document.dispatchEvent(
      new CustomEvent("pos:cartUpdated", {
        detail: { cart, total: grandTotal },
      }),
    );
  }

  // Inisialisasi klik kartu menu pada katalog POS
  document.addEventListener("DOMContentLoaded", function () {
    ensureModalExists();
    renderCart();

    document.addEventListener("click", async function (e) {
      // Abaikan klik pada tombol toggle ketersediaan (Tersedia/Habis)
      if (
        e.target.closest(".toggle-availability-btn, [data-toggle-availability]")
      ) {
        return;
      }

      const card = e.target.closest(".menu-card, [data-menu-id]");
      if (!card) return;

      if (card.dataset.available === "0") {
        showToast("Menu sedang habis.", "error");
        return;
      }

      const menuId = Number(card.dataset.menuId);
      const menuName = card.dataset.name || card.dataset.menuName || "Menu";
      const basePrice = Number(
        card.dataset.price || card.dataset.basePrice || 0,
      );
      const hasModifiers = card.dataset.hasModifiers === "1";

      if (!hasModifiers) {
        addToCart({
          menu_id: menuId,
          name: menuName,
          base_price: basePrice,
          quantity: 1,
          modifiers: [],
        });
        return;
      }

      try {
        const data = await apiFetch(`api_modifiers.php?menu_id=${menuId}`);
        if (!data.groups || data.groups.length === 0) {
          addToCart({
            menu_id: menuId,
            name: menuName,
            base_price: basePrice,
            quantity: 1,
            modifiers: [],
          });
        } else {
          openModifierModal(data, menuName);
        }
      } catch (err) {
        showToast(err.message || "Gagal memuat opsi menu.", "error");
      }
    });
  });

  // API Publik untuk modul Checkout & Pembayaran (Day 5)
  window.getCart = function () {
    return cart.map((item) => ({
      ...item,
      modifier_ids: item.modifiers.map((m) => m.modifier_id),
    }));
  };

  window.getTotal = function () {
    return cart.reduce((sum, item) => sum + item.subtotal, 0);
  };

  window.clearCart = function () {
    cart = [];
    renderCart();
  };
})();
