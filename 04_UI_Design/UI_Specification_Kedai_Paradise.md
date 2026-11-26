### 1. Global Navigation & Layout Shell (`layout_header.php` & `layout_footer.php`)

Every authenticated screen renders within this structural shell.

```
+--------------------------------------------------------------------------------------------------------------------+
| [Logo] KEDAI PARADISE (Kandang Gampang)               [WIB 10:42:15] [● Online]  User: Fitri [Admin]  [Keluar (X)] |
| Nav: [Dasbor] [Kasir] [Antrian (3)] [Riwayat] [Tutup Kasir] [Menu] [Keuangan] [Laporan] [Performa Menu] [Pengguna] [Pengaturan]|
+--------------------------------------------------------------------------------------------------------------------+
| [Flash Banner: Success / Warning Messages]                                                                         |
|                                                  PAGE CONTENT AREA                                                 |
+--------------------------------------------------------------------------------------------------------------------+

```

#### Exact UI Components

* **Header Bar (Sticky Top, Dark Pine `#1B4D3E`):**
  * **Brand Identification:** Vector crest logo (36×36px), Application Title "KEDAI PARADISE", Location Tag "Kandang Gampang, Purbalingga".
  * **Live Status Cluster:** Real-time clock label (`dd/mm/yyyy hh:mm:ss WIB`), Connectivity pill indicator (Green `#22C55E` for Cloud HTTPS Online; Red `#EF4444` with pulsating dot for Outage Fallback Mode).
  * **User Profile & Session Capsule:** Operator name ("Ibu Fitri", clickable: opens `account.php` "Akun Saya" to change own password, SRS-F02), Role badge pill (`Admin` in emerald fill or `Kasir` in slate fill), Logout action button ("Keluar" with icon, red hover outline).


* **Role-Aware Navigation Menu:**
  * **Cashier Navigation Set:** `[Dasbor]` (compact cashier dashboard, SRS-F11), `[Kasir]`, `[Antrian]`, `[Riwayat]`, `[Tutup Kasir]` (read-only).
  * **Admin Navigation Set:** `[Dasbor]`, `[Kasir]`, `[Antrian]`, `[Riwayat]`, `[Tutup Kasir]`, `[Menu]`, `[Keuangan]`, `[Laporan]`, `[Performa Menu]`, `[Pengguna]`, `[Pengaturan]`.
  * **Queue Counter Badge:** Red pill badge attached to `[Antrian]` displaying active orders count (`status = 'processing'`).


* **Mobile Viewport Drawer (Breakpoints < 768px):**
  * Collapses navigation links into a slide-over drawer triggered by a standard hamburger button (`☰`, minimum touch area 44×44px).



---

### 2. Login Screen (`login.php`)

```
+------------------------------------------------------------------+
|                          KEDAI PARADISE                          |
|             Point-of-Sale & Financial Control System             |
|                                                                  |
|  +------------------------------------------------------------+  |
|  | [!] Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit |  |  <- Rate Limit Alert
|  +------------------------------------------------------------+  |
|                                                                  |
|  Username                                                        |
|  [ fitri                                                     ]   |
|                                                                  |
|  Password                                                        |
|  [ ••••••••••••••••                                      [👁] ]   |
|                                                                  |
|  [                 MASUK KE SISTEM (Login)                    ]  |  <- Forest Green
|                                                                  |
|  Keamanan: Sesi kedaluwarsa otomatis setelah 30 menit tidak aktif  |
+------------------------------------------------------------------+

```

#### Exact UI Components

* **Card Container:** Centered responsive modal box (`max-w-md`, rounded-xl, 1px slate border, drop shadow).
* **Brand Header:** Center-aligned shop crest, primary title "KEDAI PARADISE" (`text-2xl font-bold text-[#1B4D3E]`), subtitle "Sistem Kasir & Rekap Keuangan".
* **Security Feedback Alert Container:**
  * Hidden by default; rendered with red background (`bg-red-50 text-red-700 border-red-300`) upon validation failure.
  * Credential failure text: `"Username atau password salah"`.
  * Rate-limit lockout text: `"Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit."`.


* **Form Inputs:**
  * Username: `<input type="text">`, placeholder `"Masukkan username"`, autofocus, required.
  * Password: `<input type="password">`, placeholder `"••••••••"`, right-aligned eye icon button toggle to reveal plaintext.
  * Hidden Fields: CSRF token input (`<input type="hidden" name="csrf_token">`).


* **Action Button:** "MASUK KE SISTEM" (Full-width, 48px height, solid `#1B4D3E`, white text, rounded-lg, focus ring).
* **Footer Security Note:** Gray caption text: `"Koneksi terenkripsi HTTPS (TLS 1.2+). Sesi otomatis ditutup setelah 30 menit."`.

---

### 3. Cashier POS Main Screen (`pos.php`)

Two-column split view (65% Menu Catalog, 35% Active Cart) on desktop; stacked on mobile.

```
+--------------------------------------------------------+---------------------------------------------------+
|  KATALOG MENU                     [ Cari menu...   ]   |  PESANAN SAAT INI (CART)           [Kosongkan (🗑)]|
|  [Semua]  [Makanan]  [Minuman]  [Snack]                |  ------------------------------------------------ |
|                                                        |  Mie Goreng                             Rp34.000  |
|  +------------------+  +------------------+            |    [Level 3, Telur Tambahan]   [Edit]             |
|  | [Foto / Icon]    |  | [Foto / Icon]    |            |    [-]  2  [+]                          [Hapus (x)]|
|  | Mie Goreng       |  | Kopi Susu        |            |  Kopi Susu                              Rp22.000  |
|  | Rp14.000         |  | Rp18.000         |            |    [Large]                      [Edit]             |
|  | [Pilihan]        |  | [Pilihan]        |            |    [-]  1  [+]                          [Hapus (x)]|
|  | [x] Tersedia     |  | [x] Tersedia     |            |  Es Teh                                  Rp5.000  |
|  +------------------+  +------------------+            |    [-]  1  [+]                          [Hapus (x)]|
|  +------------------+  +------------------+            |  ------------------------------------------------ |
|  | [HABIS OVERLAY]  |  | [Foto / Icon]    |            |  Layanan: (o) Meja  ( ) Bawa Pulang (TA)          |
|  | Air Mineral      |  | Roti Bakar       |            |  Pilih Meja: [ Meja 05                     |v] ]  |
|  | Rp4.000          |  | Rp12.000         |            |  ------------------------------------------------ |
|  | [ ] Habis        |  | [x] Tersedia     |            |  TOTAL PEMBAYARAN:                       Rp61.000  |
|  +------------------+  +------------------+            |  Metode: [ TUNAI (Active) ]   [ QRIS ]            |
|                                                        |  Uang Diterima: [ 70.000                        ] |
|                                                        |  Pilihan Cepat: [Pas] [20k] [50k] [70k] [100k]    |
|                                                        |  Kembalian:                              Rp9.000  |
|                                                        |  +---------------------------------------------+  |
|                                                        |  |       SELESAIKAN TRANSAKSI (Rp61.000)       |  |
|                                                        |  +---------------------------------------------+  |
+--------------------------------------------------------+---------------------------------------------------+

```

#### Exact UI Components

* **Left Panel: Catalog Grid Toolbar:**
  * Search Input: Full-width text input with magnifying glass prefix icon (`Cari nama makanan atau minuman...`), filtering client-side on menu name.
  * Category Pills: `[Semua]` plus one pill per DISTINCT `menu.category` among active menu items (for example `[Makanan]`, `[Minuman]`, `[Snack]`; generated from data, not hard-coded). Filtering is client-side and combines with the search box. Items with the default category show under `[Lainnya]`.


* **Menu Card Components:**
  * Dimensions: Fixed ratio card, rounded-lg, 1px border, white background.
  * Image Container: 120px height thumbnail or styled cutlery/cup fallback SVG.
  * Metadata: Title (`font-bold text-gray-800`), Base Price (`text-emerald-700 font-semibold`).
  * Badges: Amber pill `"Pilihan"` positioned top-right if linked to `menu_modifier_groups`.
  * Availability Toggle Switch: Miniature toggle labeled `"Tersedia"` (green) / `"Habis"` (gray/red) for real-time stock toggling.
  * Sold Out State: Dark overlay (`opacity-75 bg-gray-200`) with diagonal red banner `"HABIS"`; pointer events disabled.


* **Right Panel: Live Cart Container:**
  * Header: Title `"Pesanan Saat Ini"`, active row count badge, `"Kosongkan"` trash-bin button with confirmation prompt.
  * Scrollable Item List: Container with internal vertical scroll (`max-h-[420px]`).
  * Item Line Component:
    * Title and calculated Line Subtotal (`(base_price + modifier_price) * qty`).
    * Indented Modifier Subtext: Indented italic block displaying chosen options in brackets: `  [Level 3, Telur Tambahan]`.
    * "Edit Pilihan" Text Button: Triggers Option Modal loaded with this line's current selections.
    * Stepper Controls: Circular `[-]` button (32×32px), quantity input (`type="number"`, centered), circular `[+]` button.
    * Line Delete Button: Red trash/cross button (`✕`, 32×32px).




* **Service & Table Configuration:**
  * Service Segmented Toggle: Two options: `Dine-in (Meja)` and `Bawa Pulang (TA)`.
  * Table Selection Dropdown: Populated with options `Meja 01` through `Meja N` (dynamically pulled from `settings.table_count`). Automatically disabled and set to `"TA"` when Take Away is toggled.


* **Payment Settlement Box:**
  * Total Display: Large format price callout (`text-3xl font-extrabold text-[#1B4D3E]`).
  * Method Toggle Tabs: Two full-width selectable tiles: `[Tunai]` and `[QRIS]`.
  * Cash Tender Input (when Tunai is active):
    * Numeric input field `"Uang Diterima"`, autofocus-capable, dynamic thousands separator formatting.
    * Fast-Tender Chips: Quick click buttons: `[Uang Pas]`, `[Rp20.000]`, `[Rp50.000]`, `[Rp100.000]`.
    * Change Output Callout: Large bold text: `"Kembalian: Rp9.000"` (green) or `"Uang Kurang: RpX.XXX"` (red).


  * QRIS Notice (when QRIS is active):
    * Info callout: `"Tampilkan kode QRIS kepada pelanggan dan verifikasi notifikasi m-banking."`. The QRIS image itself and the funds-received checkbox are shown in the Transaction Confirmation Modal (section 5); there is no separate QRIS modal.




* **Primary Checkout Trigger Button:**
  * Text: `"SELESAIKAN TRANSAKSI"` (or `"PROSES PEMBAYARAN"`).
  * Specs: 54px height, high-contrast Forest Green (`#1B4D3E`), bold white uppercase font.
  * Disabled State: Dimmed (`bg-gray-300 cursor-not-allowed`) if cart has 0 items, table is unselected, or cash tendered is less than total.



---

### 4. Option Selection Modal / Drawer (`modal_options.php`)

```
+-----------------------------------------------------------------------+
|  Mie Goreng                                                  [Close X]|
|  Harga Dasar: Rp14.000                                                |
|  ===================================================================  |
|                                                                       |
|  Tingkat Pedas  [ WAJIB ]  (Pilih 1)                                  |
|  ( ) Level 1 (+Rp0)      ( ) Level 2 (+Rp0)      (o) Level 3 (+Rp0)   |
|                                                                       |
|  Toppings / Tambahan  [ OPSIONAL ]  (Pilih Bebas)                     |
|  [x] Telur Tambahan (+Rp3.000)                                        |
|  [ ] Sosis (+Rp4.000)                                                 |
|  [ ] Keju (+Rp3.000) - [HABIS]                                        |
|                                                                       |
|  -------------------------------------------------------------------  |
|  Jumlah:  [-]  2  [+]                                                 |
|  Harga Satuan: Rp17.000 (14.000 + 3.000)         Subtotal:  Rp34.000  |
|                                                                       |
|  [          Batal          ]     [   TAMBAH KE KERANJANG (Rp34.000)   ] |
+-----------------------------------------------------------------------+

```

#### Exact UI Components

* **Modal Backdrop:** Semi-transparent dark overlay (`bg-black/50 backdrop-blur-sm`, z-index 50).
* **Header:** Item title (`text-xl font-bold`), base price tag, close button (`✕`, 40×40px).
* **Modifier Group Section (Rendered iteratively, maximum 5 groups):**
  * Group Header: Group Name (e.g., "Tingkat Pedas"), Selection Constraint badge (`Pilih 1` for single-choice; `Pilih Bebas` for multi-choice).
  * Requirement Indicator Badge: Red pill badge `"WAJIB"` if `is_required === 1`; Gray pill `"OPSIONAL"` if `is_required === 0`.
  * Option Choice Items (Maximum 10 items per group):
    * Radio Buttons: Native styled circular radios for `single_choice` groups.
    * Checkboxes: Native styled square checkboxes for `multi_choice` groups.
    * Labels: Option Name + Extra Price callout (e.g., `"Telur Tambahan (+Rp3.000)"` or `"+Rp0"`).
    * Disabled / Sold Out State: Strikethrough label, grayed text, disabled input, `"HABIS"` tag if `available === 0`.




* **Quantity & Calculation Sub-bar:**
  * Quantity Stepper: Large `[-]` `[ Qty ]` `[+]` buttons (minimum 40×40px).
  * Unit Calculation Label: Descriptive breakdown: `"Harga Satuan: Rp17.000 (14.000 + 3.000)"`.
  * Subtotal Metric: Bold callout: `"Subtotal: Rp34.000"`.


* **Validation Error Banner:**
  * Hidden by default; displays red text: `"Pilih salah satu opsi pada [Nama Group] sebelum menambahkan ke keranjang."` if mandatory constraints are not met.


* **Footer Actions:**
  * "Batal" Button: Secondary gray outline button.
  * "TAMBAH KE KERANJANG" Button: Primary green button, dynamically showing calculated subtotal. Remains strictly disabled (`cursor-not-allowed opacity-50`) until all mandatory groups are satisfied.



---

### 5. Transaction Confirmation Modal (`modal_confirm.php`)

```
+-----------------------------------------------------------------------+
|  [?] KONFIRMASI TRANSAKSI                                    [Close X]|
|  -------------------------------------------------------------------  |
|  Ringkasan:                                                           |
|  - Meja / Layanan : Meja 05                                           |
|  - Total Tagihan  : Rp61.000                                          |
|  - Metode Bayar   : TUNAI                                             |
|  - Uang Diterima  : Rp70.000                                          |
|  - Uang Kembalian : Rp9.000                                           |
|                                                                       |
|  [!] Peringatan: Data transaksi yang telah disimpan tidak dapat       |
|      diubah kembali. Pastikan rincian pesanan sudah sesuai.           |
|                                                                       |
|  [           Batal           ]       [      YA, SELESAIKAN (ENTER)    ]|
+-----------------------------------------------------------------------+

```

#### Exact UI Components

* **Modal Card:** High-contrast centered dialog.
* **Data Summary Table:** Clean two-column key-value ledger displaying Table/TA, Total, Payment Method, Tendered Amount, and Change.
* **QRIS Verification Block (Rendered only if QRIS selected):**
  * Embedded image preview of the owner's static QRIS code from `uploads/qris/`.
  * Merchant details: Bank name and registered owner name.
  * Acknowledgment Checkbox: `[x] Saya mengonfirmasi dana Rp61.000 telah masuk di mutasi m-banking.` (Must be checked to enable submission).
* **Warning Banner:** Amber alert callout stating order immutability.
* **Action Buttons:** "Batal" (neutral escape button) and "YA, SELESAIKAN" (primary green action button triggering token submission and receipt window generation).

---

### 6. Printable Thermal Receipt (`receipt.php`)

Formatted via CSS `@media print` for standard 58mm and 80mm roll printers.

```
+------------------------------------------+
|              KEDAI PARADISE              |
|  RT 01 RW 02, Kandang Gampang, Pbg       |
| ---------------------------------------- |
| No. Pesanan : 021                        |
| Meja / TA   : Meja 05                    |
| Waktu       : 30/10/2026 10:42 WIB       |
| Kasir       : Fitri                      |
| ---------------------------------------- |
| 2 x Mie Goreng                  34.000   |
|     [Level 3, Telur Tambahan]            |
| 1 x Kopi Susu                   22.000   |
|     [Large]                              |
| 1 x Es Teh                       5.000   |
| ---------------------------------------- |
| TOTAL                           61.000   |
| Metode      : Tunai                      |
| Bayar       : 70.000                     |
| Kembali     :  9.000                     |
| ---------------------------------------- |
| Status      : DIPROSES                   |
|       Terima kasih telah berkunjung!     |
+------------------------------------------+

```

#### Exact UI Components

* **Paper Dimension Constraint:** 58mm (default, 32 characters/line) or 80mm (48 characters/line) monospace layout (`font-mono text-xs`).
* **Header Block:** Centered Business Name, Address line, separating dashed divider rule.
* **Metadata Block:** Left-aligned Order Number, Table/TA, Date/Time, and Cashier Name.
* **Reprint / Void Indicators:**
  * `*** SALINAN / CETAK ULANG ***` centered banner if opened with `?reprint=1`.
  * `*** DIBATALKAN ***` large centered banner if `status === 'void'`.


* **Line Items Table:**
  * Two-column layout: Quantity × Item Name on left; Line Subtotal right-aligned.
  * Indented Modifier Snapshot: Indented child lines enclosed in square brackets immediately beneath the parent item.


* **Summary Block:** Bold Total, Tendered, and Change Due lines.
* **Footer Block:** Status indicator, centering custom message from `settings.receipt_footer`.

---

### 7. Kitchen Order Queue (`queue.php`)

```
+--------------------------------------------------------------------------------------------------------------------+
|  ANTRIAN PESANAN MASUK (3 Pesanan Aktif)                           [● Pembaruan Otomatis: 15s]  [Perbarui Manual ⟳]|
|                                                                                                                    |
|  +--------------------------------+  +--------------------------------+  +--------------------------------+        |
|  | No. 021                Meja 05 |  | No. 022                     TA |  | No. 023                Meja 02 |        |
|  | Masuk: 10:42  Lama: 03:12 (⏳) |  | Masuk: 10:45  Lama: 00:20 (⏳) |  | Masuk: 10:48  Lama: 00:02 (⏳) |        |
|  | ------------------------------ |  | ------------------------------ |  | ------------------------------ |        |
|  | 2x Mie Goreng                  |  | 1x Mie Goreng                  |  | 2x Roti Bakar                  |        |
|  |    [Level 3, Telur Tambahan]   |  |    [Level 1, Sosis]            |  | 1x Kopi Susu                   |        |
|  | 1x Kopi Susu                   |  |                                |  |    [Regular]                   |        |
|  |    [Large]                     |  |                                |  |                                |        |
|  | 1x Es Teh                      |  |                                |  |                                |        |
|  | ------------------------------ |  | ------------------------------ |  | ------------------------------ |        |
|  | [    ✓ TANDAI SELESAI     ]   |  | [    ✓ TANDAI SELESAI     ]   |  | [    ✓ TANDAI SELESAI     ]   |        |
|  +--------------------------------+  +--------------------------------+  +--------------------------------+        |
|                                                                                                                    |
|  PESANAN SELESAI HARI INI                                                                                          |
|  +-------------+-----------+---------------+----------------+-------------------+-----------------+                |
|  | No. Pesanan | Meja / TA | Waktu Pesan   | Waktu Selesai  | Durasi Pengerjaan | Kasir           |                |
|  +-------------+-----------+---------------+----------------+-------------------+-----------------+                |
|  | 020         | Meja 03   | 10:31 WIB     | 10:38 WIB      | 07:02 (422 dtk)   | Fitri           |                |
|  | 018         | TA        | 10:12 WIB     | 10:18 WIB      | 06:10 (370 dtk)   | Dewi            |                |
|  +-------------+-----------+---------------+----------------+-------------------+-----------------+                |
+--------------------------------------------------------------------------------------------------------------------+

```

#### Exact UI Components

* **Top Queue Control Bar:** Active orders counter heading, green pulsing indicator `"Pembaruan Otomatis: 15 detik"`, manual refresh trigger button.
* **Active Queue Ticket Cards (CSS Grid, minimum 320px width per card):**
  * Card Header: High-contrast order number `#021` (`text-2xl font-black`), Meja/TA badge (`bg-slate-100 text-slate-800 font-bold px-2 py-1`).
  * Order Timer Capsule: Order submission timestamp (`10:42 WIB`), dynamic running counter formatted as `Lama: mm:ss`.
  * Color States: Green (`< 10 menit`), Amber (`10–20 menit`), Red/Pulsing (`> 20 menit`).


  * Item Details Box: Item quantity and name in bold, with chosen modifiers indented in amber/muted text below each item.
  * Fulfillment Action Button: Full-width green button `"✓ TANDAI SELESAI"` (44px height, triggers `api_complete_order.php` via AJAX).


* **Completed Orders Log Table:**
  * Tabular ledger of orders marked completed today, displaying Order Number, Table/TA, Order Time, Completion Time, and Duration formatted as `mm:ss`.



---

### 8. Transaction History Ledger (`history.php`)

```
+--------------------------------------------------------------------------------------------------------------------+
|  RIWAYAT TRANSAKSI PENJUALAN                                                                                       |
|  Periode: [ 01/10/2026 ] s/d [ 30/10/2026 ]  Metode: [Semua |v]  Status: [Semua |v]  [Filter]                      |
|  Aksi: [ Ekspor CSV (v) ]  [ Cetak / PDF ]                                                                         |
|  ----------------------------------------------------------------------------------------------------------------- |
|  [>] No.  Tanggal/Waktu      Meja  Metode  Total     Status     Durasi  HPP (Admin) Laba (Admin)  Aksi             |
|  ----------------------------------------------------------------------------------------------------------------- |
|  [v] 021  30/10 10:42 WIB    05    Tunai   61.000    Diproses   --      35.500      25.500        [Struk] [Batal]  |
|      └── Rincian: 2x Mie Goreng [Level 3, Telur Tambahan], 1x Kopi Susu [Large], 1x Es Teh                        |
|  [>] 022  30/10 10:45 WIB    TA    QRIS    18.000    Diproses   --      11.500       6.500        [Struk] [Batal]  |
|  [>] 019  30/10 10:20 WIB    TA    Tunai   27.000    Dibatalkan --      --          --            [Struk]          |
|      └── Alasan Batal: Pelanggan membatalkan pesanan (Dibatalkan oleh: fajar pada 10:25 WIB)                       |
+--------------------------------------------------------------------------------------------------------------------+

```

#### Exact UI Components

* **Filter Bar:** Start Date and End Date pickers (locked to current day for cashiers), Payment Method dropdown (`Semua`, `Tunai`, `QRIS`), Status dropdown (`Semua`, `Diproses`, `Selesai`, `Dibatalkan`).
* **Export Action Group:**
  * "Ekspor CSV" button with dropdown options: Comma Delimiter (`,`) and Semicolon Delimiter (`;`).
  * "Cetak / PDF" button triggering `@media print` formatted view.


* **Data Table:**
  * Clickable column headers toggling ascending/descending sorting indicators ($\blacktriangle / \blacktriangledown$).
  * Strikethrough text styling and red `"Dibatalkan"` badge for voided rows.
  * Admin-only columns: HPP (`total_cost`), Laba Kotor (`gross_profit`), Laba Bersih (`alloc_profit`), Kasir. Cashiers see none of them (BR-12).
  * Server-side sorting on EVERY main column (whitelist on the server, stable secondary key `transaction_id`, BR-23) and server-side pagination, 50 rows per page. Totals bar and exports always cover the whole filtered result, not only the visible page.
  * Export/Print links carry the active `status`, `method`, `from`, `to`, `sort`, `dir` parameters.
  * Collapsible Drawer (Accordion per row): Expands to show itemized quantities, unit prices, and indented frozen modifier details. If voided, displays cancellation metadata box.
  * Action Buttons per Row:
    * `[Struk]`: Opens `receipt.php?id=X&reprint=1` in new tab.
    * `[Batal]` (Admin only): Opens Void Modal; hidden if row is already void. Cashiers never receive this button and a direct call returns HTTP 403.




* **Totals Summary Bar (Table Footer):** Calculates gross volume, orders count, and net revenue strictly excluding voided rows.

---

### 9. Void Order Cancellation Modal (`history.php` / `api_void_order.php`)

```
+-----------------------------------------------------------------------+
|  [!] BATALKAN PESANAN #022                                   [Close X]|
|  -------------------------------------------------------------------  |
|  Rincian: Meja TA | QRIS | Rp18.000 | 1x Mie Goreng [Level 1, Sosis]  |
|                                                                       |
|  Alasan Pembatalan (Wajib Diisi):                                     |
|  +-----------------------------------------------------------------+  |
|  | Salah input tingkat pedas; pesanan dibuat ulang                 |  |
|  +-----------------------------------------------------------------+  |
|  Karakter: 52 / 255                                                   |
|                                                                       |
|  [!] Peringatan Kritis:                                               |
|      - Pesanan akan dibatalkan secara permanen (status: VOID).        |
|      - Dikeluarkan dari omset, laba, dan seluruh laporan keuangan.    |
|      - Nomor pesanan (#022) tidak akan digunakan ulang.               |
|      - Tindakan ini tidak dapat dibatalkan (audit tersimpan).         |
|      - Pengembalian uang ke pelanggan dilakukan manual di kasir.      |
|                                                                       |
|  [          Kembali          ]       [    YA, BATALKAN PESANAN (X)   ]|
+-----------------------------------------------------------------------+

```

#### Exact UI Components

* **Modal Dialog:** Critical alert modal with red warning border (`border-red-500`).
* **Order Context Capsule:** Gray container summarizing Order Number, Table/TA, Method, Amount, and Item List.
* **Cancellation Reason Input:**
  * `<textarea rows="3">`, placeholder `"Tuliskan alasan pembatalan secara jelas (minimal 3 karakter)..."`.
  * Live character counter indicator: `X / 255 karakter`.
  * Client-side validation: Blocks submission if trimmed length is < 3 characters.


* **Audit Warning Box:** Red highlighted callout explaining financial isolation, order number reservation, irreversibility, audit trail permanence, and that any cash refund to the customer is handled manually at the counter.
* **Action Buttons:** "Kembali" (neutral gray button) and "YA, BATALKAN PESANAN" (danger red button, solid `#DC2626`, white text).

---

### 10. End-of-Day Cash Closing / Tutup Kasir (`cash_close.php`)

```
+--------------------------------------------------------+---------------------------------------------------+
|  TUTUP KASIR (REKONSILIASI LACI)                       |  RIWAYAT TUTUP KASIR (30 Hari Terakhir)           |
|  Tanggal Tutup: [ 30/10/2026                 |v] ]     |  +------------+----------+----------+-----------+ |
|                                                        |  | Tanggal    | Target   | Dihitung | Selisih   | |
|  Ringkasan Sistem (Otomatis):                          |  +------------+----------+----------+-----------+ |
|  - Penjualan Tunai Bersih : Rp350.000 (Non-Void)       |  | 29/10/2026 |  610.000 |  610.000 | 0 (Pas)   | |
|  - Penjualan QRIS (Info)  : Rp180.000                  |  | 28/10/2026 |  550.000 |  555.000 | +5.000    | |
|  - Pesanan Dibatalkan     : 1 Pesanan (Rp27.000)       |  | 27/10/2026 |  495.000 |  490.000 | -5.000    | |
|                                                        |  +------------+----------+----------+-----------+ |
|  Input Kas Fisik:                                      |                                                   |
|  Modal Awal Laci (Float)                               |                                                   |
|  [ 200.000                                           ] |                                                   |
|                                                        |                                                   |
|  Uang Keluar dari Laci (Cash Out)                      |                                                   |
|  [ 50.000                                            ] |                                                   |
|  *Catatan: Jika untuk belanja toko, catat di Keuangan  |                                                   |
|                                                        |                                                   |
|  Kas Seharusnya di Laci (Target):             Rp500.000|                                                   |
|  (200.000 + 350.000 - 50.000)                          |                                                   |
|                                                        |                                                   |
|  Uang Fisik Dihitung (Actual)                          |                                                   |
|  [ 495.000                                           ] |                                                   |
|                                                        |                                                   |
|  SELISIH KAS:                     -Rp5.000 (KURANG /!) |                                                   |
|                                                        |                                                   |
|  Catatan Selisih:                                      |                                                   |
|  [ Salah kembalian pada transaksi siang tadi         ] |                                                   |
|                                                        |                                                   |
|  [           SIMPAN LAPORAN TUTUP KASIR              ] |                                                   |
+--------------------------------------------------------+---------------------------------------------------+

```

#### Exact UI Components

* **Left Panel: Cash Drawer Reconciliation Form:**
  * Date Picker (Admin only): select closing date, not in the future. Cashiers do not see the form at all, only the history table (read-only, SRS-F15, BR-12).
  * System Metric Badges:
    * Non-void cash sales volume (`Penjualan Tunai Bersih`).
    * Non-cash QRIS volume (`Penjualan QRIS (Info)`).
    * Void summary count and value.


  * Form Input Fields:
    * `Modal Awal Laci`: Numeric input for opening cash float.
    * `Uang Keluar dari Laci`: Numeric input for cash withdrawn from drawer, with explanatory caption.
    * `Uang Fisik Dihitung`: Numeric input for actual counted currency.
    * `Catatan`: Optional text input for explanations.


  * Live Calculation Output Labels:
    * Target Kas Seharusnya: Computed dynamically (`Modal Awal + Tunai Bersih - Uang Keluar`).
    * Selisih Kas (Variance): Dynamic callout badge:
      * Green pill: `Rp0 (Sesuai / Pas)`
      * Amber pill: `+RpX.XXX (Lebih)`
      * Red pill: `-RpX.XXX (Kurang)`




  * Submit Action: "SIMPAN LAPORAN TUTUP KASIR" button (Admin can edit/overwrite; Cashiers view read-only).


* **Right Panel: Closing History Table:**
  * Tabular display of past 30 days (same for Admin and Cashier): Tanggal, Modal Awal, Penjualan Tunai, Uang Keluar, Seharusnya, Fisik Dihitung, Selisih, Operator.
  * Stale-closing badge `⚠ Perlu disimpan ulang` when the stored `cash_sales` differs from the current non-void cash total of that date (for example after a later void). Admin re-saves the date to correct it.
  * A warning (not an error) is shown when orders of that date are still `processing`.



---

### 11. Sales & Financial Summary Report (`reports.php`)

```
+--------------------------------------------------------------------------------------------------------------------+
|  LAPORAN RINGKASAN PENJUALAN & KEUANGAN                                                                            |
|  Periode: [ 01/10/2026 ] s/d [ 30/10/2026 ]  [Tampilkan]  [Ekspor CSV (v)]  [Cetak / PDF]                         |
|  ----------------------------------------------------------------------------------------------------------------- |
|  +--------------------+ +--------------------+ +--------------------+ +--------------------+ +--------------------+ |
|  | TOTAL OMSET        | | TOTAL LABA KOTOR   | | TOTAL LABA BERSIH  | | TOTAL PENGELUARAN  | | SALDO KAS BERSIH   | |
|  | Rp4.250.000        | | Rp1.012.000        | | Rp506.000          | | Rp2.980.000        | | Rp1.370.000        | |
|  +--------------------+ +--------------------+ +--------------------+ +--------------------+ +--------------------+ |
|                                                                                                                    |
|  NERACA SALDO PER POS DANA (VIRTUAL POOLS)                         [✓ Saldo Rekonsiliasi Sesuai]                   |
|  +--------------------+--------------------+--------------------+--------------------+--------------------+        |
|  | Pos Dana           | Alokasi Penjualan  | Pemasukan Manual   | Pengeluaran Manual | Saldo Akhir Pos    |        |
|  +--------------------+--------------------+--------------------+--------------------+--------------------+        |
|  | Belanja Bahan      | Rp3.238.000        | Rp0                | Rp2.500.000        | Rp738.000          |        |
|  | Operasional       | Rp506.000          | Rp0                | Rp480.000          | Rp26.000           |        |
|  | Laba Bersih       | Rp506.000          | Rp100.000          | Rp0                | Rp606.000          |        |
|  +--------------------+--------------------+--------------------+--------------------+--------------------+        |
|                                                                                                                    |
|  RINGKASAN: Total Pesanan 32 | Rata-rata Durasi 07:05 | PENDAPATAN PER METODE: Tunai Rp2.600.000 | QRIS Rp1.650.000    |
|                                                                                                                    |
|  RINCIAN HARIAN (SORTABLE DAILY BREAKDOWN)                                                                         |
|  +------------+---------------+----------------+----------------+----------------+----------------+--------------+ |
|  | Tanggal    | Pesanan Sls   | Omset Kotor    | Total HPP      | Laba Kotor     | Laba Bersih    | Rata2 Durasi | |
|  +------------+---------------+----------------+----------------+----------------+----------------+--------------+ |
|  | 30/10/2026 | 17            | Rp612.000      | Rp485.000      | Rp127.000      | Rp63.500       | 06:48        | |
|  | 29/10/2026 | 15            | Rp541.000      | Rp431.000      | Rp110.000      | Rp55.000       | 07:21        | |
|  +------------+---------------+----------------+----------------+----------------+----------------+--------------+ |
+--------------------------------------------------------------------------------------------------------------------+

```

#### Exact UI Components

* **Control Header:** Date range selector, "Tampilkan" query button, "Ekspor CSV" button (with `,` and `;` delimiter options), "Cetak / PDF" button.
* **KPI Metric Cards (Grid of 5 main cards + 1 muted void card):**
  * Card 1: Total Omset (Gross sales, voids excluded).
  * Card 2: Total Laba Kotor (`Omset - Total HPP`).
  * Card 3: Total Laba Bersih (`Sum(alloc_profit)`).
  * Card 4: Total Pengeluaran (`Sum(finance.expense)`).
  * Card 5: Saldo Kas Bersih (`Omset + Pemasukan - Pengeluaran`).
  * Summary strip under the cards: Total Pesanan (non-void), Rata-rata Durasi Pengerjaan (mm:ss), and the Revenue-per-Method table (Tunai, QRIS, with percentage) required by SRS Table 3 #13.
  * Card 6: Muted card showing Void Statistics (e.g., `"2 Pesanan Dibatalkan: Rp82.000"`).


* **Fund Pool Reconciliation Table:** (pool names only; the operating/profit percentage shown in a caption comes from `settings.operating_percent`, never hard-coded)
  * Columns: Pos Dana, Alokasi Penjualan, Pemasukan Manual, Pengeluaran Manual, Saldo Akhir Pos.
  * Audit Badge: Green checkmark badge `"✓ Saldo Rekonsiliasi Sesuai"` if sum of pool balances equals Net Cash Balance; Red alert if unequal.


* **Daily Breakdown Table:**
  * Sortable columns: Tanggal, Pesanan Selesai, Omset Kotor, Total HPP, Laba Kotor, Laba Bersih, Rata-rata Durasi.



---

### 12. Menu Item Sales Performance Report (`menu_performance.php`)

```
+--------------------------------------------------------------------------------------------------------------------+
|  LAPORAN PERFORMA PENJUALAN MENU & PILIHAN                                                                         |
|  Periode: [ 01/10/2026 ] s/d [ 30/10/2026 ]  [Tampilkan]  [Ekspor CSV]  [Cetak / PDF]                              |
|  ----------------------------------------------------------------------------------------------------------------- |
|  +--------------------+------------+-----------------+-------------------+---------------+-----------------------+ |
|  | Nama Menu (v)      | Terjual    | Penjualan Dasar | Penjualan Pilihan | Total Omset   | Laba Kotor            | |
|  +--------------------+------------+-----------------+-------------------+---------------+-----------------------+ |
|  | Kopi Susu          | 64 porsi   | Rp1.152.000     | Rp120.000 (Large) | Rp1.272.000   | Rp587.000             | |
|  | Mie Goreng         | 33 porsi   | Rp462.000       | Rp84.000 (Topping)| Rp546.000     | Rp194.000             | |
|  | Es Teh             | 51 porsi   | Rp255.000       | Rp0               | Rp255.000     | Rp153.000             | |
|  +--------------------+------------+-----------------+-------------------+---------------+-----------------------+ |
|  | TOTAL KESELURUHAN  | 148 porsi  | Rp1.869.000     | Rp204.000         | Rp2.073.000   | Rp934.000             | |
|  +--------------------+------------+-----------------+-------------------+---------------+-----------------------+ |
+--------------------------------------------------------------------------------------------------------------------+

```

#### Exact UI Components

* **Date & Action Controls:** Date pickers, CSV export button, Print/PDF trigger.
* **Reconciliation rule:** for the same period, the TOTAL row's Total Omset must equal Total Omset on `reports.php` (voids excluded). The mockup values are illustrative and list only three items.
* **Performance Table:**
  * Columns: Nama Menu, Terjual (Qty), Penjualan Dasar, Penjualan Pilihan (Modifiers extra revenue), Total Omset, Total HPP, Laba Kotor.
  * Server-side sorting on all column headers (whitelist, `sort`/`dir` parameters), and Export CSV/Print carry the same parameters.
  * Bottom summary totals row summing quantities and revenue streams.



---

### 13. Manual Financial Records Ledger (`finance.php`)

```
+--------------------------------------------------------------------------------------------------------------------+
|  BUKU KAS KEUANGAN MANUAL (PENGELUARAN & PEMASUKAN TOKO)                                                           |
|                                                                                                                    |
|  +-- CATAT TRANSAKSI KEUANGAN ----------------------------------------------------------------------------------+  |
|  | Tanggal: [ 30/10/2026 09:05 ]   Jenis: (o) Pengeluaran  ( ) Pemasukan                                        |  |
|  | Kategori: [ Belanja bahan baku            |v] ]   Pos Dana: [ Belanja Bahan (Restock)     |v] ]              |  |
|  | Nominal (Rp): [ 250.000                       ]   Keterangan: [ Telur, mie, beras, ayam                    ] |  |
|  | [               + SIMPAN CATATAN KEUANGAN                ]                                                   |  |
|  +--------------------------------------------------------------------------------------------------------------+  |
|                                                                                                                    |
|  DAFTAR CATATAN TRANSAKSI                                          [Ekspor CSV]  [Cetak / PDF]                     |
|  +------------------+-------------+----------------------+-------------------+--------------+--------+-----------+ |
|  | Tanggal/Waktu    | Jenis       | Kategori             | Pos Dana          | Nominal      | User   | Aksi      | |
|  +------------------+-------------+----------------------+-------------------+--------------+--------+-----------+ |
|  | 30/10 09:05 WIB  | Pengeluaran | Belanja bahan baku   | Belanja Bahan     | Rp250.000    | fajar  | [U][H]    | |
|  | 29/10 18:00 WIB  | Pengeluaran | Token Listrik Toko   | Operasional       | Rp180.000    | fajar  | [U][H]    | |
|  | 29/10 17:55 WIB  | Pemasukan   | Penjualan Minyak Bekas| Laba             | Rp100.000    | fitri  | [U][H]    | |
|  +------------------+-------------+----------------------+-------------------+--------------+--------+-----------+ |
+--------------------------------------------------------------------------------------------------------------------+

```

#### Exact UI Components

* **Top Entry Panel:**
  * Form Fields: Date/Time picker (WIB), Radio selector (`Pengeluaran` in red, `Pemasukan` in green), Category input with autocomplete datalist, Fund Pool dropdown (`Belanja Bahan`, `Operasional`, `Laba`), Nominal integer input, Description text input.
  * Submit Action: "Simpan Catatan Keuangan" button.


* **Bottom Ledger Panel:**
  * Filter Bar: Date range, Type filter dropdown, Pool filter dropdown.
  * Table: Tanggal, Jenis pill badge, Kategori, Pos Dana, Nominal (`RpXXX.XXX`), Keterangan, Dicatat Oleh, Aksi (`[Ubah]` and `[Hapus]`).
  * Delete Confirmation Modal: Prompts `"Hapus catatan keuangan ini? Saldo pos dana akan dihitung ulang secara otomatis."`.



---

### 14. Menu Catalog Management (`menu_manage.php`)

```
+--------------------------------------------------------------------------------------------------------------------+
|  MANAJEMEN KATALOG MENU & HARGA                                                [+ Tambah Menu Baru]               |
|  Pencarian: [ Cari nama menu...             ]   [ ] Tampilkan Menu Nonaktif                                         |
|  ----------------------------------------------------------------------------------------------------------------- |
|  Foto     Nama Menu     Kategori   Harga Jual   HPP (Modal)  Margin    Pilihan   Ketersediaan  Status    Aksi        |
|  ----------------------------------------------------------------------------------------------------------------- |
|  [IMG]    Mie Goreng    Makanan    Rp14.000     Rp9.000      Rp5.000   2 Grup    [x] Tersedia  Aktif     [Ubah] [Off]|
|  [IMG]    Kopi Susu     Minuman    Rp18.000     Rp10.000     Rp8.000   1 Grup    [x] Tersedia  Aktif     [Ubah] [Off]|
|  [--]     Air Mineral   Minuman    Rp4.000      Rp2.500      Rp1.500   --        [ ] Habis     Aktif     [Ubah] [Off]|
+--------------------------------------------------------------------------------------------------------------------+

```

#### Exact UI Components

* **Control Toolbar:** Search field, Category dropdown filter (`Semua kategori` + distinct categories), filter checkbox for inactive items, "+ Tambah Menu Baru" button.
* **Menu Table:**
  * Columns: Foto (48×48px image or placeholder icon), Nama Menu, Kategori, Harga Jual, HPP/Modal, Margin (`Harga Jual - HPP`), Pilihan Badge (`X Grup [Kelola]`), Ketersediaan Switch, Status Pill (`Aktif`/`Nonaktif`), Aksi (`[Kelola Pilihan]`, `[Ubah]`, `[Nonaktifkan]`).


* **Add/Edit Menu Item Modal:**
  * Inputs: Nama Menu, Kategori (required, max 30 characters, text input with a datalist of existing categories; default `Lainnya`), Harga Jual ($\ge 0$), Harga Modal/HPP ($\ge 0$), Foto file selector ($\le 1\text{ MB}$, JPG/PNG), Status Ketersediaan toggle, Status Aktif toggle.
  * Live Warning Banner: Appears in amber if `Harga Jual < Harga Modal`.



---

### 15. Menu Options & Modifiers Configuration Builder (`menu_manage.php` Modal)

```
+--------------------------------------------------------------------------------------------------------------------+
|  KELOLA PILIHAN & MODIFIERS: Mie Goreng (Harga Dasar: Rp14.000 | HPP: Rp9.000)                             [Close X]|
|  Batas: Maksimal 5 Grup per Menu, Maksimal 10 Pilihan per Grup                                                     |
|  ----------------------------------------------------------------------------------------------------------------- |
|  +-- GRUP 1: Tingkat Pedas ------------------------------------------------------------- [Hapus Grup] ----------+  |
|  | Tipe: [ Single Choice (Pilih 1) |v] ]   Kewajiban: [ Wajib Diisi (Mandatory) |v] ]                            |  |
|  | +--------------------+--------------------+--------------------+--------------------+-----------------------+ |  |
|  | | Nama Pilihan       | Harga Tambahan (Rp)| Biaya Tambahan (HPP| Ketersediaan       | Aksi                  | |  |
|  | +--------------------+--------------------+--------------------+--------------------+-----------------------+ |  |
|  | | Level 1            | 0                  | 0                  | [x] Tersedia       | [Hapus]               | |  |
|  | | Level 2            | 0                  | 0                  | [x] Tersedia       | [Hapus]               | |  |
|  | | Level 3            | 0                  | 0                  | [x] Tersedia       | [Hapus]               | |  |
|  | +--------------------+--------------------+--------------------+--------------------+-----------------------+ |  |
|  | [+ Tambah Pilihan Item]                                                                                       |  |
|  +---------------------------------------------------------------------------------------------------------------+  |
|                                                                                                                    |
|  +-- GRUP 2: Toppings / Tambahan ------------------------------------------------------- [Hapus Grup] ----------+  |
|  | Tipe: [ Multi Choice (Bebas)    |v] ]   Kewajiban: [ Opsional (Optional)     |v] ]                            |  |
|  | +--------------------+--------------------+--------------------+--------------------+-----------------------+ |  |
|  | | Telur Tambahan     | 3.000              | 2.000              | [x] Tersedia       | [Hapus]               | |  |
|  | | Sosis              | 4.000              | 2.500              | [x] Tersedia       | [Hapus]               | |  |
|  | | Keju               | 3.000              | 1.500              | [ ] Habis          | [Hapus]               | |  |
|  | +--------------------+--------------------+--------------------+--------------------+-----------------------+ |  |
|  | [+ Tambah Pilihan Item]                                                                                       |  |
|  +---------------------------------------------------------------------------------------------------------------+  |
|                                                                                                                    |
|  [ + Tambah Grup Pilihan Baru ]                                                       [ Selesai & Tutup ]          |
+--------------------------------------------------------------------------------------------------------------------+

```

#### Exact UI Components

* **Header Capsule:** Menu name context, base retail price, base cost price.
* **Modifier Group Cards (Iterative container, maximum 5 cards):**
  * Group Parameters: Group Name text input, Group Type dropdown (`single` vs. `multiple`), Group Requirement dropdown (`is_required = 1` vs. `0`), "Hapus Grup" button.
  * Options Items Table (Maximum 10 rows per card):
    * Columns: Nama Pilihan (text), Harga Tambahan (`extra_price` $\ge 0$), Biaya Tambahan HPP (`extra_cost` $\ge 0$), Ketersediaan (Toggle switch), Aksi (`[Hapus]`).
    * Inline Add Row: Input fields with "+ Tambah Pilihan Item" trigger.




* **Bottom Controls:** "+ Tambah Grup Pilihan Baru" button (disabled if 5 groups exist) and "Selesai & Tutup" button.

---

### 16. User Account Management (`users.php`)

```
+--------------------------------------------------------------------------------------------------------------------+
|  MANAJEMEN PENGGUNA SISTEM (KASIR & ADMIN)                                     [+ Tambah Pengguna Baru]           |
|  ----------------------------------------------------------------------------------------------------------------- |
|  ID   Nama Lengkap         Username    Peran        Status         Aksi                                           |
|  ----------------------------------------------------------------------------------------------------------------- |
|  1    Bapak Fajar          fajar       Admin        Aktif          [Ubah / Reset] [Nonaktifkan]                   |
|  2    Ibu Fitri            fitri       Admin        Aktif          [Ubah / Reset] [Nonaktifkan]                   |
|  3    Dewi Lestari         dewi        Kasir        Aktif          [Ubah / Reset] [Nonaktifkan]                   |
|  4    Budi Santoso         budi        Kasir        Nonaktif       [Ubah / Reset] [Aktifkan]                      |
+--------------------------------------------------------------------------------------------------------------------+

```

#### Exact UI Components

* **User Accounts Table:**
  * Columns: ID, Nama Lengkap, Username, Peran (Badge: `Admin` in green `#1B4D3E` or `Kasir` in slate `#2C3E50`), Status (Badge: `Aktif` in green or `Nonaktif` in gray), Aksi (`[Ubah / Reset Password]`, Status toggle).
  * Lockout Prevention Safety: Status toggle is disabled for the currently logged-in user with tooltip `"Tidak dapat menonaktifkan akun sendiri."` and for the last active admin (BR-13). No last-login column (not in the data model).


* **Add / Edit User Modal Dialog:**
  * Inputs: Nama Lengkap, Username (unique constraint check), Peran (Radio: Admin / Kasir), Password (required on create, optional on edit for password reset).



---

### 17. Business, QRIS & Fund Settings (`settings.php`)

```
+--------------------------------------------------------------------------------------------------------------------+
|  PENGATURAN SISTEM & IDENTITAS TOKO                                                                                |
|  ----------------------------------------------------------------------------------------------------------------- |
|  +-- PANEL 1: IDENTITAS USAHA & STRUK --------------------------------------------------------------------------+  |
|  | Nama Usaha          : [ Kedai Paradise                                                         ]             |  |
|  | Alamat Usaha        : [ RT 01 RW 02, Kandang Gampang, Purbalingga                              ]             |  |
|  | Jumlah Meja Makan   : [ 12     ] meja                                                                        |  |
|  | Catatan Kaki Struk  : [ Terima kasih telah berkunjung!                                         ]             |  |
|  +--------------------------------------------------------------------------------------------------------------+  |
|                                                                                                                    |
|  +-- PANEL 2: ALOKASI DANA VIRTUAL (3 POOLS) -------------------------------------------------------------------+  |
|  | Persentase Pos Operasional : [ 50.00 ] % dari Laba Kotor                                                     |  |
|  | Skema Pembagian Otomatis:                                                                                    |  |
|  | [ Restock Pool: 100% HPP ]  [ Operasional: 50% Laba Kotor ]  [ Laba Bersih: 50% Laba Kotor ]                 |  |
|  +--------------------------------------------------------------------------------------------------------------+  |
|                                                                                                                    |
|  +-- PANEL 3: PEMBAYARAN DIGITAL QRIS STATIS -------------------------------------------------------------------+  |
|  | Bank / m-Banking    : [ BCA mobile                             ]                                             |  |
|  | Nama Pemilik Akun   : [ Kedai Paradise / Fajar                 ]                                             |  |
|  | Upload Gambar QRIS  : [ Choose File ] qris_kedai_paradise.png (Maksimal 2 MB, JPG/PNG)                        |  |
|  | Pratinjau Gambar:                                                                                            |  |
|  | +------------------------+                                                                                   |  |
|  | | [ QRIS CODE PREVIEW ]  |                                                                                   |  |
|  | +------------------------+                                                                                   |  |
|  +--------------------------------------------------------------------------------------------------------------+  |
|                                                                                                                    |
|  [                                       SIMPAN SEMUA PENGATURAN                                                 ] |
+--------------------------------------------------------------------------------------------------------------------+

```

#### Exact UI Components

* **Panel 1: Business Identity & Receipt Layout:** Text fields for `business_name`, `business_address`, `table_count` ($\ge 1$), and `receipt_footer`.
* **Panel 2: Virtual Fund Allocation Rule:** Numeric input for `operating_percent` ($0.00$ to $100.00$), accompanied by an explanatory diagram illustrating the three-pool split.
* **Panel 3: QRIS Digital Payment:** Input fields for `qris_bank`, `qris_owner_name`, and file selector for static QR image upload with live thumbnail preview.
* **Submit Action:** "SIMPAN SEMUA PENGATURAN" (Full-width sticky bottom button).

---

### 18. Executive Admin Dashboard (`dashboard.php`)

```
+--------------------------------------------------------------------------------------------------------------------+
|  DASBOR EKSEKUTIF KEDAI PARADISE                                                  [Hari Ini: 30/10/2026 WIB]       |
|                                                                                                                    |
|  +--------------------+ +--------------------+ +--------------------+ +--------------------+ +--------------------+ |
|  | OMSET HARI INI     | | LABA KOTOR HARI INI| | TOTAL TRANSAKSI    | | ANTRIAN AKTIF      | | KAS LACI TERHITUNG | |
|  | Rp612.000          | | Rp127.000          | | 17 Pesanan         | | 3 Pesanan          | | Rp495.000          | |
|  +--------------------+ +--------------------+ +--------------------+ +--------------------+ +--------------------+ |
|                                                                                                                    |
|  TREN OMSET 7 HARI TERAKHIR (GRAFIK CSS BARS)                      STATUS TUTUP KASIR                              |
|  +---------------------------------------------------------------+ +---------------------------------------------+ |
|  |  800k |             [■]                                       | | Status Hari Ini: [✓ SUDAH DITUTUP]          | |
|  |  600k |         [■] [■] [■]                                   | | Ditutup Oleh    : fajar                     | |
|  |  400k |     [■] [■] [■] [■] [■]     [■]                       | | Selisih Fisik   : -Rp5.000 (Kurang)         | |
|  |  200k | [■] [■] [■] [■] [■] [■] [■] [■]                       | | [ Buka Menu Tutup Kasir ]                   | |
|  |     0 +--------------------------------                       | +---------------------------------------------+ |
|  |         24  25  26  27  28  29  30 (Okt)                      |                                                 |
|  +---------------------------------------------------------------+                                                 |
|                                                                                                                    |
|  MENU TERLARIS HARI INI (TOP 5 ITEMS)                              PERINGATAN OPERASIONAL                          |
|  +----------------------------+---------------+------------------+ +---------------------------------------------+ |
|  | Nama Menu                  | Porsi Terjual | Kontribusi Omset | | [!] 1 Menu Berstatus Habis:                 | |
|  +----------------------------+---------------+------------------+ |     - Air Mineral (Tandai Tersedia)         | |
|  | 1. Kopi Susu               | 24 porsi      | Rp480.000        | |                                             | |
|  | 2. Mie Goreng              | 18 porsi      | Rp298.000        | | [!] 1 Pesanan Dibatalkan Hari Ini:          | |
|  | 3. Es Teh                  | 14 porsi      | Rp70.000         | |     - Pesanan #019 (Rp27.000)               | |
|  +----------------------------+---------------+------------------+ +---------------------------------------------+ |
+--------------------------------------------------------------------------------------------------------------------+

```

#### Exact UI Components

* **Row 1: Real-Time Operational KPI Cards (Admin):** 6 cards: Omset Hari Ini, Laba Kotor Hari Ini, Total Transaksi, Antrian Aktif, Saldo Kas Hari Ini (SRS cash balance = revenue + manual income - expenses of today), and Kas Laci Terhitung (counted cash from today's `cash_closings` row, `-` if not closed yet). Voided orders are excluded everywhere.
* **Row 2: Analytics & Status Panels:**
  * 7-Day Revenue Trend: Pure CSS vertical bars with date labels and hover tooltips.
  * Cash Closing Operational Card: Visual indicator (`SUDAH DITUTUP` green pill or `BELUM DITUTUP` amber pill) with shortcut link.
* **Row 3: Inventory & Top Performance Panels:**
  * Top 5 Best-Selling Items Table: Ranking showing Item Name, Units Sold, and Revenue Contribution.
  * Operational Alert Feed: List of items currently marked `Sold Out` and summary of today's voided orders.


---

### 19. Cashier Compact Dashboard (`dashboard.php`, role = cashier) (SRS-F11)

```
+--------------------------------------------------------------------------+
|  DASBOR KASIR                                         [Hari Ini: 30/10/2026]|
|  +----------------------+  +----------------------+                       |
|  | TOTAL PESANAN HARI INI|  | PESANAN DIPROSES     |                       |
|  | 17 Pesanan            |  | 3 Pesanan            |   [Buka Kasir] [Antrian]|
|  +----------------------+  +----------------------+                       |
+--------------------------------------------------------------------------+
```

* Only two metrics: today's number of orders (non-void) and orders in `processing`. No revenue, cost, profit or cash balance (BR-12).
* Quick-link buttons to `pos.php` and `queue.php`. Login still lands cashiers on `pos.php`.

---

### 20. My Account (`account.php`, both roles) (SRS-F02)

* Fields: Password Saat Ini, Password Baru (min 8 characters), Konfirmasi Password Baru; CSRF token.
* Verifies the current password with `password_verify()`, stores `password_hash(..., PASSWORD_BCRYPT)`, calls `session_regenerate_id(true)`, shows `"Password berhasil diubah."`.
* Error messages: `"Password saat ini salah."`, `"Password baru minimal 8 karakter."`, `"Konfirmasi password tidak cocok."`.
