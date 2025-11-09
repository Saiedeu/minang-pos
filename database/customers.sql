-- Add customers table to the database
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` varchar(20) NOT NULL COMMENT 'Business customer ID (CUS20240101001)',
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL COMMENT 'Special notes about customer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_id` (`customer_id`),
  UNIQUE KEY `phone` (`phone`),
  KEY `name` (`name`),
  KEY `created_by` (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add customer_id column to sales table if it doesn't exist
ALTER TABLE `sales` 
ADD COLUMN `customer_id` int(11) DEFAULT NULL AFTER `customer_address`,
ADD KEY `customer_id` (`customer_id`),
ADD FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

-- Insert sample customers
INSERT INTO `customers` (`customer_id`, `name`, `phone`, `email`, `address`, `notes`, `created_by`) VALUES
('CUS20240101001', 'Ahmed Al-Mahmoud', '+974-5555-1001', 'ahmed.mahmoud@email.com', 'West Bay, Doha, Qatar', 'Regular customer - prefers spicy food', 1),
('CUS20240101002', 'Fatima Al-Zahra', '+974-5555-1002', 'fatima.zahra@email.com', 'Al-Sadd, Doha, Qatar', 'Vegetarian customer', 1),
('CUS20240101003', 'Mohammed Al-Thani', '+974-5555-1003', 'mohammed.thani@email.com', 'Lusail, Qatar', 'VIP customer - large orders', 1),
('CUS20240101004', 'Aisha Hassan', '+974-5555-1004', 'aisha.hassan@email.com', 'Al-Rayyan, Qatar', 'Frequent delivery orders', 1),
('CUS20240101005', 'Omar Khalil', '+974-5555-1005', 'omar.khalil@email.com', 'Al-Wakrah, Qatar', 'Corporate account', 1);