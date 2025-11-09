-- Additional tables for complete system functionality
-- Run this after the main database schema

USE minang_restaurant;

-- Working Schedules Table
CREATE TABLE `working_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `shift_start` time NOT NULL,
  `shift_end` time NOT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `working_hours` decimal(4,2) DEFAULT 8.00,
  `schedule_type` enum('regular','overtime','training','meeting') DEFAULT 'regular',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_date` (`user_id`,`schedule_date`),
  KEY `idx_schedule_date` (`schedule_date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payroll Table
CREATE TABLE `payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `payroll_month` int(2) NOT NULL,
  `payroll_year` int(4) NOT NULL,
  `base_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `overtime_pay` decimal(10,2) DEFAULT 0.00,
  `attendance_bonus` decimal(10,2) DEFAULT 0.00,
  `other_allowances` decimal(10,2) DEFAULT 0.00,
  `absence_deduction` decimal(10,2) DEFAULT 0.00,
  `other_deductions` decimal(10,2) DEFAULT 0.00,
  `gross_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_deductions` decimal(10,2) DEFAULT 0.00,
  `net_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `working_days` int(2) DEFAULT 0,
  `total_hours` decimal(6,2) DEFAULT 0.00,
  `payment_status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `payment_method` tinyint(1) DEFAULT NULL COMMENT '1=Cash, 2=Bank, 3=Cheque',
  `payment_date` date DEFAULT NULL,
  `payment_notes` text,
  `generated_by` int(11) DEFAULT NULL,
  `generated_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `paid_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_period` (`user_id`,`payroll_month`,`payroll_year`),
  KEY `idx_payroll_period` (`payroll_month`,`payroll_year`),
  KEY `idx_payment_status` (`payment_status`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`paid_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Purchase Orders Table
CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','sent','confirmed','delivered','cancelled') DEFAULT 'pending',
  `status_notes` text,
  `status_updated_by` int(11) DEFAULT NULL,
  `status_updated_at` timestamp NULL DEFAULT NULL,
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order_date` (`order_date`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`status_updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Purchase Order Items Table
CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` decimal(8,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_product` (`product_id`),
  FOREIGN KEY (`order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tables Management
CREATE TABLE `tables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_number` varchar(20) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `capacity` int(2) NOT NULL DEFAULT 4,
  `location` varchar(100) DEFAULT NULL,
  `status` enum('available','occupied','reserved','maintenance') DEFAULT 'available',
  `status_notes` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `table_number` (`table_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Reservations
CREATE TABLE `table_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `reservation_time` datetime NOT NULL,
  `party_size` int(2) DEFAULT NULL,
  `status` enum('active','completed','cancelled','no_show') DEFAULT 'active',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_table` (`table_id`),
  KEY `idx_reservation_time` (`reservation_time`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`table_id`) REFERENCES `tables`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kitchen Log for order tracking
CREATE TABLE `kitchen_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL COMMENT '0=Pending, 1=Cooking, 2=Ready, 3=Cancelled',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`order_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System Alerts
CREATE TABLE `system_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alert_type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_priority` (`priority`),
  FOREIGN KEY (`order_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Held Orders Table
CREATE TABLE `held_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_type` tinyint(1) NOT NULL DEFAULT 1,
  `table_number` varchar(10) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text,
  `held_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `idx_user` (`user_id`),
  KEY `idx_held_at` (`held_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Held Order Items Table
CREATE TABLE `held_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `held_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` decimal(8,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `idx_held_order` (`held_order_id`),
  KEY `idx_product` (`product_id`),
  FOREIGN KEY (`held_order_id`) REFERENCES `held_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample tables
INSERT INTO `tables` (`table_number`, `table_name`, `capacity`, `location`) VALUES
('01', 'Table 1', 4, 'Main Hall'),
('02', 'Table 2', 4, 'Main Hall'),
('03', 'Table 3', 6, 'Main Hall'),
('04', 'Table 4', 2, 'Main Hall'),
('05', 'Table 5', 4, 'Main Hall'),
('06', 'Table 6', 4, 'Main Hall'),
('07', 'Table 7', 8, 'VIP Section'),
('08', 'Table 8', 8, 'VIP Section'),
('09', 'Table 9', 4, 'Outdoor'),
('10', 'Table 10', 4, 'Outdoor');

-- Update sales table to include kitchen status
ALTER TABLE `sales` 
ADD COLUMN `kitchen_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Pending, 1=Cooking, 2=Ready, 3=Cancelled' AFTER `is_printed`,
ADD COLUMN `kitchen_updated_at` timestamp NULL DEFAULT NULL AFTER `kitchen_status`;

-- Add indexes for better performance
CREATE INDEX `idx_kitchen_status` ON `sales`(`kitchen_status`);
CREATE INDEX `idx_order_type` ON `sales`(`order_type`);
CREATE INDEX `idx_table_number` ON `sales`(`table_number`);

-- Insert sample working schedules for current week
INSERT INTO `working_schedules` (`user_id`, `schedule_date`, `shift_start`, `shift_end`, `break_start`, `break_end`, `working_hours`, `schedule_type`, `created_by`) VALUES
(2, CURDATE(), '08:00:00', '16:00:00', '12:00:00', '13:00:00', 7.00, 'regular', 1),
(3, CURDATE(), '09:00:00', '17:00:00', '13:00:00', '14:00:00', 7.00, 'regular', 1),
(4, CURDATE(), '14:00:00', '22:00:00', '18:00:00', '19:00:00', 7.00, 'regular', 1);

-- Insert sample purchase order
INSERT INTO `purchase_orders` (`supplier_id`, `order_number`, `order_date`, `expected_delivery`, `subtotal`, `total`, `status`, `notes`, `created_by`) VALUES
(1, 'PO-20240315-001', '2024-03-15', '2024-03-20', 2500.00, 2500.00, 'sent', 'Weekly stock replenishment order', 1),
(2, 'PO-20240315-002', '2024-03-15', '2024-03-18', 800.00, 800.00, 'pending', 'Fresh vegetables order', 1);

-- Insert sample purchase order items
INSERT INTO `purchase_order_items` (`order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 'Rendang Daging', 20, 30.00, 600.00),
(1, 2, 'Nasi Padang Komplit', 30, 25.00, 750.00),
(1, 3, 'Ayam Pop Crispy', 25, 28.00, 700.00),
(2, 6, 'Gulai Kambing', 15, 30.00, 450.00),
(2, 7, 'Ikan Bakar Padang', 10, 25.00, 250.00);