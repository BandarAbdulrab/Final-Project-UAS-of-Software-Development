# Kedai Paradise POS & Finance System: Implementation Guide (SRS Revision D, with extensions)

This document has two parts:

- **Part 1:** The 10-Day AI-Driven Implementation Plan (architecture, conventions, prompts, verification steps)
- **Part 2:** The Beginner's Execution Playbook (the hands-on "how-to" for Porkbun, Hostinger, phpMyAdmin, File Manager and debugging)

**What was added to the original plan (and why)**

| Area | Change |
|---|---|
| Missing features | Menu and modifier management, checkout payment UI and token flow, shared navigation and `dashboard.php`, the toggle and finance backends, order voids, and end-of-day cash closing |
| Prompt fixes | Safer daily order numbering, pool balances that include manual income, time zone setup, uploads protection that works on LiteSpeed, upload validation, cutover SQL that works with foreign keys, folder path fix, login rate limiting, CSP header |
| Secrets | Database credentials now live outside `public_html`; `display_errors` is ON in development and OFF at launch |
| Hardware | Thermal printer is tested on Day 1 with the real device, not on Day 10 |
| AI consistency | A Project Conventions block is pasted with the Master Context in every session |
| Language | The whole staff-facing UI and the receipts are in Bahasa Indonesia |

**SRS deviations to be aware of:** this plan adds a `void` status and three columns to `transactions`, a generated `order_date` column, and two new tables (`cash_closings`, `login_attempts`). Treat these as "Revision D + extensions" and update the SRS if you want it to stay the source of truth.

---

# Part 1: Comprehensive 10-Day AI-Driven Implementation Plan

## Master Context Package (Provide at the Start of Every AI Session)

Paste this **Master Context Block** into your AI session (as system instructions or the first message), **followed by the Project Conventions Block below it**. Together they tell the model the schema, architecture, business rules, and coding conventions.

```markdown
### SYSTEM ARCHITECTURE & MASTER CONTEXT: KEDAI PARADISE POS (SRS REV. D + EXTENSIONS)
- **Stack:** PHP 8.x, MySQL 8.0/MariaDB (InnoDB, utf8mb4), Tailwind CSS (via CDN), Vanilla JavaScript (ES6, fetch/AJAX).
- **UI Language:** Everything staff see (screens, buttons, messages, receipts) is in Bahasa Indonesia. Code, comments, database names, and CSV column headers stay in English.
- **Deployment:** Hostinger (Apache-compatible; may be LiteSpeed), HTTPS (TLS 1.2+), Timezone: 'Asia/Jakarta' (WIB, UTC+7), Currency: Indonesian Rupiah (INT, no decimals).
- **Security:** Strict PDO prepared statements, htmlspecialchars() escaping, bcrypt password_hash/verify, session cookies (Secure, HttpOnly, SameSite=Strict, 30m idle timeout), anti-CSRF tokens, single-use checkout tokens, login rate limiting. Database credentials live OUTSIDE public_html in `private/config.local.php`.
- **Database Schema (9 core tables + 2 extension tables):**
  1. `users` (user_id PK, username UK, password_hash, name, role ENUM('admin','cashier'), status TINYINT(1))
  2. `menu` (menu_id PK, name, price INT, cost_price INT, photo NULL, available TINYINT(1), status TINYINT(1))
  3. `menu_modifier_groups` (group_id PK, menu_id FK RESTRICT, name, type ENUM('single','multiple'), is_required TINYINT(1))
  4. `menu_modifier_items` (modifier_id PK, group_id FK CASCADE, name, extra_price INT, extra_cost INT, available TINYINT(1))
  5. `transactions` (transaction_id PK, order_no INT, date DATETIME, table_no VARCHAR(10), method ENUM('cash','qris'), total INT, paid INT, change_due INT, status ENUM('processing','completed','void'), completed_at DATETIME NULL, duration_seconds INT NULL, total_cost INT, gross_profit INT, alloc_restock INT, alloc_operating INT, alloc_profit INT, user_id FK RESTRICT, **extension:** void_reason VARCHAR(255) NULL, voided_at DATETIME NULL, voided_by FK users NULL, order_date DATE GENERATED STORED = DATE(date), UNIQUE(order_date, order_no))
  6. `orders` (order_id PK, transaction_id FK RESTRICT, menu_id FK RESTRICT, quantity INT, price INT, cost_price INT, modifier_price INT, modifier_cost INT, subtotal INT)
  7. `order_item_modifiers` (order_modifier_id PK, order_id FK RESTRICT, modifier_name, price INT, cost_price INT) -> FROZEN SNAPSHOT, NO FK to modifier setup tables
  8. `finance` (finance_id PK, date DATETIME, type ENUM('income','expense'), category, pool ENUM('restock','operating','profit'), amount INT, description NULL, user_id FK RESTRICT)
  9. `settings` (settings_id PK=1, business_name, business_address, table_count INT, receipt_footer, operating_percent DECIMAL(5,2), qris_bank, qris_owner_name, qris_image)
  10. `cash_closings` (extension: closing_id PK, closing_date DATE UNIQUE, user_id FK RESTRICT, opening_cash INT, cash_sales INT, cash_out INT, expected_cash INT, counted_cash INT, difference INT, note VARCHAR(255) NULL, created_at DATETIME)
  11. `login_attempts` (extension: attempt_id PK, username VARCHAR(50), ip_address VARCHAR(45), attempted_at DATETIME; indexed on (username, attempted_at) and (ip_address, attempted_at))
- **Core Business Logic:**
  - Order sequencing: Daily sequential `order_no` starts at 1, resets at 00:00 WIB. Uniqueness is guaranteed by UNIQUE(order_date, order_no); a duplicate-key error is retried.
  - Modifiers: max 5 groups/item, max 10 items/group. Mandatory groups must be selected before adding to cart.
  - Snapshotting: Copy base price/cost and modifier extra price/cost into `orders` and `order_item_modifiers` at checkout. Later menu changes must never mutate past transactions.
  - Profit & 3-Pool Virtual Allocation:
    - total_cost = sum((base_cost + sum(modifier_costs)) * qty)
    - gross_profit = total - total_cost
    - alloc_restock = total_cost
    - alloc_operating = round((operating_percent / 100) * gross_profit)
    - alloc_profit = gross_profit - alloc_operating
    - Invariant: alloc_restock + alloc_operating + alloc_profit === total
  - Double-counting rule: Sales revenue comes exclusively from `transactions`. The `finance` table tracks non-sales income and operational expenses only.
  - Voids: a voided transaction keeps all its rows and amounts (audit trail) but is EXCLUDED from every revenue, cost, profit, pool, performance, dashboard, and summary figure (`status <> 'void'`). It stays visible in history. Its order_no is never reused. Only admins can void; a reason is mandatory. Partial refunds are out of scope (void the whole order and enter a corrected one).
  - Pool balance = allocated (from sales) + manual income (finance, income, that pool) - manual expenses (finance, expense, that pool). The three pool balances must add up to Net Cash Balance.
```

### Project Conventions Block (paste together with the Master Context)

```markdown
### PROJECT CONVENTIONS: KEDAI PARADISE POS (follow exactly; do not invent alternatives)

**Language & formats**
- All staff-facing text is Bahasa Indonesia, taken from `lang/id.php` via `t('key')` (PHP) and `t('key')` (JS, from `window.I18N`). Never hard-code UI strings in pages.
- Quoted English labels in prompts are reference labels only; use the glossary at the bottom of this block.
- Money: integers; display as `Rp14.000` via `formatRupiah()` (PHP and JS). Dates: `dd/mm/yyyy hh:mm` WIB.
- Code, comments, DB names and CSV column headers stay English.

**Folder layout (public_html/)**
- Root pages: index.php, login.php, logout.php, auth_check.php, dashboard.php, pos.php, queue.php, history.php, cash_close.php, menu_manage.php, finance.php, reports.php, menu_performance.php, users.php, settings.php, receipt.php, export_csv.php, test_suite.php
- Endpoints: `api_*.php` in the root (api_modifiers, api_checkout, api_checkout_token, api_toggle_availability, api_complete_order, api_void_order, api_menu, api_finance)
- `config/` (database.php, session.php), `includes/` (bootstrap.php, helpers.php, layout_header.php, layout_footer.php, menu_functions.php), `lang/id.php`, `js/` (common.js, pos.js), `uploads/` (qris/, menu/), `sql/`
- OUTSIDE public_html: `private/config.local.php` (DB credentials + `env` = 'development' or 'production') and `private/logs/`. Reached from `config/database.php` with `dirname(__DIR__, 2) . '/private/config.local.php'`.

**Shared PHP API (includes/helpers.php), reuse these, never re-implement**
- `db(): PDO` (singleton from config/database.php)
- `e(string $s): string` (htmlspecialchars, UTF-8)
- `t(string $key): string` (Indonesian text from lang/id.php)
- `formatRupiah(int $n): string` -> "Rp14.000"
- `formatDateTimeWib(string $dt): string` -> "dd/mm/yyyy hh:mm"
- `redirect(string $url): never`
- `jsonResponse(array $data, int $status = 200): never` and `jsonError(string $message, int $status = 400): never`
- `requireCsrf(): void` (reads header `X-CSRF-Token` or POST field `csrf_token`; 403 on failure)
- `saveUploadedImage(array $file, string $subdir, string $prefix): string` (validates with finfo + getimagesize: JPEG/PNG only, max 2 MB; random file name; extension chosen from the detected type; returns the stored relative path; throws on invalid files)
- Constant `SALES_FILTER` = `"t.status <> 'void'"` for every sales aggregate over `transactions` aliased as `t`.

**Page skeleton**
1. `require __DIR__.'/includes/bootstrap.php';` 2. `require __DIR__.'/auth_check.php';` 3. `requireRole([...])` if restricted 4. POST handling with `requireCsrf()` 5. `include 'includes/layout_header.php'` (takes `$pageTitle`) 6. page HTML 7. `include 'includes/layout_footer.php'`. `receipt.php` and `print_test.html` use no layout.

**API rules**
- Endpoints that change data are POST-only, call `requireCsrf()`, check the role, and use prepared statements only.
- JSON success: `{ "success": true, ...fields }`. JSON error: `{ "success": false, "message": "<Indonesian text>" }` with a proper HTTP status (400 validation, 401 not logged in, 403 role/CSRF, 404 not found, 409 conflict/consumed token, 500 server).
- JS calls endpoints only through `apiFetch(url, options)` in `js/common.js` (adds CSRF header, parses JSON, throws on `success:false`). Other JS helpers: `formatRupiah(n)`, `t(key)`, `showToast(message, type)`.

**SQL rules**
- Sales aggregates always exclude voids. Filter by day with `order_date` (e.g. `order_date = CURDATE()`), never `DATE(date)`. Date ranges: `order_date BETWEEN :from AND :to`.
- No hard deletes on `users`, `menu`, `transactions`, `orders`.

**Roles & navigation (layout_header.php builds this from $_SESSION['role'])**
- admin: Dasbor, Kasir, Antrian, Riwayat, Tutup Kasir, Menu, Keuangan, Laporan, Performa Menu, Pengguna, Pengaturan
- cashier: Kasir, Antrian, Riwayat, Tutup Kasir
- (Adjust to BR-12 if the SRS differs.)

**Style:** Tailwind CDN; Forest Green #1B4D3E (primary), Slate #2C3E50 (text/headers); mobile-first, no horizontal scroll at 360px.

**Glossary (English reference label -> Indonesian UI text)**
Login -> Masuk; Logout -> Keluar; Incorrect username or password -> Username atau password salah; You do not have access to this feature -> Anda tidak memiliki akses ke fitur ini; Dashboard -> Dasbor; POS -> Kasir; Queue -> Antrian; History -> Riwayat; Cash Closing -> Tutup Kasir; Menu -> Menu; Finance -> Keuangan; Reports -> Laporan; Menu Performance -> Performa Menu; Users -> Pengguna; Settings -> Pengaturan; Sold Out -> Habis; Available -> Tersedia; Options -> Pilihan; Mandatory -> Wajib; Add to Cart -> Tambah ke Keranjang; Cart -> Keranjang; Pay/Checkout -> Bayar; Table -> Meja; Take Away (TA) -> Bawa Pulang (TA); Cash -> Tunai; QRIS -> QRIS; Amount Received -> Uang Diterima; Change -> Kembalian; Processing -> Diproses; Completed -> Selesai; Voided -> Dibatalkan; Mark as Done -> Tandai Selesai; Elapsed -> Lama; Orders Completed Today -> Pesanan Selesai Hari Ini; Reprint Receipt -> Cetak Ulang Struk; COPY / REPRINT -> SALINAN / CETAK ULANG; Void Order -> Batalkan Pesanan; Save -> Simpan; Edit -> Ubah; Delete -> Hapus; Cancel -> Batal; Active/Inactive -> Aktif/Nonaktif; Admin/Cashier -> Admin/Kasir; Expense/Income -> Pengeluaran/Pemasukan; Restock pool -> Belanja Bahan; Operating pool -> Operasional; Profit pool -> Laba; Export CSV -> Ekspor CSV; Print/PDF -> Cetak/PDF; Delete this record? Balances will be recalculated -> Hapus catatan ini? Saldo akan dihitung ulang; Thank you for dining with us! -> Terima kasih telah berkunjung!
```

---

## Day 1: Cloud Provisioning, Security, Database & Shared Foundation

### Context to Give the AI

- Master Context + Project Conventions
- `schema (1).sql`
- Hostinger environment specs (Apache-compatible/LiteSpeed, PHP 8.x, MySQL 8.0, SSL installed)

### Prompt 1.1: Web Server Directives & Security Layer

```markdown
Write the production `.htaccess` file for the root directory of the Kedai Paradise POS system on Hostinger. The server may be Apache 2.4 or LiteSpeed (which also reads .htaccess), so wrap module-specific directives in <IfModule> blocks so a missing module can never cause a 500 error.

Requirements:
1. Enforce HTTPS 301 redirection for all incoming HTTP requests.
2. Set HTTP Strict Transport Security (HSTS) with max-age=31536000 and includeSubDomains.
3. Security headers: X-Content-Type-Options: nosniff, X-Frame-Options: SAMEORIGIN, Referrer-Policy: strict-origin-when-cross-origin. Do NOT add X-XSS-Protection (deprecated). Add a Content-Security-Policy compatible with Tailwind via CDN: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'.
4. Disable directory indexing (Options -Indexes).
5. Prevent direct browser access to sensitive files and directories: .git, .env, /sql/, /scripts/, *.sql, *.md, *.sh, *.log, *.bak.
6. Route error 403 and 404 to clean static error responses (messages in Indonesian).
7. Also provide the separate .htaccess file for the /uploads/ directory that:
   a. denies everything by default and only allows files ending in .jpg, .jpeg, .png;
   b. disables PHP execution on every kind of server setup: `php_flag engine off` inside <IfModule mod_php.c> and <IfModule mod_php8.c> blocks, PLUS a FilesMatch rule denying .php, .phtml, .phar, .php[0-9] (needed when PHP runs via PHP-FPM/LiteSpeed, where php_flag has no effect), PLUS Options -ExecCGI -Indexes (NF07).
   Add two lines of comments explaining why both mechanisms are needed.
```

### Prompt 1.2: Core Database Singleton & Session Manager

```markdown
Write two foundational backend scripts for Kedai Paradise POS:

1. `config/database.php`:
   - Singleton PDO wrapper exposed as db(): PDO.
   - Credentials are NOT in this file. Load them with `require dirname(__DIR__, 2) . '/private/config.local.php'` (returns an array: env, db host/name/user/pass). If the file is missing or unreadable, log the real reason to the PHP error log and show only a generic message ("Terjadi kesalahan sistem") - never show paths or credentials.
   - DSN must include charset=utf8mb4.
   - Enforce options: PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false.
   - After connecting, run SET time_zone = '+07:00' as its own separate statement (WIB). Also call date_default_timezone_set('Asia/Jakarta').
   - On connection failure return a clean JSON error (for api_*.php) or a simple Indonesian error page, without exposing credentials.

2. `config/session.php`:
   - Enforce session cookie flags before session_start(): lifetime = 1800 (30 mins), path = '/', domain = '', secure = true, httponly = true, samesite = 'Strict'.
   - If session is active and (time() - $_SESSION['last_activity']) > 1800, destroy session and redirect to login.php?error=expired.
   - Update $_SESSION['last_activity'] = time() on every non-expired request.
   - Implement anti-CSRF utility functions: generateCsrfToken() and validateCsrfToken($token), using random_bytes and hash_equals.

Also output the template for `private/config.local.php` (placeholders only) and a one-line reminder of where it must be placed.
```

### Prompt 1.3: Shared Foundation (Helpers, Layout, Language, Navigation)

```markdown
Write the shared foundation files that every later page will reuse. Follow the Project Conventions Block exactly.

1. `includes/bootstrap.php`:
   - Loads private config, then config/session.php, config/database.php, includes/helpers.php, lang/id.php.
   - Error handling by environment (from `env` in private/config.local.php): 'development' -> display_errors=1, error_reporting(E_ALL); 'production' -> display_errors=0, log_errors=1, error_log to private/logs/php-error.log, and a generic Indonesian error page for uncaught exceptions.
2. `includes/helpers.php`: implement exactly the shared API listed in the conventions (db, e, t, formatRupiah, formatDateTimeWib, redirect, jsonResponse, jsonError, requireCsrf, saveUploadedImage, SALES_FILTER).
3. `includes/layout_header.php` / `includes/layout_footer.php`:
   - Header takes $pageTitle; outputs <head> (viewport meta, Tailwind CDN), a responsive role-aware navigation bar (links per the conventions, active-page highlight, hamburger on mobile), logged-in name + "Keluar" button, and a flash-message area.
   - Injects `window.CSRF_TOKEN` and `window.I18N` (only keys starting with `js_`) for JavaScript.
4. `lang/id.php`: returns an associative array of every Indonesian string from the glossary plus common messages (validation errors, success messages). Keys in English snake_case.
5. `js/common.js`: formatRupiah(n), t(key), apiFetch(url, options), showToast(message, type).
6. `index.php`: redirect to login.php if not logged in, otherwise to the role's home (admin -> dashboard.php, cashier -> pos.php).
7. Small `.htaccess` files containing "Require all denied" for config/, includes/, and lang/ (their files must only be reachable through PHP includes).

Finish by listing the final function signatures of helpers.php and common.js in one short block, so I can paste that list into future AI sessions.
```

### Prompt 1.4: Schema Extension Migration

```markdown
Write `sql/migration_001_extensions.sql` for MySQL 8.0/MariaDB, to run once after importing schema (1).sql and demo_data.sql. Match data types and charset/collation of the existing users.user_id and transactions.transaction_id.

1. ALTER TABLE transactions:
   - change status to ENUM('processing','completed','void') NOT NULL DEFAULT 'processing';
   - add void_reason VARCHAR(255) NULL, voided_at DATETIME NULL, voided_by (same type as users.user_id) NULL with a FOREIGN KEY to users ON DELETE RESTRICT;
   - add order_date DATE GENERATED ALWAYS AS (DATE(`date`)) STORED, plus UNIQUE KEY uq_order_no_per_day (order_date, order_no) and an index on order_date.
2. CREATE TABLE cash_closings and login_attempts exactly as in the Master Context (InnoDB, utf8mb4, indexes as listed).
3. Put a comment at the top: "Take an Export backup in phpMyAdmin before running", and a final SELECT that shows the new column list of transactions so I can verify the result.
4. If existing demo rows could violate the unique key, add a check query above the ALTER statements that lists duplicates (order_date, order_no).
```

### Prompt 1.5: Thermal Printer Test Page

```markdown
Write a static `print_test.html` (no PHP, no dependencies) that tests printing on the real thermal printer.

Requirements:
1. Selectable width through the URL: `?w=58` (default) or `?w=80`. Use `@page { size: <width>mm auto; margin: 0 }` and a print stylesheet (monochrome, high-contrast, 4mm inner padding, monospace font, no background colors).
2. Sample receipt content in Indonesian that stress-tests the layout: shop header, a ruler line of 32 and 48 characters to measure how many characters fit per line, one very long item name that must wrap, an item with indented modifiers on the next line (`  [Level 3, Telur Tambahan]`), right-aligned Rupiah amounts, Total / Tunai / Kembalian, a "SALINAN / CETAK ULANG" label, and a footer.
3. On screen only (hidden when printing): a "Cetak Tes" button and a short checklist of browser print settings (Paper: Roll/receipt size; Margins: None; Headers and footers: off; Scale: 100%).
4. A `<meta name="robots" content="noindex">` tag.
```

### Verification & Test Action

- Import `schema (1).sql` and `demo_data.sql` via phpMyAdmin, **export a backup**, then run `sql/migration_001_extensions.sql`. Confirm `transactions` has the new columns and both new tables exist.
- Test navigating to `http://yourdomain.com` and ensure it auto-redirects to `https://`.
- Visit `https://yourdomain.com/config/database.php`, `/includes/helpers.php`, and `/sql/` : all must be denied (403).
- Upload a harmless `test.php` (containing `<?php echo "x";`) into `/uploads/` and open it in the browser: it must NOT execute (403 or plain text). Delete it afterwards.
- Run a test PHP file including `includes/bootstrap.php` and verify `SELECT NOW()` returns the current Asia/Jakarta (WIB) time.
- **Hardware gate (do this now, with the real device):** connect the real thermal printer to the real counter tablet/PC, open `print_test.html?w=58` (or `?w=80`), and print. Check: no cut-off text, no blank paper feed, correct characters per line, indented modifiers readable. Note the exact browser settings that worked. If `window.print()` is unreliable, decide the alternative **now** (for example Chrome's `--kiosk-printing` shortcut flag on Windows/Linux to skip the print dialog, the printer vendor's driver, or an Android print-bridge app for Bluetooth printers), because it can change how `receipt.php` is built on Day 5.

---

## Day 2: Authentication Engine & User Management UI (SRS-F01, SRS-F02)

### Context to Give the AI

- Master Context + Project Conventions + the function-signature list produced in Prompt 1.3
- Table `users` definition
- Business rules BR-12 (Role boundaries) and BR-13 (Account preservation)

### Prompt 2.1: Login, Session Verification & Logout

```markdown
Write `login.php`, `logout.php`, `auth_check.php`, and a placeholder `dashboard.php` for Kedai Paradise POS, following the Project Conventions (bootstrap, layout, t(), jsonError, etc.).

1. `login.php`:
   - Tailwind CSS CDN (Palette: Forest Green #1B4D3E, Slate #2C3E50). Modern, responsive card layout with username and password fields and CSRF token. All text in Indonesian.
   - Backend logic: query table `users` by username. Check `status == 1`. Verify password using password_verify() against password_hash. For an unknown username, still run password_verify() against a fixed dummy hash so response time does not reveal whether the username exists.
   - Rate limiting using table `login_attempts`: record every failed attempt (username + IP). If there are 5 or more failures in the last 15 minutes for the same username OR the same IP, reject before checking the password with "Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit." On success, delete that username's attempts. Opportunistically delete rows older than 1 day.
   - On success: session_regenerate_id(true), store user_id, username, name, role in session, and redirect: role === 'admin' -> dashboard.php, role === 'cashier' -> pos.php.
   - On failure: return the generic message "Username atau password salah" (same message for unknown user, wrong password, and inactive account).
2. `logout.php`: clears session variables, deletes session cookie, destroys session, and redirects to login.php.
3. `auth_check.php`: middleware included at the top of protected pages. If user is not logged in, redirect to login.php. Function requireRole($allowedRoles) checks $_SESSION['role']; if unauthorized, return HTTP 403 Forbidden with message "Anda tidak memiliki akses ke fitur ini" (BR-12).
4. `dashboard.php`: a minimal placeholder (admin only) using the shared layout, saying "Dasbor - segera hadir". The full version is built on Day 7.
```

### Prompt 2.2: Full User Management UI & CRUD

```markdown
Write `users.php` (Admin only) for staff account management, following the Project Conventions.

Requirements:
1. Enforce requireRole(['admin']) using auth_check.php.
2. Display a responsive Tailwind CSS table listing all users from table `users`: Name, Username, Role badge (Admin / Kasir), Status badge (Aktif / Nonaktif), and Action buttons.
3. Include an "Add New User" modal with CSRF token:
   - Fields: Full Name, Username (unique), Password (min 8 chars), Role (select: admin, cashier).
   - Backend inserts into `users` using password_hash($password, PASSWORD_BCRYPT).
4. Include an "Edit User / Reset Password" modal: allows updating Full Name, Role, and optional Password reset.
5. Implement Account Status Toggle (Active / Inactive). Enforce Business Rule BR-13: an admin CANNOT deactivate their own currently logged-in account, and CANNOT deactivate the last remaining active admin in the database.
6. Prevent hard deletion: users can only be set to `status = 0`, never deleted via DELETE queries (BR-13).
```

### Verification & Test Action

- Log in with default seed account (`fajar` / `Admin#12345`). Navigation bar shows the admin links; `dashboard.php` placeholder loads.
- Create a new cashier account (`dewi_test`).
- Try to deactivate `fajar` while logged in as `fajar` (must show error and reject).
- Log out and log in as `dewi_test`; nav shows only cashier links; attempt to load `users.php` directly (must receive 403 Forbidden).
- Enter a wrong password 5 times for `dewi_test`: the 6th attempt (even with the right password) must show the lockout message. Check rows in `login_attempts`.

---

## Day 3: Settings, Menu & Modifier Management, POS Catalog (SRS-F12, SRS-F03, SRS-F04)

> This is a heavy day. If it overruns, carry Prompt 3.3 into Day 4 morning; the rest of the plan has slack for this.

### Context to Give the AI

- Master Context + Project Conventions + signature list
- Tables `settings`, `menu`, `menu_modifier_groups`, `menu_modifier_items`
- Business rule BR-01 (Menu availability) and the modifier rules BR-16 to BR-20

### Prompt 3.1: Settings Management Portal (Business, Tables, QRIS)

```markdown
Write `settings.php` (Admin only) allowing the owner to configure business and payment parameters, following the Project Conventions.

Requirements:
1. Query the single record in table `settings` (`WHERE settings_id = 1`).
2. Form layout using Tailwind CSS with sections:
   - Business Profile: business_name (required), business_address (optional), receipt_footer (optional).
   - Table Capacity: table_count (integer >= 1).
   - Profit Allocation: operating_percent (decimal 0.00 to 100.00, default 50.00) with Indonesian help text explaining the 3-pool split.
   - QRIS Configuration: qris_bank (e.g. BCA, Mandiri), qris_owner_name, and static QRIS image upload input.
3. File Upload Processing: use the shared saveUploadedImage() helper (JPEG/PNG only, max 2 MB, validated by real file content) with subdir `qris` and prefix `qris`. After saving the new file, update `qris_image` and delete the previous file from disk.
4. Validation: enforce table_count >= 1 and operating_percent BETWEEN 0 AND 100. Display a success alert upon saving.
```

### Prompt 3.2: Menu & Modifier Management

```markdown
Write `menu_manage.php` (Admin only), `api_menu.php`, and `includes/menu_functions.php` so the owner can maintain the menu and modifier options. Follow the Project Conventions.

1. Menu list (Tailwind table, responsive): photo thumbnail, name, price, cost price, margin (price - cost), availability toggle, status badge, actions. Search box and a filter to show inactive items.
2. Add/Edit modal: name (required, max 100), price (integer >= 0), cost_price (integer >= 0, show a warning if cost > price), photo (optional, via saveUploadedImage() with subdir `menu`, prefix `menu`; replacing a photo deletes the old file), available (switch), status (switch).
3. No hard delete: "Nonaktifkan" sets status = 0 (past orders reference the item); an inactive item can be reactivated.
4. Modifier editor for each menu item (expandable panel or modal):
   - Groups: name, type (single/multiple), is_required.
   - Items in a group: name, extra_price (>= 0), extra_cost (>= 0), available toggle.
   - Limits enforced SERVER-SIDE in includes/menu_functions.php by functions `createModifierGroup()` and `createModifierItem()` that throw an exception when a menu item already has 5 groups or a group already has 10 items (the Day 9 test calls these functions). Show the exception message in Indonesian.
   - Groups and items may be deleted after a confirmation dialog ("Transaksi lama tidak terpengaruh"), because past orders keep their own frozen snapshot with no foreign key to these tables.
   - Show a warning (not a block) when a required group has no available item, because the cashier would be unable to add that menu item to the cart.
5. All writes go to `api_menu.php` via POST with an `action` parameter (menu_create, menu_update, menu_toggle_status, group_create, group_update, group_delete, item_create, item_update, item_delete), CSRF-checked, admin-only, prepared statements, JSON shapes per the conventions.
6. cost_price and extra_cost must only appear on admin pages. They must never be sent to cashier-facing endpoints.
```

### Prompt 3.3: Cashier POS Shell, Menu Catalog View & Availability Toggle

```markdown
Write `pos.php` and `api_toggle_availability.php` for cashier and admin order-taking, following the Project Conventions.

Requirements:
1. Two-column responsive layout (Desktop: 65% Catalog Grid, 35% Active Cart; Mobile: Stacked view).
2. Left Column (Menu Catalog):
   - Fetch active items (`status = 1`) from table `menu`.
   - Display product cards: item photo (or clean fallback icon), item name, and formatted price (e.g., "Rp14.000").
   - If the item has modifier groups in `menu_modifier_groups`, render a visible badge "Pilihan".
   - If `available == 0`, visually dim the card, display a "Habis" overlay, and disable click events (BR-01).
   - Include a small toggle button on each card to switch an item between Tersedia and Habis via AJAX.
3. `api_toggle_availability.php`: POST { menu_id, available (0|1) }, CSRF-checked, any logged-in role, prepared statement, updates only menu.available, returns { success: true, available }. Reject unknown or inactive menu_id with 404.
4. Right Column (Active Cart Shell): empty container for order items, table selection dropdown (1 to table_count from settings + "Bawa Pulang (TA)"), payment selector area, total display, and checkout button. Embed `window.CHECKOUT_TOKEN` (see Prompt 5.1) and load js/common.js and js/pos.js.
```

### Verification & Test Action

- Open `settings.php`, upload a QRIS barcode image, set table count to 15, and save. Check that `settings_id = 1` reflects the new values. Try uploading a `.php` file renamed to `.png`: it must be rejected.
- In `menu_manage.php`, add a new item with a photo, add a required "Tingkat Pedas" group and an optional "Tambahan" group with items. Try adding a 6th group to one item: it must be refused with an Indonesian message.
- Deactivate and reactivate an item.
- Load `pos.php`, toggle an item to "Habis", refresh, and confirm the item stays disabled. Confirm `cost_price` never appears in the page source or in any cashier-facing network response.

---

## Day 4: Modifiers Selection Modal & Live Reactive Cart (SRS-F03, SRS-F04)

### Context to Give the AI

- Master Context + Project Conventions + signature list
- Tables `menu`, `menu_modifier_groups`, `menu_modifier_items`
- Business rules BR-02 (Effective unit price), BR-16 to BR-20 (Modifier options rules & cart line merging)

### Prompt 4.1: Modifiers Data API Endpoint

```markdown
Write `api_modifiers.php` which returns option configuration for a selected menu item, following the Project Conventions.

Requirements:
1. Accept `GET ?menu_id=X`. Validate menu_id is an integer > 0 and that the menu item exists and is active.
2. Query `menu_modifier_groups` where `menu_id = X` ordered by group_id ASC.
3. For each group, query all available items from `menu_modifier_items` where `group_id = group_id` and `available = 1`. Never return cost fields.
4. Return a clean JSON response structured as:
   {
     "success": true,
     "menu_id": 2,
     "base_price": 14000,
     "groups": [
       {
         "group_id": 1,
         "name": "Tingkat Pedas",
         "type": "single",
         "is_required": 1,
         "items": [
           { "modifier_id": 1, "name": "Level 1", "extra_price": 0 },
           { "modifier_id": 3, "name": "Level 3", "extra_price": 0 }
         ]
       },
       {
         "group_id": 2,
         "name": "Tambahan",
         "type": "multiple",
         "is_required": 0,
         "items": [
           { "modifier_id": 4, "name": "Telur Tambahan", "extra_price": 3000 },
           { "modifier_id": 5, "name": "Sosis", "extra_price": 4000 }
         ]
       }
     ]
   }
5. Handle error states with appropriate JSON HTTP headers.
```

### Prompt 4.2: Client-Side Modal Controller & Reactive Cart Engine

```markdown
Write `js/pos.js` (loaded by pos.php, after js/common.js) managing the modal selection, effective unit price calculation, and the reactive cart. Use apiFetch(), formatRupiah(), t() and showToast() from js/common.js. All visible text in Indonesian.

Requirements:
1. Modal Controller:
   - When a menu card is clicked: if it has no modifiers, add directly to cart with quantity 1.
   - If it has modifiers, open the Option Selection Modal and fetch data from `api_modifiers.php?menu_id=X`.
   - Render groups: `single` groups render radio buttons; `multiple` groups render checkboxes.
   - If `is_required == 1`, show a red "Wajib" badge.
   - Dynamically compute and display Effective Unit Price live in the modal footer: Base Price + sum(checked modifier extra prices) (BR-02).
   - Validate mandatory groups: disable the "Tambah ke Keranjang" button until every mandatory group has an active selection (BR-17).
2. Reactive Cart Array:
   - Cart item structure: { menu_id, name, base_price, quantity, modifiers: [ { modifier_id, name, extra_price } ], unit_price, subtotal }.
   - Cart Merging (BR-20): when adding an item, check if an existing cart line has the exact same menu_id AND the identical set of modifier_ids. If yes, increment quantity. If any modifier differs, append as a new cart row.
   - Render Cart UI: list items with selected modifiers indented under the item title (e.g. `Mie Goreng [Level 3, Telur Tambahan]`), unit price, subtotal, quantity steppers (+/-), and a delete icon.
   - Update line subtotals and overall grand total reactively in <= 0.5s without reloading the page (NF01).
   - Expose a small public API for the checkout code written in Prompt 5.2: getCart(), getTotal(), clearCart().
```

### Verification & Test Action

- In POS, click "Mie Goreng" (Fried Noodles; has a mandatory single Spice Level group and an optional multiple Toppings group).
- Verify "Tambah ke Keranjang" stays disabled until a Spice Level radio is selected.
- Check "Telur Tambahan" (+Rp3.000); verify modal shows unit price Rp17.000 (14.000 + 3.000).
- Click "Tambah ke Keranjang". Add the identical selection again; verify the cart row quantity increments to 2 with subtotal Rp34.000.
- Add Mie Goreng with "Level 1" (no egg); verify it appears as a separate line.

---

## Day 5: Checkout API, Payment UI, 3-Pool Math & Thermal Receipt (SRS-F05, F06, F07, F13)

### Context to Give the AI

- Master Context + Project Conventions + signature list
- Tables `transactions`, `orders`, `order_item_modifiers`
- Business rules BR-03, BR-05, BR-06, BR-08, BR-09, BR-21, BR-22, BR-26
- Worked Test Case TC-D17 for arithmetic verification

### Prompt 5.1: Atomic Checkout, Token Flow & 3-Pool Allocation Engine

```markdown
Write `api_checkout.php` and `api_checkout_token.php` handling order finalization, following the Project Conventions.

Requirements:
1. Accept JSON POST: { table_no, method, paid, checkout_token, items: [ { menu_id, quantity, modifier_ids: [...] } ] } with the X-CSRF-Token header.
2. Checkout token flow (BR-26, TC-D15):
   - pos.php stores a random 32-byte hex token in $_SESSION['checkout_token'] and exposes it as window.CHECKOUT_TOKEN.
   - api_checkout.php compares the sent token with hash_equals(), then IMMEDIATELY removes it from the session (consumption), before any other processing. A missing, mismatched or already-consumed token returns HTTP 409 with no database writes.
   - EVERY response (success or error) includes `next_token`, a freshly generated token stored in the session, so the client always has a valid token for its next attempt.
   - `api_checkout_token.php` (logged-in GET) issues and returns a fresh token { success: true, token } for recovery after a network failure.
3. Input validation: items not empty; quantity integer 1..99; table_no is an integer 1..table_count (from settings) or 'TA'; method is 'cash' or 'qris'; every menu item exists, has status = 1 and available = 1.
4. Atomic Database Transaction: wrap execution inside `$pdo->beginTransaction()` and `try/catch`.
5. Server-Side Price, Cost & Modifier Rule Recalculation (BR-21):
   - Never trust client-sent prices. For every item, query base price and base cost from `menu`.
   - For every modifier_id, verify it belongs to a group of that menu item and is available; query extra_price and extra_cost from `menu_modifier_items`. Verify every mandatory group has a selection and every `single` group has at most one.
   - Calculate: unit_price = base_price + sum(extra_prices); unit_cost = base_cost + sum(extra_costs); line_subtotal = unit_price * qty; line_cost = unit_cost * qty.
   - Compute order total = sum(line_subtotals) and total_cost = sum(line_costs).
6. Payment Validation (BR-03):
   - If method === 'cash', verify paid >= total; change_due = paid - total.
   - If method === 'qris', paid = total; change_due = 0.
7. Daily Sequential Order Number (BR-06):
   - Inside the transaction: `SELECT COALESCE(MAX(order_no), 0) + 1 FROM transactions WHERE order_date = CURDATE() FOR UPDATE;`
   - The database also enforces UNIQUE(order_date, order_no). If the insert fails with duplicate-key error 1062, roll back and retry the whole transaction up to 3 times with a fresh number.
8. Three-Pool Virtual Allocation Engine (SRS-F13, BR-08, BR-09):
   - Query `operating_percent` from table `settings`.
   - gross_profit = total - total_cost.
   - alloc_restock = total_cost.
   - alloc_operating = (int) round((operating_percent / 100) * gross_profit).
   - alloc_profit = gross_profit - alloc_operating.
   - Verify invariant: alloc_restock + alloc_operating + alloc_profit === total.
9. Multi-Table Insert:
   - Insert into `transactions` (including pool columns, status='processing', user_id).
   - Insert each line into `orders` (saving frozen base price, base cost, modifier_price, modifier_cost, subtotal).
   - Insert frozen snapshots into `order_item_modifiers` for every chosen option (modifier_name, price=extra_price, cost_price=extra_cost) (BR-05, BR-22).
10. Commit & Return: call `$pdo->commit()` and return JSON: { success: true, transaction_id, order_no, next_token }. On error call `$pdo->rollBack()` and return { success: false, message, next_token }.
```

### Prompt 5.2: Checkout & Payment UI (Cash / QRIS)

```markdown
Extend `pos.php` and `js/pos.js` with the checkout and payment experience, following the Project Conventions. All text in Indonesian.

1. Payment selector: two large buttons, "Tunai" and "QRIS". Table dropdown is required (no default value). The "Bayar" button is disabled while the cart is empty, no table is chosen, or no payment method is chosen.
2. Tunai: numeric input "Uang Diterima" with live thousands separators; quick buttons "Uang Pas", Rp20.000, Rp50.000, Rp100.000; live "Kembalian" in green, or "Kurang Rp X" in red; "Bayar" stays disabled until paid >= total.
3. QRIS: opens a modal showing the QRIS image from settings, bank, account owner name, and the total in large type, with the button "Pembayaran Sudah Diterima". If no QRIS image is configured, show a warning that it must be set in Pengaturan.
4. Submission: POST JSON to api_checkout.php through apiFetch() with window.CHECKOUT_TOKEN. Double-click guard: disable the button and show a spinner until the response arrives. Open a blank window synchronously inside the click handler (`const w = window.open('', '_blank')`) so popup blockers do not stop it, then point it at `receipt.php?id=<transaction_id>` when the response arrives; close it on error.
5. On success: clear the cart (clearCart()), reset table and payment, store `next_token` in window.CHECKOUT_TOKEN, and show the toast "Pesanan #<order_no> berhasil disimpan".
6. On error: show the server message, keep the cart intact, and store `next_token`. On network failure show "Koneksi bermasalah. Jangan kirim ulang; periksa Riwayat dulu", then fetch a fresh token from api_checkout_token.php.
```

### Prompt 5.3: Printable Thermal Receipt View

```markdown
Write `receipt.php` to display and print transaction receipts, following the Project Conventions (no layout wrapper). Reuse the print settings that worked in `print_test.html` on Day 1.

Requirements:
1. Accept `GET ?id=transaction_id` (and optional `&reprint=1`, `&w=58|80`). Require a logged-in user. Query `transactions`, `orders`, and `order_item_modifiers` joined with `settings`.
2. Styling: CSS `@media print` optimized for 58mm and 80mm thermal receipt printers (monochrome, high-contrast, compact margins), consistent with the Day 1 printer test.
3. Content Breakdown (all labels in Indonesian):
   - Shop Header: business_name, business_address (from settings).
   - Transaction Meta: No. Pesanan, Meja/TA, Tanggal/Waktu (dd/mm/yyyy hh:mm WIB), Kasir.
   - Line Items: item name, quantity, line total. Indent chosen modifiers directly beneath the item name in brackets (e.g., `  [Level 3, Telur Tambahan]`) using the frozen snapshot from `order_item_modifiers` (SRS-F07).
   - Financials: Subtotal, Total, Metode, Dibayar, Kembalian.
   - Status & Footer: "Status: DIPROSES" (or SELESAI), and receipt_footer text.
4. Reprint Watermark: the first print right after checkout (no `reprint` parameter) has no label. When `reprint=1` (used by the history page), add the visible label "SALINAN / CETAK ULANG" (SRS-F07).
5. Void marking: if the transaction status is 'void', print a prominent "DIBATALKAN" banner at the top regardless of the reprint parameter.
6. Auto-print: `<script>window.onload = function() { window.print(); }</script>` unless `?autoprint=0` is present.
```

### Verification & Test Action

- Execute the multi-item test order matching TC-D17:
  - 2x Mie Goreng [Level 3, Telur Tambahan] (Selling: 17.000 ea, Cost: 11.000 ea).
  - 1x Kopi Susu [Large] (Selling: 22.000, Cost: 11.500).
  - 1x Es Teh (Selling: 5.000, Cost: 2.000).
- Pay with Tunai Rp70.000 and confirm the change display shows Rp9.000 before pressing "Bayar".
- Check the database row in `transactions`:
  - `total` must be **61000**, `total_cost` **35500**, `gross_profit` **25500**, `alloc_restock` **35500**, `alloc_operating` **12750**, `alloc_profit` **12750**, `change_due` **9000**.
- Verify the receipt renders all items and indented modifier names correctly, **on the real thermal printer**.
- Double-click "Bayar" quickly: exactly one transaction must be created. Replay the same request with an old token (browser dev tools): it must return 409 and create no row.
- Place a QRIS order and confirm the QRIS modal shows the uploaded image, bank, and owner name.

---

## Day 6: Kitchen Queue, Cashier History & Order Voids (SRS-F08, SRS-F09)

### Context to Give the AI

- Master Context + Project Conventions + signature list
- Table `transactions` status and timing columns
- Business rule BR-07 (Order queue timing and duration)

### Prompt 6.1: Live Kitchen Queue Dashboard

```markdown
Write `queue.php` and `api_complete_order.php` for order fulfillment tracking, following the Project Conventions. All text in Indonesian.

1. `queue.php`:
   - Fetch all transactions with `status = 'processing'` ordered by `date ASC` (voided orders never appear here).
   - Query associated items and frozen modifiers from `orders` and `order_item_modifiers`.
   - Display active tickets as cards: Order Number (large), Meja/TA badge, order time, live running elapsed timer ticker (e.g., "Lama: 04:15"), and item list with indented modifiers.
   - Each card has a green action button: "Tandai Selesai".
   - Below the active cards, display a secondary table: "Pesanan Selesai Hari Ini" showing No. Pesanan, Meja, Waktu Pesan, Waktu Selesai, and fulfillment duration (formatted mm:ss). Exclude voided orders.
   - Auto-refresh active tickets every 15 seconds via AJAX without reloading the entire page.
2. `api_complete_order.php`:
   - Accept POST { transaction_id } with CSRF.
   - Validate status is currently 'processing' (reject 'completed' and 'void' with 409).
   - Update `transactions`: `completed_at = NOW()`, `duration_seconds = TIMESTAMPDIFF(SECOND, date, NOW())`, `status = 'completed'`.
   - Return JSON success response.
```

### Prompt 6.2: Cashier Daily Transaction History Ledger

```markdown
Write `history.php` providing a transaction log, following the Project Conventions. All text in Indonesian.

Requirements:
1. Role-Based Scoping (BR-12, BR-25):
   - If user is a `cashier`: strictly lock the date filter to today (`order_date = CURDATE()`). Hide columns: total_cost, gross_profit, and pool allocations.
   - If user is an `admin`: allow arbitrary date range filters (from_date to to_date) and display full financial columns.
2. Display a responsive Tailwind CSS table:
   - No. Pesanan, Tanggal/Waktu, Meja/TA, Metode badge (Tunai/QRIS), Total, Status badge (Diproses/Selesai/Dibatalkan), Durasi (mm:ss), and Aksi.
   - Voided rows are greyed out with a strikethrough total; the expanded row also shows the void reason, who voided it, and when.
   - The footer totals row EXCLUDES voided orders.
   - Clicking an order row expands to show ordered items and frozen modifier snapshots.
   - Action buttons: "Cetak Ulang Struk" (opens `receipt.php?id=X&reprint=1` in a new tab); for admins only, "Batalkan Pesanan" on non-void rows (see Prompt 6.3).
3. Client-Side Sorting: allow clicking column headers (Tanggal, No. Pesanan, Total, Durasi) to toggle ascending/descending order dynamically (BR-23).
```

### Prompt 6.3: Order Void (Cancellation)

```markdown
Write `api_void_order.php` and add the void flow to `history.php`, following the Project Conventions. All text in Indonesian.

1. `api_void_order.php`: POST { transaction_id, reason } with CSRF. Admin only (403 otherwise). `reason` is required, 3 to 255 characters.
2. Inside a database transaction: `SELECT ... FOR UPDATE` the transaction. If it does not exist return 404; if it is already 'void' return 409. Otherwise update status = 'void', void_reason, voided_at = NOW(), voided_by = current user. Do NOT change or delete any amounts, pool columns, orders, or order_item_modifiers rows (audit trail), and never reuse the order number.
3. `history.php`: the admin's "Batalkan Pesanan" button opens a modal with a required reason field and a confirmation text ("Pesanan akan dikeluarkan dari semua laporan. Pengembalian uang dilakukan manual."). On success the row updates without a page reload.
4. Policy notes (put them in code comments): only whole-order voids exist; partial refunds are done by voiding the order and entering a corrected one; cash refunds are handled manually at the counter.
```

### Verification & Test Action

- Place an order in POS. Check `queue.php`; confirm the ticket appears with live elapsed ticker and indented modifiers.
- Wait 60 seconds; click "Tandai Selesai".
- Check `transactions` table: `status` is now 'completed', `completed_at` is stamped, and `duration_seconds` is ~60.
- Log in as cashier; verify `history.php` only shows today's sales and hides profit/cost metrics, and shows no "Batalkan Pesanan" button.
- As admin, void a test order with a reason. Confirm: row is greyed out, the amounts in the database are unchanged, it disappears from the queue, history totals exclude it, and the receipt reprint shows "DIBATALKAN". Try voiding it again: must be rejected.

---

## Day 7: Admin Financial Reports & Executive Dashboard (SRS-F10, SRS-F11)

### Context to Give the AI

- Master Context + Project Conventions + signature list
- Aggregation queries across `transactions`, `orders`, `finance`, and `order_item_modifiers`
- Business rules BR-10, BR-11 (Ledger reconciliation & pool balances)

### Prompt 7.1: Sales & Financial Summary Report

```markdown
Write `reports.php` (Admin only) generating the complete sales and financial audit report for a specified date range (`from_date` to `to_date`), following the Project Conventions. All text in Indonesian. Every aggregate over `transactions` uses SALES_FILTER (excludes voids).

Requirements:
1. Executive KPI Summary Cards:
   - Total Gross Revenue = sum(total) from transactions.
   - Total Gross Profit = sum(gross_profit) from transactions.
   - Total Net Profit = sum(alloc_profit) from transactions.
   - Total Expenses = sum(amount) from finance where type='expense'.
   - Manual Income = sum(amount) from finance where type='income'.
   - Net Cash Balance = Revenue + Manual Income - Expenses (BR-11).
   - Info card (NOT included in any total): number and total value of voided orders in the period.
2. Balance per Fund Pool Table, with columns Allocated, Manual Income, Expenses, Net Pool Balance (= Allocated + Manual Income - Expenses), for each of:
   - Belanja Bahan (restock): Allocated = sum(alloc_restock); income/expenses = finance rows where pool='restock'.
   - Operasional (operating): Allocated = sum(alloc_operating); income/expenses = finance rows where pool='operating'.
   - Laba (profit): Allocated = sum(alloc_profit); income/expenses = finance rows where pool='profit'.
   - Reconciliation check: the three Net Pool Balances must add up to Net Cash Balance. Show a green "Saldo sesuai" badge, or a red warning with the difference if they do not match.
3. Revenue by Payment Method Table: Tunai vs. QRIS totals and percentage breakdown.
4. Sortable Daily Breakdown Table: group by `order_date`: Tanggal, Jumlah Pesanan, Penjualan Kotor, Total Biaya, Laba Kotor, Laba Bersih, Rata-rata Durasi.
5. UI Layout: "Ekspor CSV" button (links to export_csv.php?report=summary) and "Cetak/PDF" button (triggers window.print() formatted via @media print) (SRS-F10).
```

### Prompt 7.2: Menu Item Performance Analytics Table

```markdown
Write `menu_performance.php` (Admin only) to analyze individual item and modifier sales performance for a date range, following the Project Conventions. Exclude voided transactions.

Requirements:
1. Group by `orders.menu_id` joined with `menu` and `transactions` within the selected period.
2. Compute columns:
   - Menu Item Name
   - Quantity Sold = sum(orders.quantity)
   - Base Sales = sum(orders.price * orders.quantity)
   - Modifier Sales Volume = sum(orders.modifier_price * orders.quantity)
   - Gross Sales = sum(orders.subtotal)
   - Total Cost = sum((orders.cost_price + orders.modifier_cost) * orders.quantity)
   - Gross Profit = Gross Sales - Total Cost
3. Support ascending/descending sorting on every column via JavaScript.
4. Include a dedicated "Ekspor CSV" button linking to `export_csv.php?report=menu_performance`.
```

### Prompt 7.3: Executive Dashboard

```markdown
Replace the placeholder `dashboard.php` (Admin only) with the real dashboard, following the Project Conventions. All text in Indonesian. Exclude voided orders from every figure.

1. Today's KPI cards: revenue, number of orders, orders currently in the queue (processing), average fulfillment duration (mm:ss), Tunai vs QRIS split.
2. A bar chart of daily revenue for the last 7 days, drawn with plain CSS or inline SVG (no chart libraries).
3. Status line: "Tutup Kasir hari ini: sudah / belum" (from table cash_closings).
4. Quick-link buttons to Kasir, Antrian, Laporan, Keuangan.
5. Must load fast on a phone (a single request for the aggregates, no N+1 queries).
```

### Verification & Test Action

- Open `reports.php` across the period of your demo transactions.
- Check that Restock Pool allocation equals the sum of `total_cost` across all non-void sales.
- Check that Net Cash Balance matches: Gross Revenue + Manual Income - Expenses, and that the reconciliation badge reads "Saldo sesuai".
- Verify that a voided test order appears only in the info card and in none of the totals.
- Verify that `menu_performance.php` accurately splits Base Sales from Modifier Sales.
- Open `dashboard.php` on a 360px-wide screen and confirm there is no horizontal scrolling.

---

## Day 8: Manual Ledger, CSV Streaming & Cash Closing (SRS-F14, SRS-NF13)

### Context to Give the AI

- Master Context + Project Conventions + signature list
- Tables `finance` and `cash_closings`
- Business rules BR-10, BR-11, BR-24, BR-25 and SRS-NF13

### Prompt 8.1: Manual Bookkeeping Ledger UI & Backend

```markdown
Write `finance.php` (Admin only) and `api_finance.php` for logging non-sales income and operational business expenses, following the Project Conventions. All text in Indonesian.

Requirements:
1. Form to record entries:
   - Date & Time picker (defaults to current WIB timestamp).
   - Type selector: 'expense' / Pengeluaran (default) or 'income' / Pemasukan.
   - Category input: text with autocomplete suggestions (e.g., "Belanja bahan", "Listrik", "Gaji karyawan", "Sewa lapak").
   - Target Fund Pool selector: Belanja Bahan (restock), Operasional (operating), or Laba (profit) (BR-10).
   - Amount: integer in Rupiah (> 0).
   - Description: optional notes.
2. Transaction Table:
   - Render the table listing past records, with a date-range filter (default: current month) and sorting by Tanggal, Jenis, Kategori, Dana, and Jumlah.
   - Action buttons for Ubah (opens the form pre-filled) and Hapus (with confirmation "Hapus catatan ini? Saldo akan dihitung ulang").
3. `api_finance.php`: POST with `action` = create | update | delete, CSRF-checked, admin-only, prepared statements. Validate type and pool against the allowed enum values, amount > 0 as an integer, date format, category required (max 100 characters). Record user_id on create. Delete is a real delete (per the SRS confirmation wording). Return JSON per the conventions.
4. Add "Ekspor CSV" and "Cetak/PDF" buttons linking to `export_csv.php?report=finance`.
```

### Prompt 8.2: Native Memory-Efficient CSV Streaming Engine

```markdown
Write `export_csv.php` implementing native streaming CSV generation conforming strictly to SRS-NF13 and RFC 4180, following the Project Conventions. CSV column headers stay in English (machine-readable).

Requirements:
1. Accept `GET ?report=X&from=Y&to=Z&delimiter=comma|semicolon`.
2. Role Authentication: verify session. If report is 'summary', 'menu_performance', or 'finance', require role === 'admin'. If report is 'history', allow cashier but restrict period to today and strip cost/profit columns (BR-25, TC-D07).
3. HTTP Streaming Headers:
   - Content-Type: text/csv; charset=UTF-8
   - Content-Disposition: attachment; filename="kedai-paradise_{report}_{from}-{to}.csv"
   - Cache-Control: no-store
4. Excel Compatibility (UTF-8 BOM): stream directly to `php://output`. Write the 3-byte UTF-8 BOM (`\xEF\xBB\xBF`) before emitting any text (TC-D01).
5. Delimiter Handling: use `,` if delimiter === 'comma' (default); use `;` if delimiter === 'semicolon'.
6. CSV Formula Injection Defense (OWASP / NF13 / TC-D04): check every text cell. If the first character is `=`, `+`, `-`, `@`, `\t`, or `\r`, prepend a single quote (`'`) to force spreadsheet applications to treat the cell as literal text. Never alter numeric columns.
7. Report Generation Logic:
   - 'summary': labeled sections (SUMMARY, REVENUE_PER_METHOD, POOL_BALANCE, DAILY_BREAKDOWN) separated by blank rows. Voids are excluded. POOL_BALANCE includes the columns allocated, manual_income, expenses, net_balance.
   - 'menu_performance': headers `menu_name, quantity_sold, gross_sales, modifier_sales, total_cost, gross_profit`. Voids are excluded.
   - 'history': transaction rows INCLUDING voided ones, with a `status` column (and void_reason for admins). If admin, include total_cost, gross_profit, alloc_restock, alloc_operating, alloc_profit.
   - 'finance': all filtered manual ledger rows.
```

### Prompt 8.3: End-of-Day Cash Closing

```markdown
Write `cash_close.php` ("Tutup Kasir"), following the Project Conventions. All text in Indonesian.

1. Shows, for the chosen date (cashiers: today only; admins: any date): cash sales (sum of total where method='cash', excluding voids), QRIS sales (information only), number of voided orders, and number of orders still in 'processing' (show a warning, not an error).
2. Form fields (integers >= 0): Modal Awal Laci (opening_cash), Uang Keluar dari Laci (cash_out, with help text: "Jika ini pengeluaran usaha, catat juga di menu Keuangan"), Uang Terhitung (counted_cash), optional note.
3. Live calculation: expected_cash = opening_cash + cash_sales - cash_out; difference = counted_cash - expected_cash. Show difference in green when 0, amber when positive (lebih), red when negative (kurang).
4. Save into `cash_closings` (one row per date, UNIQUE closing_date). After saving, a cashier sees it read-only; an admin can edit (overwrite) it.
5. Below the form: the list of previous closings (admins: last 30; cashiers: today only) with date, expected, counted, difference, and who closed.
6. cash_out is NOT written to the `finance` table and does not affect any report figure; closing is a drawer check only.
```

### Verification & Test Action

- In `finance.php`, record an expense of Rp250.000 under "Belanja bahan" tagged to the Belanja Bahan (restock) pool. Edit it, then delete a second test record to prove both work.
- Check `reports.php`; confirm that Restock Pool expenses increased by Rp250.000 and Net Cash Balance decreased by Rp250.000. Add a Rp100.000 income to the Laba pool and confirm that pool's balance rises and the reconciliation badge still reads "Saldo sesuai".
- Trigger CSV export for Summary, History, and Ledger.
- Open the exported `.csv` files in Microsoft Excel and LibreOffice Calc: verify characters display cleanly without encoding errors and numbers sit in separate columns.
- In `cash_close.php`, enter a deliberately wrong counted amount; confirm the difference shows in red and the saved row matches. Log in as cashier and confirm the saved closing is read-only.

---

## Day 9: Comprehensive QA Testing & Resiliency Verification

### Context to Give the AI

- Section 4.4 Verification of Revision D Test Cases (TC-D01 to TC-D24)
- The function names `createModifierGroup()` and `createModifierItem()` from `includes/menu_functions.php`

### Prompt 9.1: Automated QA Test Script

```markdown
Write a standalone test verification script `test_suite.php` (CLI or web executable by admin only) that runs automated test assertions against the database for Revision D test cases.

The script must run and output PASS/FAIL assertions for:
1. TC-D01 & TC-D04: Test that an exported CSV buffer contains the 3-byte BOM (\xEF\xBB\xBF) and prefixes a test string "=1+1" with a single quote.
2. TC-D09: Insert a test menu item; assert that attempting to add a 6th modifier group through createModifierGroup() (includes/menu_functions.php) throws an exception.
3. TC-D14: Verify frozen snapshot persistence: update a modifier's extra_price in `menu_modifier_items`; query existing records in `order_item_modifiers` and assert that the frozen price has NOT changed.
4. TC-D15: Assert that executing checkout with an already consumed token returns an error and creates 0 new rows in `transactions`.
5. TC-D17: Run the exact arithmetic assertion: for total=61000, total_cost=35500, operating_percent=50:
   - assert gross_profit === 25500
   - assert alloc_restock === 35500
   - assert alloc_operating === 12750
   - assert alloc_profit === 12750
   - assert (alloc_restock + alloc_operating + alloc_profit) === 61000
6. Output a clean HTML or terminal summary showing test name, expected, actual, and green PASS / red FAIL status badges.
```

### Verification & Test Action

- Run `test_suite.php` and verify that all assertions return **PASS**.
- Open the browser developer tools in mobile view (360px width) across POS, Queue, History, Cash Closing, Reports, and Dashboard to ensure zero horizontal scrollbars (NF06).
- Walk through the new flows once end to end as the real staff would: add a menu item with options, sell it (cash and QRIS), print the receipt, mark it done, void a mistaken order, record an expense, close the cash drawer, export a CSV.

---

## Day 10: Live Cutover

### Context to Give the AI

- Appendix D (Initial system seed data)
- Appendix E (Data to be prepared by management)

### Prompt 10.1: Database Sanitize & Production Seeding Script

```markdown
Write `sql/production_cutover.sql` to prepare the database for live commercial operation (MySQL 8.0/MariaDB, run in phpMyAdmin).

Requirements:
1. Delete all test data in foreign-key-safe order using DELETE (not TRUNCATE): `order_item_modifiers`, `orders`, `transactions`, `cash_closings`, `finance`, `login_attempts`. Do not rely on SET FOREIGN_KEY_CHECKS.
2. Retain the structural configuration tables: `users`, `menu`, `menu_modifier_groups`, `menu_modifier_items`, and `settings`.
3. After the deletes, reset AUTO_INCREMENT to 1 on every table that was emptied (ALTER TABLE ... AUTO_INCREMENT = 1).
4. Ensure the single row in `settings` (`settings_id = 1`) contains the verified Kedai Paradise identity:
   - business_name = 'Kedai Paradise'
   - business_address = 'RT 01 RW 02, Kandang Gampang, Purbalingga'
   - table_count = (verified count, placeholder to edit)
   - operating_percent = 50.00
   - receipt_footer = 'Terima kasih telah berkunjung!'
5. End with SELECT COUNT(*) checks for every emptied table (all must be 0) and a SELECT of the settings row.
```

### Pre-Cutover Checklist (Human Actions)

1. **Production mode:** in `private/config.local.php` set `env` to `'production'`. In Hostinger hPanel, PHP Options, switch **display_errors OFF**. Trigger a deliberate harmless error (for example, open a page with a wrong parameter) and confirm no PHP error text or file paths appear in the browser. From now on, read errors in `private/logs/php-error.log`.
2. **Accounts:** change the password of `fajar` (the default `Admin#12345` must be gone); try logging in with the old password and confirm it fails. Set test accounts such as `dewi_test` to Nonaktif; create the real accounts for Ibu Fitri and Bapak Fajar.
3. **Remove test files:** delete `print_test.html` and any test uploads from `/uploads/`.
4. **Re-verify protections:** `/config/`, `/includes/`, `/sql/` are denied; a `.php` file in `/uploads/` does not execute.
5. **Real data:** enter the real menu, prices, costs, and modifier options in `menu_manage.php`; verify table count, QRIS image, and operating percent in `settings.php`.
6. **Hardware setup at the counter:** place the counter tablet/PC at the Kedai Paradise counter. Bookmark `https://yourdomain.com/pos.php`. Pair the 58mm/80mm USB or Bluetooth thermal printer using the settings you recorded on Day 1 and print one real receipt from a test order to confirm.
7. **Staff orientation:**
   - Ibu Fitri: adding an item, selecting modifier options, entering cash tendered, viewing change, QRIS payment, printing a receipt, marking an order done, and closing the cash drawer (Tutup Kasir).
   - Bapak Fajar: managing the menu, voiding a mistaken order, adding an expense in Keuangan, viewing Laporan and Dasbor, and clicking "Ekspor CSV".
8. **Backup, then cutover:** export a full database backup from phpMyAdmin, then execute `sql/production_cutover.sql` to clear test orders. Place one real order and confirm its order number is 1 and the data looks right. Begin recording 100% of live customer transactions.

---

# Part 2: Beginner's Execution Playbook

The plan in Part 1 gives you the architecture, the exact database schema, and complete AI prompts to generate the code. It assumes background knowledge of the actual mechanics: where to click, how to set up files, how to point domain nameservers, and how to debug server errors when code doesn't work on the first try. This playbook is the hands-on "how-to" that sits between those prompts and your live website.

## Step 1: Connect Your Porkbun Domain to Hostinger

1. **Get Hostinger's Nameservers:**
   - Log in to your Hostinger dashboard (hPanel).
   - Go to **Websites**, then click **Manage** on your hosting plan.
   - On the dashboard sidebar, find **Nameservers** (they usually look like `ns1.dns-parking.com` and `ns2.dns-parking.com`). Note them down.
2. **Point Porkbun to Hostinger:**
   - Log in to **Porkbun.com** and open your **Domain Management** dashboard.
   - Find your domain and click **Details**, then click **Edit** next to **Authoritative Nameservers**.
   - Delete the default Porkbun nameservers and paste Hostinger's two nameservers.
   - Click **Submit**.
3. **Wait for DNS Propagation:**
   - DNS changes take between 15 minutes and a few hours.
   - In Hostinger hPanel, go to **Websites**, click **Add Website**, then select your domain.
   - Under **Security**, then **SSL**, ensure the status says **Active** (Hostinger installs this automatically for free).

---

## Step 2: Create and Import the Database in Hostinger phpMyAdmin

1. **Create the Database in Hostinger:**
   - In hPanel, go to **Databases**, then **Management**.
   - Under **Create a New MySQL Database and Database User**:
     - Database Name: e.g., `u123456789_kedai`
     - Username: e.g., `u123456789_admin`
     - Password: create a strong password (copy this somewhere safe!).
   - Click **Create**.
2. **Open phpMyAdmin:** on the same page, scroll to your newly created database and click the **phpMyAdmin** button next to it.
3. **Import the Schema First:**
   - At the top navigation bar of phpMyAdmin, click the **Import** tab.
   - Click **Choose File**, then select `schema (1).sql` from your computer.
   - Scroll down and click **Go** (bottom right).
   - Ensure green success checks appear and all 9 tables show up on the left menu.
4. **Import the Demo Data Second:** click the **Import** tab again, choose `demo_data.sql`, and click **Go**.
5. **Run the Extension Migration Third (after Prompt 1.4 on Day 1):**
   - First click the **Export** tab, then **Quick Export**, then **Go** to download a backup.
   - Then use **Import** with `sql/migration_001_extensions.sql` generated by the AI.
   - Confirm the tables `cash_closings` and `login_attempts` now exist and `transactions` shows the new columns.

---

## Step 3: Set Up Files in Hostinger

You do not need to install complex local development tools right away. You can work directly in Hostinger:

1. **Open File Manager:**
   - In hPanel, go to **Files**, then **File Manager** (Files of your domain).
   - Find the **`public_html`** folder. (Everything inside `public_html` is accessible on the internet.) Note the folder that contains it: it usually looks like `/home/u123456789/domains/yourdomain.com/` and is **not** reachable from the internet.
2. **Create the Private Folder (outside `public_html`):**
   - In the folder that contains `public_html` (one level up), create a folder named `private`, and inside it a folder named `logs`.
   - Inside `private/`, click **New File**, name it `config.local.php`, and paste the template produced by Prompt 1.2. Replace the placeholders with the real database host (usually `localhost`; confirm in hPanel), database name, username, and password from Step 2. Set `env` to `'development'` for now.
   - This file holds your passwords, so it must never be placed inside `public_html`.
3. **Create the Folder Structure inside `public_html`:**
   - `config`
   - `includes`
   - `lang`
   - `js`
   - `sql`
   - `uploads`, and inside it `qris` and `menu`
4. **Create the PHP Files as You Go:** `config/database.php` and `config/session.php` come from Prompt 1.2; `includes/*`, `lang/id.php` and `js/common.js` come from Prompt 1.3. `config/database.php` finds your credentials by itself through `dirname(__DIR__, 2) . '/private/config.local.php'`, so if you get a "file not found" error, re-check that `private` sits in the same folder as `public_html`.

---

## Step 4: The AI Prompting & Coding Workflow

To execute Days 1 through 9 without breaking your site, follow this repetitive workflow:

1. **Start a Fresh AI Session:**
   - Open Claude, ChatGPT, or Gemini.
   - Paste the **Master Context Block** and then the **Project Conventions Block** from Part 1.
   - From Day 2 onward, also paste the **function-signature list** that Prompt 1.3 produced (helpers.php and common.js), so the AI reuses your shared functions instead of inventing new ones.
2. **Send One Specific Prompt at a Time:**
   - Take a single prompt (e.g., **Prompt 2.1** for `login.php`).
   - When the AI provides code, inspect it to see which filename it belongs to.
3. **Place the Code into Hostinger:**
   - In Hostinger File Manager, navigate to the required directory (e.g., `public_html/` or `public_html/js/`).
   - Click **New File**, name it (e.g., `login.php`).
   - Open the file, paste the AI-generated code, and click **Save & Close** (disk icon).
4. **Test Immediately in Your Browser:**
   - Open your browser and go to `https://yourdomain.com/login.php`.
   - Try entering the credentials (`fajar` and `Admin#12345`).
   - If it works, move to the next prompt.
   - **If it fails or shows a blank page / error:** Do not guess. Copy the exact error message or screenshot, paste it back into your AI session, and say: *"I pasted this code into login.php on Hostinger, but got this error: [paste error]. How do I fix it?"*

---

## Step 5: Common Beginner Pitfalls & How to Avoid Them

- **Error 500 (Internal Server Error):**
  - *Cause:* A typo or missing semicolon in PHP, or a malformed `.htaccess` file.
  - *Fix:* During development, keep `env` set to `'development'` in `private/config.local.php` (this turns PHP error display on). If you still only see a generic 500, in hPanel search for **PHP Configuration**, click **PHP Options**, and turn **display_errors** ON so PHP shows you the exact line number. If a new `.htaccess` is the suspect, temporarily rename it to `.htaccess_off` to confirm.
- **Remember to turn errors OFF at launch:**
  - On Day 10, set `env` to `'production'` and switch **display_errors OFF** in hPanel. Visitors must never see PHP error text or file paths. After that, read problems in `private/logs/php-error.log` through File Manager.
- **"Access denied for user" or Database Connection Fails:**
  - *Cause:* Hostinger prepends a prefix to database names (e.g., your database might be named `u123456789_kedai`, not just `kedai`).
  - *Fix:* Double-check the exact database name and username in hPanel under **MySQL Databases** and update `private/config.local.php` accordingly.
- **Images not showing up or uploads failing:**
  - *Cause:* Linux folder permissions.
  - *Fix:* In File Manager, right-click on the `uploads` folder (and `uploads/qris`, `uploads/menu`), click **Permissions**, and ensure it is set to `755` (Read and Execute for all, Write for Owner).
- **Receipt prints cut off or blank paper feeds:**
  - *Cause:* Browser print settings or paper size.
  - *Fix:* Re-open `print_test.html` (until Day 10) and repeat the Day 1 hardware gate: paper size Roll/receipt, margins None, headers and footers off, scale 100%.
- **Accidentally overwriting database changes:**
  - Whenever you want to test a major change, go to phpMyAdmin, click the **Export** tab, click **Quick Export**, then **Go** to download a local backup file (`.sql`). If anything breaks, you can re-import that backup in 30 seconds.
