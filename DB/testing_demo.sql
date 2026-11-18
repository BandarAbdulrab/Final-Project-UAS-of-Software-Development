-- ============================================================
-- KEDAI PARADISE POS - TESTING & DEMO DATABASE
-- Database target: kedai_paradise_test
-- Users: admin_test (admin), kasir_test (cashier) | Password: Admin#12345
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `cash_closings`;
DROP TABLE IF EXISTS `order_item_modifiers`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `finance`;
DROP TABLE IF EXISTS `menu_modifier_items`;
DROP TABLE IF EXISTS `menu_modifier_groups`;
DROP TABLE IF EXISTS `menu`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `user_id`       INT             NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)     NOT NULL,
  `password_hash` VARCHAR(255)    NOT NULL,
  `name`          VARCHAR(100)    NOT NULL,
  `role`          ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
  `status`        TINYINT(1)      NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu` (
  `menu_id`    INT           NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)  NOT NULL,
  `category`   VARCHAR(30)   NOT NULL DEFAULT 'Makanan',
  `price`      INT           NOT NULL DEFAULT 0,
  `cost_price` INT           NOT NULL DEFAULT 0,
  `photo`      VARCHAR(100)  NULL,
  `available`  TINYINT(1)    NOT NULL DEFAULT 1,
  `status`     TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`menu_id`),
  KEY `idx_menu_category` (`category`),
  CONSTRAINT `chk_menu_price` CHECK (`price` >= 0),
  CONSTRAINT `chk_menu_cost`  CHECK (`cost_price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_modifier_groups` (
  `group_id`    INT          NOT NULL AUTO_INCREMENT,
  `menu_id`     INT          NOT NULL,
  `name`        VARCHAR(50)  NOT NULL,
  `type`        ENUM('single','multiple') NOT NULL DEFAULT 'single',
  `is_required` TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`group_id`),
  KEY `idx_mmg_menu` (`menu_id`),
  CONSTRAINT `fk_mmg_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_modifier_items` (
  `modifier_id` INT          NOT NULL AUTO_INCREMENT,
  `group_id`    INT          NOT NULL,
  `name`        VARCHAR(50)  NOT NULL,
  `extra_price` INT          NOT NULL DEFAULT 0,
  `extra_cost`  INT          NOT NULL DEFAULT 0,
  `available`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`modifier_id`),
  KEY `idx_mmi_group` (`group_id`),
  CONSTRAINT `fk_mmi_group` FOREIGN KEY (`group_id`) REFERENCES `menu_modifier_groups` (`group_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_mmi_price` CHECK (`extra_price` >= 0),
  CONSTRAINT `chk_mmi_cost`  CHECK (`extra_cost` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transactions` (
  `transaction_id`   INT          NOT NULL AUTO_INCREMENT,
  `order_no`         INT          NOT NULL,
  `date`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `table_no`         VARCHAR(10)  NOT NULL,
  `method`           ENUM('cash','qris') NOT NULL,
  `total`            INT          NOT NULL,
  `paid`             INT          NOT NULL,
  `change_due`       INT          NOT NULL DEFAULT 0,
  `status`           ENUM('processing','completed','void') NOT NULL DEFAULT 'processing',
  `completed_at`     DATETIME     NULL,
  `duration_seconds` INT          NULL,
  `total_cost`       INT          NOT NULL DEFAULT 0,
  `gross_profit`     INT          NOT NULL DEFAULT 0,
  `alloc_restock`    INT          NOT NULL DEFAULT 0,
  `alloc_operating`  INT          NOT NULL DEFAULT 0,
  `alloc_profit`     INT          NOT NULL DEFAULT 0,
  `user_id`          INT          NOT NULL,
  `order_date`       DATE GENERATED ALWAYS AS (DATE(`date`)) STORED,
  `void_reason`      VARCHAR(255) NULL,
  `voided_at`        DATETIME     NULL,
  `voided_by`        INT          NULL,
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `uq_order_no_per_day` (`order_date`, `order_no`),
  KEY `idx_transactions_date` (`date`),
  KEY `idx_transactions_order_date` (`order_date`),
  KEY `idx_transactions_user` (`user_id`),
  KEY `idx_transactions_voided_by` (`voided_by`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_transactions_voided_by` FOREIGN KEY (`voided_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_trx_total` CHECK (`total` >= 0),
  CONSTRAINT `chk_trx_paid`  CHECK (`paid` >= `total`),
  CONSTRAINT `chk_trx_void`  CHECK (`status` <> 'void' OR (`void_reason` IS NOT NULL AND CHAR_LENGTH(`void_reason`) >= 3 AND `voided_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `order_id`       INT NOT NULL AUTO_INCREMENT,
  `transaction_id` INT NOT NULL,
  `menu_id`        INT NOT NULL,
  `quantity`       INT NOT NULL,
  `price`          INT NOT NULL,
  `cost_price`     INT NOT NULL,
  `modifier_price` INT NOT NULL DEFAULT 0,
  `modifier_cost`  INT NOT NULL DEFAULT 0,
  `subtotal`       INT NOT NULL,
  PRIMARY KEY (`order_id`),
  KEY `idx_orders_transaction` (`transaction_id`),
  KEY `idx_orders_menu` (`menu_id`),
  CONSTRAINT `fk_orders_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_orders_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_item_modifiers` (
  `order_modifier_id` INT         NOT NULL AUTO_INCREMENT,
  `order_id`          INT         NOT NULL,
  `modifier_name`     VARCHAR(50) NOT NULL,
  `price`             INT         NOT NULL DEFAULT 0,
  `cost_price`        INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (`order_modifier_id`),
  KEY `idx_oim_order` (`order_id`),
  CONSTRAINT `fk_oim_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_oim_price` CHECK (`price` >= 0),
  CONSTRAINT `chk_oim_cost`  CHECK (`cost_price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `finance` (
  `finance_id`  INT          NOT NULL AUTO_INCREMENT,
  `date`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type`        ENUM('income','expense') NOT NULL,
  `category`    VARCHAR(100) NOT NULL,
  `pool`        ENUM('restock','operating','profit') NOT NULL,
  `amount`      INT          NOT NULL,
  `description` VARCHAR(200) NULL,
  `user_id`     INT          NOT NULL,
  PRIMARY KEY (`finance_id`),
  KEY `idx_finance_date` (`date`),
  KEY `idx_finance_user` (`user_id`),
  CONSTRAINT `fk_finance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_finance_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cash_closings` (
  `closing_id`    INT           NOT NULL AUTO_INCREMENT,
  `closing_date`  DATE          NOT NULL,
  `user_id`       INT           NOT NULL,
  `opening_cash`  INT           NOT NULL DEFAULT 0,
  `cash_sales`    INT           NOT NULL DEFAULT 0,
  `cash_out`      INT           NOT NULL DEFAULT 0,
  `expected_cash` INT           NOT NULL DEFAULT 0,
  `counted_cash`  INT           NOT NULL DEFAULT 0,
  `difference`    INT           NOT NULL DEFAULT 0,
  `note`          VARCHAR(255)  NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`closing_id`),
  UNIQUE KEY `uk_cash_closings_date` (`closing_date`),
  KEY `idx_cash_closings_user` (`user_id`),
  CONSTRAINT `fk_cash_closings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_cc_opening` CHECK (`opening_cash` >= 0),
  CONSTRAINT `chk_cc_cash_out` CHECK (`cash_out` >= 0),
  CONSTRAINT `chk_cc_counted` CHECK (`counted_cash` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
  `attempt_id`   INT          NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(50)  NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attempt_id`),
  KEY `idx_login_user` (`username`, `attempted_at`),
  KEY `idx_login_ip` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
  `settings_id`       TINYINT       NOT NULL DEFAULT 1,
  `business_name`     VARCHAR(100)  NOT NULL,
  `business_address`  VARCHAR(200)  NULL,
  `table_count`       INT           NOT NULL DEFAULT 1,
  `receipt_footer`    VARCHAR(200)  NULL,
  `operating_percent` DECIMAL(5,2)  NOT NULL DEFAULT 50.00,
  `qris_bank`         VARCHAR(50)   NULL,
  `qris_owner_name`   VARCHAR(100)  NULL,
  `qris_image`        VARCHAR(100)  NULL,
  PRIMARY KEY (`settings_id`),
  CONSTRAINT `chk_settings_single` CHECK (`settings_id` = 1),
  CONSTRAINT `chk_settings_tables` CHECK (`table_count` >= 1),
  CONSTRAINT `chk_settings_pct`    CHECK (`operating_percent` BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Settings
INSERT INTO `settings` (`settings_id`, `business_name`, `business_address`, `table_count`, `receipt_footer`, `operating_percent`, `qris_bank`, `qris_owner_name`, `qris_image`)
VALUES (1, 'Kedai Paradise', 'Kost Paradise, RT 01 RW 02, Kandang Gampang, Purbalingga (DO: 0857-0102-3362)', 12, 'Terima kasih telah berkunjung! Harga Kaki Lima, Rasa Bintang Lima.', 50.00, 'BCA mobile', 'Kedai Paradise / Fajar', NULL);

-- 2. Two Testing Users: admin_test (Admin) & kasir_test (Cashier) | Password: Admin#12345
INSERT INTO `users` (`user_id`, `username`, `password_hash`, `name`, `role`, `status`) VALUES
  (1, 'admin_test', '$2y$10$YQBKzOC78bPmAiqFgyVQuOUPnaUxXvrBoqV.fD5i1LqGe5HLENqPu', 'Ibu Fitri (Admin Demo)',  'admin',   1),
  (2, 'kasir_test', '$2y$10$YQBKzOC78bPmAiqFgyVQuOUPnaUxXvrBoqV.fD5i1LqGe5HLENqPu', 'Dewi Lestari (Kasir Demo)', 'cashier', 1);

-- 3. Menu (Same 15 items; Air Es set to available=0 to showcase Sold-Out alert on Dashboard, Mie Nampol & Wonton Nonaktif)
INSERT INTO `menu` (`menu_id`, `name`, `category`, `price`, `cost_price`, `photo`, `available`, `status`) VALUES
  (1,  'Ayam (Goreng/Bakar/Penyet)', 'Makanan',  8000, 5000, NULL, 1, 1),
  (2,  'Ayam + Nasi',                'Makanan', 10000, 6500, NULL, 1, 1),
  (3,  'Mie Nampol',                 'Makanan',  8000, 4500, NULL, 0, 0),
  (4,  'Wonton Rebus/Goreng',        'Makanan',  8000, 4500, NULL, 0, 0),
  (5,  'Tahu / Tempe',               'Makanan',  1500,  800, NULL, 1, 1),
  (6,  'Nasi Tahu/Tempe',            'Makanan',  7000, 4000, NULL, 1, 1),
  (7,  'Nasi Telur + Tahu/Tempe',    'Makanan', 10000, 6000, NULL, 1, 1),
  (8,  'Nasi Goreng',                'Makanan', 10000, 5500, NULL, 1, 1),
  (9,  'Nasi Putih',                 'Makanan',  4000, 2000, NULL, 1, 1),
  (10, 'Paket Hemat',                'Makanan', 15000, 9000, NULL, 1, 1),
  (11, 'Es Teh Tawar/Manis/Hangat',  'Minuman',  3500, 1200, NULL, 1, 1),
  (12, 'Nutrisari',                  'Minuman',  5000, 2500, NULL, 1, 1),
  (13, 'Es Good Day',                'Minuman',  5000, 2500, NULL, 1, 1),
  (14, 'Es Susu',                    'Minuman',  5000, 2500, NULL, 1, 1),
  (15, 'Air Es',                     'Minuman',  2000,  500, NULL, 0, 1);

-- 4. Modifier Groups & Items
INSERT INTO `menu_modifier_groups` (`group_id`, `menu_id`, `name`, `type`, `is_required`) VALUES
  (1,  1,  'Pilihan Olahan Ayam', 'single',   1),
  (2,  1,  'Tingkat Pedas',       'single',   0),
  (3,  2,  'Pilihan Olahan Ayam', 'single',   1),
  (4,  2,  'Tambahan Lauk',       'multiple', 0),
  (5,  3,  'Level Pedas',         'single',   1),
  (6,  4,  'Varian Penyajian',    'single',   1),
  (7,  5,  'Pilihan Lauk',        'single',   1),
  (8,  6,  'Kombinasi Tahu/Tempe','single',   1),
  (9,  7,  'Olahan Telur',        'single',   1),
  (10, 8,  'Tingkat Pedas',       'single',   1),
  (11, 8,  'Topping Tambahan',    'multiple', 0),
  (12, 10, 'Pilihan Ayam Paket',  'single',   1),
  (13, 11, 'Varian Penyajian Teh','single',   1),
  (14, 12, 'Varian Rasa',         'single',   0),
  (15, 13, 'Suhu Minuman',        'single',   1),
  (16, 14, 'Varian Susu',         'single',   1);

INSERT INTO `menu_modifier_items` (`group_id`, `name`, `extra_price`, `extra_cost`, `available`) VALUES
  (1,  'Ayam Goreng',       0,    0, 1),
  (1,  'Ayam Bakar',        0,  500, 1),
  (1,  'Ayam Penyet',       0,  500, 1),
  (2,  'Tidak Pedas/Pisah', 0,    0, 1),
  (2,  'Sedang',            0,    0, 1),
  (2,  'Pedas Nampol',      0,    0, 1),
  (3,  'Ayam Goreng',       0,    0, 1),
  (3,  'Ayam Bakar',        0,  500, 1),
  (3,  'Ayam Penyet',       0,  500, 1),
  (4,  'Tambah Tahu',    1500,  800, 1),
  (4,  'Tambah Tempe',   1500,  800, 1),
  (4,  'Tambah Telur',   3000, 2000, 1),
  (5,  'Level 1',           0,    0, 1),
  (5,  'Level 2',           0,    0, 1),
  (5,  'Level 3',           0,    0, 1),
  (6,  'Wonton Rebus',      0,    0, 1),
  (6,  'Wonton Goreng',     0,    0, 1),
  (7,  'Tahu Goreng',       0,    0, 1),
  (7,  'Tempe Goreng',      0,    0, 1),
  (8,  'Campur Tahu Tempe', 0,    0, 1),
  (8,  'Full Tahu',         0,    0, 1),
  (8,  'Full Tempe',        0,    0, 1),
  (9,  'Telur Dadar',       0,    0, 1),
  (9,  'Telur Ceplok',      0,    0, 1),
  (9,  'Telur Penyet',      0,    0, 1),
  (10, 'Tidak Pedas',       0,    0, 1),
  (10, 'Sedang',            0,    0, 1),
  (10, 'Pedas',             0,    0, 1),
  (11, 'Telur Tambahan', 3000, 2000, 1),
  (11, 'Tahu/Tempe',     1500,  800, 1),
  (12, 'Paket Ayam Goreng', 0,    0, 1),
  (12, 'Paket Ayam Bakar',  0,  500, 1),
  (12, 'Paket Ayam Penyet', 0,  500, 1),
  (13, 'Es Teh Manis',      0,    0, 1),
  (13, 'Es Teh Tawar',      0,    0, 1),
  (13, 'Teh Manis Hangat',  0,    0, 1),
  (13, 'Teh Tawar Hangat',  0,    0, 1),
  (14, 'Sweet Orange',      0,    0, 1),
  (14, 'Jeruk Peras',       0,    0, 1),
  (14, 'Mangga',            0,    0, 1),
  (15, 'Dingin (Es)',       0,    0, 1),
  (15, 'Hangat',            0,    0, 1),
  (16, 'Es Susu Putih',     0,    0, 1),
  (16, 'Es Susu Cokelat',   0,    0, 1),
  (16, 'Susu Hangat',       0,    0, 1);

-- 5. Demo Transactions (Past 6 Days + Today's Completed, Processing Queue & Voided Orders)
INSERT INTO `transactions`
  (`transaction_id`, `order_no`, `date`, `table_no`, `method`, `total`, `paid`, `change_due`, `status`, `completed_at`, `duration_seconds`, `total_cost`, `gross_profit`, `alloc_restock`, `alloc_operating`, `alloc_profit`, `user_id`, `void_reason`, `voided_at`, `voided_by`)
VALUES
  -- Past 6 days for 7-day trend chart
  (1,  1, CONCAT(CURDATE() - INTERVAL 6 DAY, ' 12:15:00'), '02', 'cash', 75000,  80000,  5000, 'completed', CONCAT(CURDATE() - INTERVAL 6 DAY, ' 12:22:00'), 420, 45000, 30000, 45000, 15000, 15000, 2, NULL, NULL, NULL),
  (2,  1, CONCAT(CURDATE() - INTERVAL 5 DAY, ' 13:10:00'), '04', 'qris', 90000,  90000,     0, 'completed', CONCAT(CURDATE() - INTERVAL 5 DAY, ' 13:18:00'), 480, 54000, 36000, 54000, 18000, 18000, 2, NULL, NULL, NULL),
  (3,  1, CONCAT(CURDATE() - INTERVAL 4 DAY, ' 11:30:00'), '01', 'cash', 65000,  70000,  5000, 'completed', CONCAT(CURDATE() - INTERVAL 4 DAY, ' 11:36:30'), 390, 39000, 26000, 39000, 13000, 13000, 1, NULL, NULL, NULL),
  (4,  1, CONCAT(CURDATE() - INTERVAL 3 DAY, ' 18:20:00'), 'TA', 'cash', 110000, 110000,    0, 'completed', CONCAT(CURDATE() - INTERVAL 3 DAY, ' 18:28:00'), 480, 66000, 44000, 66000, 22000, 22000, 2, NULL, NULL, NULL),
  (5,  1, CONCAT(CURDATE() - INTERVAL 2 DAY, ' 19:00:00'), '03', 'qris', 85000,  85000,     0, 'completed', CONCAT(CURDATE() - INTERVAL 2 DAY, ' 19:07:00'), 420, 51000, 34000, 51000, 17000, 17000, 2, NULL, NULL, NULL),
  (6,  1, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 12:40:00'), '05', 'cash', 120000, 150000, 30000,'completed', CONCAT(CURDATE() - INTERVAL 1 DAY, ' 12:47:10'), 430, 72000, 48000, 72000, 24000, 24000, 1, NULL, NULL, NULL),
  -- Today's Completed Orders (#1, #2)
  (7,  1, DATE_SUB(NOW(), INTERVAL 90 MINUTE),             '03', 'cash', 37000,  50000, 13000, 'completed', DATE_SUB(NOW(), INTERVAL 83 MINUTE),             422, 21400, 15600, 21400,  7800,  7800, 1, NULL, NULL, NULL),
  (8,  2, DATE_SUB(NOW(), INTERVAL 65 MINUTE),             'TA', 'qris', 30000,  30000,     0, 'completed', DATE_SUB(NOW(), INTERVAL 59 MINUTE),             370, 18000, 12000, 18000,  6000,  6000, 2, NULL, NULL, NULL),
  -- Today's Voided Order (#3)
  (9,  3, DATE_SUB(NOW(), INTERVAL 45 MINUTE),             'TA', 'cash', 23000,  25000,  2000, 'void',      NULL,                                            NULL,14500,  8500, 14500,  4250,  4250, 2, 'Pelanggan salah pilih varian lauk; pesanan dibuat ulang', DATE_SUB(NOW(), INTERVAL 40 MINUTE), 1),
  -- Today's Active Kitchen Queue Orders (#4, #5, #6)
  (10, 4, DATE_SUB(NOW(), INTERVAL 14 MINUTE),             '05', 'cash', 40000,  50000, 10000, 'processing',NULL,                                            NULL,24400, 15600, 24400,  7800,  7800, 1, NULL, NULL, NULL),
  (11, 5, DATE_SUB(NOW(), INTERVAL 8 MINUTE),              'TA', 'qris', 18000,  18000,     0, 'processing',NULL,                                            NULL,10000,  8000, 10000,  4000,  4000, 2, NULL, NULL, NULL),
  (12, 6, DATE_SUB(NOW(), INTERVAL 3 MINUTE),              '02', 'cash', 25000,  30000,  5000, 'processing',NULL,                                            NULL,15500,  9500, 15500,  4750,  4750, 2, NULL, NULL, NULL);

-- 6. Demo Order Line Items
INSERT INTO `orders` (`order_id`, `transaction_id`, `menu_id`, `quantity`, `price`, `cost_price`, `modifier_price`, `modifier_cost`, `subtotal`) VALUES
  (1,  1,  10, 5, 15000, 9000,    0,    0,  75000),
  (2,  2,  10, 6, 15000, 9000,    0,    0,  90000),
  (3,  3,  2,  5, 10000, 6500,    0,    0,  50000),
  (4,  3,  13, 3,  5000, 2500,    0,    0,  15000),
  (5,  4,  10, 6, 15000, 9000,    0,    0,  90000),
  (6,  4,  8,  2, 10000, 5500,    0,    0,  20000),
  (7,  5,  2,  6, 10000, 6500,    0,    0,  60000),
  (8,  5,  14, 5,  5000, 2500,    0,    0,  25000),
  (9,  6,  10, 8, 15000, 9000,    0,    0, 120000),
  -- Today #1 (37,000)
  (10, 7,  10, 2, 15000, 9000,    0,    0,  30000),
  (11, 7,  11, 2,  3500, 1200,    0,    0,   7000),
  -- Today #2 (30,000)
  (12, 8,  2,  2, 10000, 6500,    0,    0,  20000),
  (13, 8,  12, 2,  5000, 2500,    0,    0,  10000),
  -- Today #3 Voided (23,000)
  (14, 9,  8,  2, 10000, 5500, 1500,  800,  23000),
  -- Today #4 Processing in Queue (40,000)
  (15, 10, 2,  2, 10000, 6500, 3000, 2000,  26000),
  (16, 10, 11, 2,  3500, 1200,    0,    0,   7000),
  (17, 10, 6,  1,  7000, 4000,    0,    0,   7000),
  -- Today #5 Processing in Queue (18,000)
  (18, 11, 8,  1, 10000, 5500, 3000, 2000,  13000),
  (19, 11, 13, 1,  5000, 2500,    0,    0,   5000),
  -- Today #6 Processing in Queue (25,000)
  (20, 12, 10, 1, 15000, 9000,    0,  500,  15000),
  (21, 12, 7,  1, 10000, 6000,    0,    0,  10000);

-- 7. Frozen Modifiers Snapshot
INSERT INTO `order_item_modifiers` (`order_id`, `modifier_name`, `price`, `cost_price`) VALUES
  (10, 'Paket Ayam Bakar',  0,    500),
  (11, 'Es Teh Manis',      0,      0),
  (12, 'Ayam Penyet',       0,    500),
  (13, 'Jeruk Peras',       0,      0),
  (14, 'Pedas',             0,      0),
  (14, 'Tahu/Tempe',     1500,    800),
  (15, 'Ayam Bakar',        0,    500),
  (15, 'Tambah Telur',   3000,   2000),
  (16, 'Es Teh Manis',      0,      0),
  (17, 'Campur Tahu Tempe', 0,      0),
  (18, 'Pedas',             0,      0),
  (18, 'Telur Tambahan', 3000,   2000),
  (19, 'Dingin (Es)',       0,      0),
  (20, 'Paket Ayam Penyet', 0,    500),
  (21, 'Telur Dadar',       0,      0);

-- 8. Demo Manual Finance Entries (Matching Figma design)
INSERT INTO `finance` (`finance_id`, `date`, `type`, `category`, `pool`, `amount`, `description`, `user_id`) VALUES
  (1, CONCAT(CURDATE(), ' 08:30:00'),                  'expense', 'Belanja bahan baku',     'restock',   150000, 'Ayam segar, beras, minyak goreng, tahu tempe', 1),
  (2, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 18:00:00'), 'expense', 'Token Listrik & Gas LPG','operating',  80000, 'Token PLN prabayar & isi ulang gas 3kg',        1),
  (3, CONCAT(CURDATE() - INTERVAL 1 DAY, ' 17:30:00'), 'income',  'Penjualan Minyak Bekas', 'profit',     50000, '1 jerigen minyak jelantah ke pengepul',         1);

-- 9. Demo Cash Closings History (Last 3 Days)
INSERT INTO `cash_closings` (`closing_id`, `closing_date`, `user_id`, `opening_cash`, `cash_sales`, `cash_out`, `expected_cash`, `counted_cash`, `difference`, `note`) VALUES
  (1, CURDATE() - INTERVAL 1 DAY, 1, 200000, 120000, 20000, 300000, 300000,     0, 'Kas sesuai (Pas)'),
  (2, CURDATE() - INTERVAL 2 DAY, 1, 200000,      0,     0, 200000, 205000,  5000, 'Lebih uang kembalian receh'),
  (3, CURDATE() - INTERVAL 3 DAY, 1, 200000, 110000, 15000, 295000, 290000, -5000, 'Selisih kembalian siang hari');