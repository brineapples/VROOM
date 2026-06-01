CREATE DATABASE IF NOT EXISTS `VROOMDB`;
USE `VROOMDB`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `repair_order_services`;
DROP TABLE IF EXISTS `repair_orders`;
DROP TABLE IF EXISTS `vehicles`;
DROP TABLE IF EXISTS `vehicle_models`;
DROP TABLE IF EXISTS `vehicle_makes`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `service_types`;
DROP TABLE IF EXISTS `customers`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `roles` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(30) NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_roles_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role_id` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customers` (
  `customer_id` int NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vehicle_makes` (
  `make_id` int NOT NULL AUTO_INCREMENT,
  `make_name` varchar(50) NOT NULL,
  PRIMARY KEY (`make_id`),
  UNIQUE KEY `uq_vehicle_makes_make_name` (`make_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vehicle_models` (
  `model_id` int NOT NULL AUTO_INCREMENT,
  `make_id` int NOT NULL,
  `model_name` varchar(50) NOT NULL,
  PRIMARY KEY (`model_id`),
  UNIQUE KEY `uq_vehicle_models_make_model` (`make_id`, `model_name`),
  CONSTRAINT `fk_vehicle_models_make` FOREIGN KEY (`make_id`) REFERENCES `vehicle_makes` (`make_id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vehicles` (
  `vehicle_id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `plate_number` varchar(30) NOT NULL,
  `model_id` int NOT NULL,
  `vehicle_year` int DEFAULT NULL,
  PRIMARY KEY (`vehicle_id`),
  UNIQUE KEY `uq_vehicles_plate_number` (`plate_number`),
  KEY `idx_vehicles_customer_id` (`customer_id`),
  KEY `idx_vehicles_model_id` (`model_id`),
  CONSTRAINT `fk_vehicles_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `fk_vehicles_model` FOREIGN KEY (`model_id`) REFERENCES `vehicle_models` (`model_id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_types` (
  `service_type_id` int NOT NULL AUTO_INCREMENT,
  `service_name` varchar(100) NOT NULL,
  `standard_hours` decimal(5,2) NOT NULL,
  `hourly_rate` decimal(8,2) NOT NULL,
  PRIMARY KEY (`service_type_id`),
  UNIQUE KEY `uq_service_types_service_name` (`service_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `repair_orders` (
  `repair_order_id` int NOT NULL AUTO_INCREMENT,
  `vehicle_id` int NOT NULL,
  `service_date` date NOT NULL,
  `problem_description` text DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`repair_order_id`),
  KEY `idx_repair_orders_vehicle_id` (`vehicle_id`),
  KEY `idx_repair_orders_resolved_at` (`resolved_at`),
  CONSTRAINT `fk_repair_orders_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `repair_order_services` (
  `repair_order_id` int NOT NULL,
  `service_type_id` int NOT NULL,
  PRIMARY KEY (`repair_order_id`, `service_type_id`),
  KEY `idx_repair_order_services_service_type_id` (`service_type_id`),
  CONSTRAINT `fk_repair_order_services_order` FOREIGN KEY (`repair_order_id`) REFERENCES `repair_orders` (`repair_order_id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT `fk_repair_order_services_service` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`service_type_id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `audit_log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action_name` varchar(30) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `details` text DEFAULT NULL,
  `logged_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_log_id`),
  KEY `idx_audit_logs_user_id` (`user_id`),
  KEY `idx_audit_logs_logged_at` (`logged_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`role_name`) VALUES
  ('super_admin'),
  ('admin');

INSERT INTO `users` (`role_id`, `full_name`, `username`, `password_hash`, `is_active`)
SELECT `role_id`, 'Super Admin', 'superadmin', '$2y$12$Wha0w1F.tH7ck4TEI8q0Q.1B78CGchN46S0aGWNDv1NJ/74yI3Q0W', 1
FROM `roles`
WHERE `role_name` = 'super_admin';

INSERT INTO `users` (`role_id`, `full_name`, `username`, `password_hash`, `is_active`)
SELECT `role_id`, 'Admin User', 'admin', '$2y$12$Wha0w1F.tH7ck4TEI8q0Q.1B78CGchN46S0aGWNDv1NJ/74yI3Q0W', 1
FROM `roles`
WHERE `role_name` = 'admin';
