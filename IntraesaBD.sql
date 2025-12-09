DROP DATABASE IF EXISTS intraesa;
Create database intraesa;
USE intraesa;

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE;
SET SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

CREATE SCHEMA IF NOT EXISTS `intraesa`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `intraesa`;

-- =========================================
-- 1) USERS (cuentas del sistema)
-- =========================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;

-- =========================================
-- 2) ROLES y USER_ROLES (permisos básicos)
-- =========================================
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  CONSTRAINT `fk_user_roles_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_user_roles_role`
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;

-- =========================================
-- 3) CLIENTS (clientes ligados a users)
-- =========================================
CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `company` VARCHAR(80) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_clients_user` (`user_id`),
  CONSTRAINT `fk_clients_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;

-- =========================================
-- 4) PLANS (planes de créditos)
-- =========================================
CREATE TABLE IF NOT EXISTS `plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(32) DEFAULT NULL,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `price_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `credits` INT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
              ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plans_code` (`code`),
  CONSTRAINT `chk_plans_price_nonneg`  CHECK (`price_usd` >= 0),
  CONSTRAINT `chk_plans_credits_nonneg` CHECK (`credits` >= 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;

-- =========================================
-- 5) WALLETS (saldo de créditos del cliente)
-- =========================================
CREATE TABLE IF NOT EXISTS `wallets` (
  `client_id` INT UNSIGNED NOT NULL,
  `credits_balance` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`client_id`),
  CONSTRAINT `fk_wallets_client`
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `chk_wallets_balance_nonneg` CHECK (`credits_balance` >= 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;

-- =========================================
-- 6) TOP_UPS (historial de recargas / compra de plan)
-- =========================================
CREATE TABLE IF NOT EXISTS `top_ups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `credits_added` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_topups_client_created` (`client_id`, `created_at`),
  CONSTRAINT `fk_topups_client`
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_topups_plan`
    FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_topups_credits_pos` CHECK (`credits_added` > 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;

-- =========================================
-- 7) SHIPMENTS (envíos)
-- =========================================
CREATE TABLE IF NOT EXISTS `shipments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NOT NULL,
  `receiver_name` VARCHAR(80) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `address` TEXT NOT NULL,
  `depto` VARCHAR(50) NOT NULL,
  `municipio` VARCHAR(50) NOT NULL,
  `weight_kg` DECIMAL(4,2) NOT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'verifying',
  `priority` TINYINT(1) NOT NULL DEFAULT 0,
  `label_code` VARCHAR(30) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
              ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shipments_label_code` (`label_code`),
  KEY `idx_shipments_client_created` (`client_id`, `created_at`),
  KEY `idx_shipments_status` (`status`),
  CONSTRAINT `fk_shipments_client`
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `chk_shipments_weight`
    CHECK (`weight_kg` >= 0 AND `weight_kg` <= 2.00),
  CONSTRAINT `chk_shipments_status`
    CHECK (`status` IN ('verifying','pending_collection','collected','in_transit','delivered'))
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;

-- =========================================
-- 8) SHIPMENT_EVENTS (historial de estados)
-- =========================================
CREATE TABLE IF NOT EXISTS `shipment_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shipment_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `at_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_shipment_time` (`shipment_id`, `at_time`),
  CONSTRAINT `fk_events_shipment`
    FOREIGN KEY (`shipment_id`) REFERENCES `shipments`(`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;

-- =========================================
-- 9) WALLET_MOVEMENTS (auditoría créditos) [opcional pero pro]
-- =========================================
CREATE TABLE IF NOT EXISTS `wallet_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NOT NULL,
  `movement_type` ENUM('top_up','shipment_charge','adjustment') NOT NULL,
  `ref_id` INT UNSIGNED NULL,
  `credits_delta` INT NOT NULL,  -- +N o -1
  `note` VARCHAR(200) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wm_client_time` (`client_id`, `created_at`),
  CONSTRAINT `fk_wm_client`
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `chk_wm_delta_nonzero` CHECK (`credits_delta` <> 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;

-- =========================================
-- 10) SETTINGS (configuraciones varias)
-- =========================================
CREATE TABLE IF NOT EXISTS `settings` (
  `skey` VARCHAR(40) NOT NULL,
  `svalue` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;

-- =========================================
-- SEEDS BÁSICOS (opcionales)
-- =========================================

-- Roles: admin y client
INSERT INTO `roles` (`id`, `name`) VALUES
  (1, 'admin'),
  (2, 'client')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Ajustes de simulación / negocio
INSERT INTO `settings` (`skey`, `svalue`) VALUES
  ('collection_cutoff', '18:00'), -- hora límite de recolección
  ('sim_enabled', '0'),
  ('sim_factor', '1.0')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- Planes base (40, 20, 10 créditos)
INSERT INTO `plans` (`id`, `code`, `name`, `description`, `price_usd`, `credits`, `is_active`, `sort_order`)
VALUES
  (1, 'PLAN_40', 'Plan 40 créditos', 'Plan estándar (antes 90 envíos).', 0.00, 40, 1, 1),
  (2, 'PLAN_20', 'Plan 20 créditos', 'Plan medio (antes 80 envíos).',    0.00, 20, 1, 2),
  (3, 'PLAN_10', 'Plan 10 créditos', 'Plan básico (antes 70 envíos).',   0.00, 10, 1, 3)
ON DUPLICATE KEY UPDATE
  name        = VALUES(name),
  description = VALUES(description),
  credits     = VALUES(credits),
  is_active   = VALUES(is_active),
  sort_order  = VALUES(sort_order);


INSERT INTO `users` (`id`, `name`, `email`, `password_hash`)
VALUES (1, 'Admin', 'admin@example.com', 'CAMBIA_ESTE_HASH')
ON DUPLICATE KEY UPDATE
  name = VALUES(name);

-- Rol admin asignado al usuario 1
INSERT INTO `user_roles` (`user_id`, `role_id`)
VALUES (1, 1)
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- =========================================
-- Restaurar settings globales
-- =========================================
SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
