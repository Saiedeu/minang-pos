-- Restaurant Management Tables
-- Add these tables to your existing database



-- Tasks/To-Do List Table
CREATE TABLE IF NOT EXISTS `restaurant_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general' COMMENT 'general, meeting, maintenance, cleaning, inventory',
  `priority` tinyint(4) DEFAULT 2 COMMENT '1=Low, 2=Medium, 3=High, 4=Urgent',
  `due_date` date DEFAULT NULL,
  `due_time` time DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `completion_notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `created_by` (`created_by`),
  KEY `due_date` (`due_date`),
  KEY `status` (`status`),
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Restaurant Plans Table
CREATE TABLE IF NOT EXISTS `restaurant_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `plan_type` enum('weekly','monthly','event','project') DEFAULT 'weekly',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `assigned_department` varchar(50) DEFAULT NULL COMMENT 'kitchen, service, management, all',
  `priority` tinyint(4) DEFAULT 2,
  `status` enum('draft','active','in_progress','completed','cancelled') DEFAULT 'draft',
  `completion_percentage` tinyint(4) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `start_date` (`start_date`),
  KEY `plan_type` (`plan_type`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Temperature Records Table
CREATE TABLE IF NOT EXISTS `temperature_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_type` enum('chiller','freezer') NOT NULL,
  `equipment_number` varchar(10) NOT NULL,
  `record_date` date NOT NULL,
  `time_slot` enum('6am','10am','2pm','6pm','10pm') NOT NULL,
  `temperature` decimal(5,2) NOT NULL COMMENT 'Temperature in Celsius',
  `remarks` text DEFAULT NULL,
  `corrective_action` text DEFAULT NULL,
  `shift_person` varchar(100) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_record` (`equipment_type`, `equipment_number`, `record_date`, `time_slot`),
  KEY `equipment_type` (`equipment_type`),
  KEY `record_date` (`record_date`),
  KEY `recorded_by` (`recorded_by`),
  FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expiration Records Table
CREATE TABLE IF NOT EXISTS `expiration_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(200) NOT NULL,
  `product_id` int(11) DEFAULT NULL COMMENT 'Link to products table if applicable',
  `batch_number` varchar(50) DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(10) DEFAULT 'PCS',
  `location` varchar(100) DEFAULT NULL COMMENT 'Storage location',
  `supplier` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','disposed','used') DEFAULT 'active',
  `disposal_date` date DEFAULT NULL,
  `disposal_reason` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `expiry_date` (`expiry_date`),
  KEY `status` (`status`),
  KEY `recorded_by` (`recorded_by`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Alerts Table
CREATE TABLE IF NOT EXISTS `system_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alert_type` varchar(50) NOT NULL COMMENT 'TEMPERATURE, EXPIRY, TASK, SYSTEM',
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `severity` enum('info','warning','critical') DEFAULT 'info',
  `reference_id` int(11) DEFAULT NULL COMMENT 'Reference to related record',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `alert_type` (`alert_type`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data
INSERT INTO `restaurant_tasks` (`title`, `description`, `category`, `priority`, `due_date`, `assigned_to`, `created_by`) VALUES
('Daily kitchen cleaning', 'Complete deep cleaning of kitchen equipment and surfaces', 'cleaning', 3, CURDATE(), 6, 1),
('Weekly inventory count', 'Count all inventory items and update stock levels', 'inventory', 2, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 2, 1),
('Staff meeting - Menu updates', 'Discuss new menu items and seasonal changes', 'meeting', 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), NULL, 1),
('Equipment maintenance check', 'Monthly maintenance check for all kitchen equipment', 'maintenance', 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 7, 1);

INSERT INTO `restaurant_plans` (`title`, `description`, `plan_type`, `start_date`, `end_date`, `assigned_department`, `created_by`) VALUES
('Ramadan Special Menu Launch', 'Launch special Ramadan menu with traditional dishes', 'event', '2024-03-01', '2024-04-30', 'all', 1),
('Weekly Staff Training Program', 'Implement weekly training sessions for service improvement', 'weekly', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'service', 1),
('Monthly Inventory Optimization', 'Optimize inventory levels and reduce waste', 'monthly', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'kitchen', 1);

-- Sample temperature records
INSERT INTO `temperature_records` (`equipment_type`, `equipment_number`, `record_date`, `time_slot`, `temperature`, `shift_person`, `recorded_by`) VALUES
('chiller', '1', CURDATE(), '6am', 4.5, 'Morning Shift - Ahmed', 6),
('chiller', '1', CURDATE(), '10am', 5.0, 'Morning Shift - Ahmed', 6),
('chiller', '2', CURDATE(), '6am', 3.8, 'Morning Shift - Ahmed', 6),
('freezer', '1', CURDATE(), '6am', -15.2, 'Morning Shift - Ahmed', 6),
('freezer', '1', CURDATE(), '10am', -14.8, 'Morning Shift - Ahmed', 6);

-- Sample expiration records
INSERT INTO `expiration_records` (`item_name`, `expiry_date`, `quantity`, `unit`, `location`, `supplier`, `recorded_by`) VALUES
('Fresh Beef - Rendang Cut', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 5.5, 'KG', 'Chiller 1', 'Al-Reef Food Supplies', 1),
('Coconut Milk Cans', DATE_ADD(CURDATE(), INTERVAL 10 DAY), 24, 'PCS', 'Dry Store', 'Qatar Fresh Supplies', 1),
('Fresh Vegetables Mix', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 3.0, 'KG', 'Chiller 2', 'Local Market', 1),
('Spice Mix - Rendang', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 500, 'GM', 'Spice Cabinet', 'Spice Masters Qatar', 1);