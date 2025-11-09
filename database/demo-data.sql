-- Sample Data for Minang Restaurant System
-- Insert this after creating the main database schema

USE minang_restaurant;

-- Insert additional users
INSERT INTO `users` (`name`, `username`, `password`, `role`, `qid_number`, `phone`, `email`, `joining_date`, `is_active`) VALUES
('Admin User', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, '29876543211', '+974-5555-0002', 'admin@minang.com', '2024-01-01', 1),
('Manager Ali', 'manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, '29876543212', '+974-5555-0003', 'manager@minang.com', '2024-01-15', 1),
('Waiter Ahmad', 'ahmad', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, '29876543213', '+974-5555-0004', 'ahmad@minang.com', '2024-02-01', 1);

-- Insert more sample products
INSERT INTO `products` (`code`, `name`, `name_ar`, `description`, `ingredients`, `category_id`, `cost_price`, `sell_price`, `quantity`, `unit`, `reorder_level`, `list_in_pos`) VALUES
('PRD006', 'Gulai Kambing', 'جولاي كامبينغ', 'Traditional goat curry with aromatic herbs', 'Goat meat, coconut milk, spices', 1, 30.00, 55.00, 10, 'PCS', 3, 1),
('PRD007', 'Ikan Bakar Padang', 'إيكان باكار بادانغ', 'Grilled fish with Padang special sauce', 'Fresh fish, spices, sambal', 1, 25.00, 46.00, 14, 'PCS', 5, 1),
('PRD008', 'Sate Padang', 'ساتيه بادانغ', 'Grilled beef skewers with traditional sauce', 'Beef, spices, peanut sauce', 1, 18.00, 35.00, 30, 'PCS', 10, 1),
('PRD009', 'Perkedel Kentang', 'بيركيديل كينتانغ', 'Indonesian potato fritters', 'Potato, herbs, spices', 2, 5.00, 15.00, 35, 'PCS', 10, 1),
('PRD010', 'Kopi Tubruk', 'كوبي توبروك', 'Traditional Indonesian ground coffee', 'Coffee beans, sugar', 3, 3.00, 14.00, 80, 'PCS', 20, 1);

-- Insert sample suppliers
INSERT INTO `suppliers` (`name`, `contact_person`, `phone`, `email`, `address`, `cr_number`, `is_active`) VALUES
('Al-Reef Food Supplies', 'Mohammed Al-Rashid', '+974-4444-1111', 'info@alreef.qa', 'Industrial Area, Doha, Qatar', 'CR-111222333', 1),
('Qatar Fresh Vegetables', 'Ahmad Hassan', '+974-4444-2222', 'orders@qatarfresh.qa', 'Wholesale Market, Doha, Qatar', 'CR-444555666', 1),
('Spice Masters Qatar', 'Fatima Al-Zahra', '+974-4444-3333', 'sales@spicemasters.qa', 'Souq Waqif, Doha, Qatar', 'CR-777888999', 1);

-- Insert sample expense categories
INSERT INTO `expense_categories` (`name`, `description`) VALUES
('Purchases', 'Supplier payments and stock purchases'),
('Salaries', 'Staff salaries and wages'),
('Transportation', 'Delivery and transport costs');

-- Insert sample shifts
INSERT INTO `shifts` (`user_id`, `opening_balance`, `start_time`, `end_time`, `total_sales`, `cash_sales`, `card_sales`, `credit_sales`, `foc_sales`, `discount_amount`, `expected_cash`, `physical_cash`, `shortage_extra`, `is_closed`) VALUES
(2, 500.00, '2024-03-15 08:00:00', '2024-03-15 16:00:00', 2850.00, 1200.00, 800.00, 650.00, 50.00, 150.00, 1700.00, 1695.00, -5.00, 1),
(2, 1695.00, '2024-03-15 16:00:00', '2024-03-15 23:00:00', 3200.00, 1500.00, 900.00, 600.00, 80.00, 120.00, 3195.00, 3200.00, 5.00, 1);

-- Insert sample sales
INSERT INTO `sales` (`receipt_number`, `order_number`, `shift_id`, `user_id`, `customer_name`, `customer_phone`, `order_type`, `table_number`, `subtotal`, `discount`, `delivery_fee`, `total`, `payment_method`, `amount_received`, `change_amount`) VALUES
('R240315-001', 'LMR-031501', 1, 2, NULL, NULL, 1, '5', 85.00, 5.00, 0.00, 80.00, 1, 100.00, 20.00),
('R240315-002', 'LMR-031502', 1, 2, 'Ahmed Al-Mansoori', '+974-5555-1234', 3, NULL, 120.00, 0.00, 15.00, 135.00, 5, 0.00, 0.00),
('R240315-003', 'LMR-031503', 1, 2, NULL, NULL, 2, NULL, 65.00, 0.00, 0.00, 65.00, 2, 0.00, 0.00);

-- Insert sample sale items
INSERT INTO `sale_items` (`sale_id`, `product_id`, `product_name`, `product_name_ar`, `quantity`, `unit_price`, `total_price`, `notes`) VALUES
(1, 1, 'Rendang Daging', 'رندانغ اللحم', 1, 45.00, 45.00, 'Medium spicy'),
(1, 4, 'Teh Tarik Special', 'شاي تاريك مميز', 2, 12.00, 24.00, NULL),
(1, 5, 'Es Cendol Durian', 'آيس سيندول دوريان', 1, 22.00, 22.00, 'Extra durian'),
(2, 2, 'Nasi Padang Komplit', 'نasi بادانغ كامل', 2, 38.00, 76.00, NULL),
(2, 3, 'Ayam Pop Crispy', 'دجاج بوب مقرمش', 1, 42.00, 42.00, 'Extra crispy'),
(3, 1, 'Rendang Daging', 'رندانغ اللحم', 1, 45.00, 45.00, NULL),
(3, 4, 'Teh Tarik Special', 'شاي تاريك مميز', 1, 12.00, 12.00, NULL);

-- Insert sample attendance records
INSERT INTO `attendance` (`user_id`, `attendance_date`, `sign_in_time`, `sign_out_time`, `total_hours`) VALUES
(2, '2024-03-15', '2024-03-15 07:45:00', '2024-03-15 16:15:00', 8.50),
(3, '2024-03-15', '2024-03-15 09:00:00', '2024-03-15 17:00:00', 8.00),
(4, '2024-03-15', '2024-03-15 14:00:00', '2024-03-15 22:30:00', 8.50);

-- Insert sample purchases
INSERT INTO `purchases` (`supplier_id`, `invoice_number`, `purchase_date`, `payment_method`, `subtotal`, `discount`, `total`, `paid_amount`, `payment_status`, `created_by`) VALUES
(1, 'ALRF-2024-001', '2024-03-14', 1, 1500.00, 50.00, 1450.00, 1450.00, 2, 1),
(2, 'QFV-2024-015', '2024-03-13', 3, 800.00, 0.00, 800.00, 400.00, 1, 1),
(3, 'SMQ-2024-008', '2024-03-12', 2, 650.00, 25.00, 625.00, 0.00, 0, 1);

-- Update product quantities from purchases
UPDATE products SET quantity = quantity + 50 WHERE id = 1; -- Rendang
UPDATE products SET quantity = quantity + 30 WHERE id = 2; -- Nasi Padang
UPDATE products SET quantity = quantity + 25 WHERE id = 3; -- Ayam Pop

-- Insert stock movements for purchases
INSERT INTO `stock_movements` (`product_id`, `type`, `reference_type`, `reference_id`, `quantity`, `reason`, `created_by`) VALUES
(1, 'IN', 'PURCHASE', 1, 50, 'Purchase from Al-Reef Food Supplies', 1),
(2, 'IN', 'PURCHASE', 1, 30, 'Purchase from Al-Reef Food Supplies', 1),
(3, 'IN', 'PURCHASE', 2, 25, 'Purchase from Qatar Fresh Vegetables', 1);

-- Insert stock movements for sales
INSERT INTO `stock_movements` (`product_id`, `type`, `reference_type`, `reference_id`, `quantity`, `reason`, `created_by`) VALUES
(1, 'OUT', 'SALE', 1, 1, 'Sale transaction', 2),
(4, 'OUT', 'SALE', 1, 2, 'Sale transaction', 2),
(5, 'OUT', 'SALE', 1, 1, 'Sale transaction', 2);