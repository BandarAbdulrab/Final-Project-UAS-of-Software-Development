-- ============================================================
-- KEDAI PARADISE POS - PRODUCTION LIVE DATABASE (ACTUAL USE)
-- Database target: kedai_paradise
-- Users: fajar (admin), fitri (cashier) | Initial Password: Admin#12345
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

-- 1. Settings Identity (Kedai Paradise Kandang Gampang Purbalingga)
INSERT INTO `settings` (`settings_id`, `business_name`, `business_address`, `table_count`, `receipt_footer`, `operating_percent`, `qris_bank`, `qris_owner_name`, `qris_image`)
VALUES (1, 'Kedai Paradise', 'Kost Paradise, RT 01 RW 02, Kandang Gampang, Purbalingga (DO: 0857-0102-3362)', 10, 'Terima kasih telah berkunjung! Harga Kaki Lima, Rasa Bintang Lima.', 50.00, 'BCA', 'Kedai Paradise / Bapak Fajar', NULL);

-- 2. Two Actual Users: fajar (Admin) & fitri (Cashier) | Password: Admin#12345
INSERT INTO `users` (`user_id`, `username`, `password_hash`, `name`, `role`, `status`) VALUES
  (1, 'fajar', '$2y$10$YQBKzOC78bPmAiqFgyVQuOUPnaUxXvrBoqV.fD5i1LqGe5HLENqPu', 'Bapak Fajar', 'admin',   1),
  (2, 'fitri', '$2y$10$YQBKzOC78bPmAiqFgyVQuOUPnaUxXvrBoqV.fD5i1LqGe5HLENqPu', 'Ibu Fitri',   'cashier', 1);

-- 3. Actual Kedai Paradise Menu (from official menu poster)
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
  (15, 'Air Es',                     'Minuman',  2000,  500, NULL, 1, 1);

-- 4. Modifier Groups
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

-- 5. Modifier Items
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