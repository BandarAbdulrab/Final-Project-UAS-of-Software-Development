-- ============================================================
-- Kedai Paradise POS & Finance System  (SRS-KP-2026-01 Rev. D.1)
-- MySQL 8.0 / MariaDB 10.x  |  InnoDB  |  utf8mb4  |  11 tables
-- Import via phpMyAdmin: Import tab -> choose this file -> Go
-- Rev. D.2 fixes: menu.category added; no CREATE DATABASE/USE; CHECK on void consistency; Indonesian sample menu.
-- WARNING: this script DROPS and recreates the tables (all data lost).
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';            -- WIB (Asia/Jakarta)
SET FOREIGN_KEY_CHECKS = 0;

-- NOTE: this script does NOT create or select a database. On shared hosting
-- (e.g. Hostinger) the database name is prefixed (u123456789_kedai): create it in the
-- control panel, click it in phpMyAdmin, THEN import this file.

-- Drop in reverse dependency order so the script can be re-run
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

-- ------------------------------------------------------------
-- D4  users
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `user_id`       INT             NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)     NOT NULL,
  `password_hash` VARCHAR(255)    NOT NULL COMMENT 'PHP password_hash() / bcrypt',
  `name`          VARCHAR(100)    NOT NULL,
  `role`          ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
  `status`        TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '1 = active',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- D1  menu
-- ------------------------------------------------------------
CREATE TABLE `menu` (
  `menu_id`    INT           NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)  NOT NULL,
  `category`   VARCHAR(30)   NOT NULL DEFAULT 'Lainnya' COMMENT 'free-text category used by the cashier filter pills (F03, F04)',
  `price`      INT           NOT NULL DEFAULT 0 COMMENT 'Rupiah, >= 0',
  `cost_price` INT           NOT NULL DEFAULT 0 COMMENT 'Rupiah, >= 0',
  `photo`      VARCHAR(100)  NULL     COMMENT 'file name in uploads/',
  `available`  TINYINT(1)    NOT NULL DEFAULT 1 COMMENT '1 = available, 0 = sold out',
  `status`     TINYINT(1)    NOT NULL DEFAULT 1 COMMENT '1 = active',
  PRIMARY KEY (`menu_id`),
  KEY `idx_menu_category` (`category`),
  CONSTRAINT `chk_menu_price` CHECK (`price` >= 0),
  CONSTRAINT `chk_menu_cost`  CHECK (`cost_price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- D7  menu_modifier_groups (Size, Spice Level, Toppings, ...)
--     max 5 groups per menu item (enforced by the application)
-- ------------------------------------------------------------
CREATE TABLE `menu_modifier_groups` (
  `group_id`    INT          NOT NULL AUTO_INCREMENT,
  `menu_id`     INT          NOT NULL,
  `name`        VARCHAR(50)  NOT NULL,
  `type`        ENUM('single','multiple') NOT NULL DEFAULT 'single'
                COMMENT 'single = single_choice, multiple = multi_choice',
  `is_required` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = mandatory',
  PRIMARY KEY (`group_id`),
  KEY `idx_mmg_menu` (`menu_id`),
  CONSTRAINT `fk_mmg_menu`
    FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- D8  menu_modifier_items (Large, Level 3, Extra Egg, ...)
--     max 10 items per group (enforced by the application)
-- ------------------------------------------------------------
CREATE TABLE `menu_modifier_items` (
  `modifier_id` INT          NOT NULL AUTO_INCREMENT,
  `group_id`    INT          NOT NULL,
  `name`        VARCHAR(50)  NOT NULL,
  `extra_price` INT          NOT NULL DEFAULT 0 COMMENT 'extra selling price per unit, >= 0',
  `extra_cost`  INT          NOT NULL DEFAULT 0 COMMENT 'extra cost price per unit, >= 0',
  `available`   TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = selectable',
  PRIMARY KEY (`modifier_id`),
  KEY `idx_mmi_group` (`group_id`),
  CONSTRAINT `fk_mmi_group`
    FOREIGN KEY (`group_id`) REFERENCES `menu_modifier_groups` (`group_id`)
    ON UPDATE CASCADE ON DELETE CASCADE,   -- modifier setup is not historical data
  CONSTRAINT `chk_mmi_price` CHECK (`extra_price` >= 0),
  CONSTRAINT `chk_mmi_cost`  CHECK (`extra_cost` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- D2  transactions
-- ------------------------------------------------------------
CREATE TABLE `transactions` (
  `transaction_id`   INT          NOT NULL AUTO_INCREMENT,
  `order_no`         INT          NOT NULL COMMENT 'sequential per day, resets 00:00 WIB',
  `date`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'order time',
  `table_no`         VARCHAR(10)  NOT NULL COMMENT 'table number or TA',
  `method`           ENUM('cash','qris') NOT NULL,
  `total`            INT          NOT NULL,
  `paid`             INT          NOT NULL,
  `change_due`       INT          NOT NULL DEFAULT 0,
  `status`           ENUM('processing','completed','void') NOT NULL DEFAULT 'processing'
                     COMMENT 'void = cancelled by an admin (F09)',
  `completed_at`     DATETIME     NULL,
  `duration_seconds` INT          NULL,
  `total_cost`       INT          NOT NULL DEFAULT 0 COMMENT 'sum((base cost + modifier costs) x qty)',
  `gross_profit`     INT          NOT NULL DEFAULT 0 COMMENT 'total - total_cost, can be negative',
  `alloc_restock`    INT          NOT NULL DEFAULT 0,
  `alloc_operating`  INT          NOT NULL DEFAULT 0,
  `alloc_profit`     INT          NOT NULL DEFAULT 0,
  `user_id`          INT          NOT NULL COMMENT 'cashier who created the transaction',
  `order_date`       DATE GENERATED ALWAYS AS (DATE(`date`)) STORED
                     COMMENT 'derived from date; used for the unique daily order number',
  `void_reason`      VARCHAR(255) NULL     COMMENT 'mandatory 3-255 chars when void',
  `voided_at`        DATETIME     NULL,
  `voided_by`        INT          NULL     COMMENT 'admin who voided the order',
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `uq_order_no_per_day` (`order_date`, `order_no`),
  KEY `idx_transactions_date` (`date`),
  KEY `idx_transactions_order_date` (`order_date`),
  KEY `idx_transactions_user` (`user_id`),
  KEY `idx_transactions_voided_by` (`voided_by`),
  CONSTRAINT `fk_transactions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_transactions_voided_by`
    FOREIGN KEY (`voided_by`) REFERENCES `users` (`user_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_trx_total` CHECK (`total` >= 0),
  CONSTRAINT `chk_trx_paid`  CHECK (`paid` >= `total`),
  -- a voided order must carry reason (3-255 chars) and time (voided_by is excluded: MySQL forbids
  -- CHECK on a column used in a FK with ON UPDATE CASCADE; the app/API enforces it and admin role)
  CONSTRAINT `chk_trx_void`  CHECK (`status` <> 'void' OR (`void_reason` IS NOT NULL AND CHAR_LENGTH(`void_reason`) >= 3 AND `voided_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- D3  orders (item lines of a transaction)
-- ------------------------------------------------------------
CREATE TABLE `orders` (
  `order_id`       INT NOT NULL AUTO_INCREMENT,
  `transaction_id` INT NOT NULL,
  `menu_id`        INT NOT NULL,
  `quantity`       INT NOT NULL,
  `price`          INT NOT NULL COMMENT 'base selling price at transaction time',
  `cost_price`     INT NOT NULL COMMENT 'base cost price at transaction time',
  `modifier_price` INT NOT NULL DEFAULT 0 COMMENT 'sum of extra prices per unit',
  `modifier_cost`  INT NOT NULL DEFAULT 0 COMMENT 'sum of extra costs per unit',
  `subtotal`       INT NOT NULL COMMENT '(price + modifier_price) x quantity',
  PRIMARY KEY (`order_id`),
  KEY `idx_orders_transaction` (`transaction_id`),
  KEY `idx_orders_menu` (`menu_id`),
  CONSTRAINT `fk_orders_transaction`
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_menu`
    FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_orders_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- D9  order_item_modifiers (frozen snapshot of chosen modifiers)
--     deliberately NO foreign key to the modifier tables
-- ------------------------------------------------------------
CREATE TABLE `order_item_modifiers` (
  `order_modifier_id` INT         NOT NULL AUTO_INCREMENT,
  `order_id`          INT         NOT NULL,
  `modifier_name`     VARCHAR(50) NOT NULL COMMENT 'name at transaction time',
  `price`             INT         NOT NULL DEFAULT 0 COMMENT 'extra price per unit at transaction time',
  `cost_price`        INT         NOT NULL DEFAULT 0 COMMENT 'extra cost per unit at transaction time',
  PRIMARY KEY (`order_modifier_id`),
  KEY `idx_oim_order` (`order_id`),
  CONSTRAINT `fk_oim_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_oim_price` CHECK (`price` >= 0),
  CONSTRAINT `chk_oim_cost`  CHECK (`cost_price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- D6  finance (manual income & expenses)
-- ------------------------------------------------------------
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
  CONSTRAINT `fk_finance_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_finance_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- D10  cash_closings (end-of-day cash drawer audit, F15)
--      audit record only: never writes to finance
-- ------------------------------------------------------------
CREATE TABLE `cash_closings` (
  `closing_id`    INT           NOT NULL AUTO_INCREMENT,
  `closing_date`  DATE          NOT NULL COMMENT 'one closing per date',
  `user_id`       INT           NOT NULL COMMENT 'admin who saved the closing',
  `opening_cash`  INT           NOT NULL DEFAULT 0 COMMENT 'drawer float, >= 0',
  `cash_sales`    INT           NOT NULL DEFAULT 0 COMMENT 'sum of non-void cash totals of closing_date',
  `cash_out`      INT           NOT NULL DEFAULT 0 COMMENT '>= 0, not written to finance',
  `expected_cash` INT           NOT NULL DEFAULT 0 COMMENT 'opening_cash + cash_sales - cash_out',
  `counted_cash`  INT           NOT NULL DEFAULT 0 COMMENT 'physical cash counted, >= 0',
  `difference`    INT           NOT NULL DEFAULT 0 COMMENT 'counted_cash - expected_cash (negative = shortage)',
  `note`          VARCHAR(255)  NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`closing_id`),
  UNIQUE KEY `uk_cash_closings_date` (`closing_date`),
  KEY `idx_cash_closings_user` (`user_id`),
  CONSTRAINT `fk_cash_closings_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_cc_opening` CHECK (`opening_cash` >= 0),
  CONSTRAINT `chk_cc_cash_out` CHECK (`cash_out` >= 0),
  CONSTRAINT `chk_cc_counted` CHECK (`counted_cash` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- D11  login_attempts (failed logins, rate limiting, F01)
--      5 failures per username or IP within 15 min -> 15 min lock
--      no foreign keys; rows older than 24 h are purged by the app
-- ------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `attempt_id`   INT          NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(50)  NOT NULL COMMENT 'as typed, also if it does not exist',
  `ip_address`   VARCHAR(45)  NOT NULL COMMENT 'IPv4 or IPv6',
  `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attempt_id`),
  KEY `idx_login_user` (`username`, `attempted_at`),
  KEY `idx_login_ip` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- D5  settings (single configuration row, settings_id = 1)
-- ------------------------------------------------------------
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

-- ============================================================
-- INITIAL DATA (Appendix D of the SRS Rev. D.1)
-- ============================================================

INSERT INTO `settings`
  (`settings_id`, `business_name`, `business_address`, `table_count`,
   `receipt_footer`, `operating_percent`, `qris_bank`, `qris_owner_name`, `qris_image`)
VALUES
  (1, 'Kedai Paradise', 'RT 01 RW 02, Kandang Gampang, Purbalingga',
   10, 'Terima kasih telah berkunjung!', 50.00, NULL, NULL, NULL);

-- Admin accounts (Bapak Fajar & Ibu Fitri)
-- Temporary password for both: Admin#12345 (bcrypt) -> CHANGE AFTER FIRST LOGIN
-- (cash_closings and login_attempts start empty)
INSERT INTO `users` (`user_id`, `username`, `password_hash`, `name`, `role`, `status`) VALUES
  (1, 'fajar', '$2y$10$YQBKzOC78bPmAiqFgyVQuOUPnaUxXvrBoqV.fD5i1LqGe5HLENqPu', 'Bapak Fajar', 'admin', 1),
  (2, 'fitri', '$2y$10$YQBKzOC78bPmAiqFgyVQuOUPnaUxXvrBoqV.fD5i1LqGe5HLENqPu', 'Ibu Fitri',   'admin', 1);

-- SAMPLE menu for testing (price / cost)
INSERT INTO `menu` (`menu_id`, `name`, `category`, `price`, `cost_price`, `photo`, `available`, `status`) VALUES
  (1, 'Kopi Susu',     'Minuman', 18000, 10000, NULL, 1, 1),
  (2, 'Mie Goreng',    'Makanan', 14000,  9000, NULL, 1, 1),
  (3, 'Es Teh',          'Minuman',  5000,  2000, NULL, 1, 1),
  (4, 'Roti Bakar',    'Snack',   12000,  7500, NULL, 1, 1);

-- SAMPLE modifier groups
INSERT INTO `menu_modifier_groups` (`group_id`, `menu_id`, `name`, `type`, `is_required`) VALUES
  (1, 2, 'Tingkat Pedas',        'single',   1),   -- Mie Goreng, mandatory
  (2, 2, 'Toppings / Tambahan', 'multiple', 0),   -- Mie Goreng, optional
  (3, 1, 'Ukuran',               'single',   1);   -- Kopi Susu, mandatory

-- SAMPLE modifier items (extra price / extra cost)
INSERT INTO `menu_modifier_items` (`group_id`, `name`, `extra_price`, `extra_cost`, `available`) VALUES
  (1, 'Level 1',   0,    0, 1),
  (1, 'Level 2',   0,    0, 1),
  (1, 'Level 3',   0,    0, 1),
  (2, 'Telur Tambahan', 3000, 2000, 1),
  (2, 'Sosis',   4000, 2500, 1),
  (2, 'Keju',    3000, 1500, 1),
  (3, 'Reguler',   0,    0, 1),
  (3, 'Large',     4000, 1500, 1);
