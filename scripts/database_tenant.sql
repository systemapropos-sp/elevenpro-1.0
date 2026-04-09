-- ============================================
-- SCRIPT DE MIGRACIÓN PARA BASE DE DATOS TENANT
-- ElevenPro POS - https://elevenpropos.com
-- ============================================
-- 
-- INSTRUCCIONES:
-- 1. Reemplazar {TENANT_DB_NAME} con el nombre de la base de datos del tenant
-- 2. Ejecutar este script para cada nuevo tenant
--
-- ============================================

-- ============================================
-- TABLA: USERS (Usuarios del sistema)
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'cashier', 'manager') DEFAULT 'cashier',
    `phone` VARCHAR(20) DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: CATEGORIES (Categorías de productos)
-- ============================================
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `color` VARCHAR(7) DEFAULT '#6366f1',
    `icon` VARCHAR(50) DEFAULT 'fa-box',
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: PRODUCTS (Productos)
-- ============================================
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category_id` INT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cost` DECIMAL(10,2) DEFAULT 0.00,
    `stock` INT DEFAULT 0,
    `min_stock` INT DEFAULT 5,
    `max_stock` INT DEFAULT 100,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `barcode` VARCHAR(100) DEFAULT NULL,
    `unit` VARCHAR(20) DEFAULT 'pieza',
    `is_active` TINYINT(1) DEFAULT 1,
    `allow_negative_stock` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_sku` (`sku`),
    INDEX `idx_barcode` (`barcode`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_stock` (`stock`),
    FULLTEXT INDEX `idx_search` (`name`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: CUSTOMERS (Clientes)
-- ============================================
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `zip_code` VARCHAR(10) DEFAULT NULL,
    `rfc` VARCHAR(13) DEFAULT NULL,
    `loyalty_points` INT DEFAULT 0,
    `credit_limit` DECIMAL(10,2) DEFAULT 0.00,
    `current_credit` DECIMAL(10,2) DEFAULT 0.00,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_phone` (`phone`),
    INDEX `idx_active` (`is_active`),
    FULLTEXT INDEX `idx_search` (`name`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: TRANSACTIONS (Transacciones/Ventas)
-- ============================================
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_number` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT DEFAULT NULL,
    `user_id` INT NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_rate` DECIMAL(5,2) DEFAULT 16.00,
    `discount` DECIMAL(10,2) DEFAULT 0.00,
    `discount_type` ENUM('percentage', 'fixed') DEFAULT 'fixed',
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('cash', 'card', 'transfer', 'credit', 'mixed') DEFAULT 'cash',
    `payment_reference` VARCHAR(255) DEFAULT NULL,
    `cash_received` DECIMAL(10,2) DEFAULT 0.00,
    `change_amount` DECIMAL(10,2) DEFAULT 0.00,
    `status` ENUM('pending', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `receipt_url` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX `idx_ticket` (`ticket_number`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_payment` (`payment_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: TRANSACTION_ITEMS (Items de transacciones)
-- ============================================
CREATE TABLE IF NOT EXISTS `transaction_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(10,2) DEFAULT 0.00,
    `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cost_price` DECIMAL(10,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    INDEX `idx_transaction` (`transaction_id`),
    INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: INVENTORY_LOGS (Registro de inventario)
-- ============================================
CREATE TABLE IF NOT EXISTS `inventory_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `type` ENUM('in', 'out', 'adjustment', 'sale', 'return') NOT NULL,
    `quantity` INT NOT NULL,
    `stock_before` INT NOT NULL,
    `stock_after` INT NOT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `reference_id` INT DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX `idx_product` (`product_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: SETTINGS (Configuración del negocio)
-- ============================================
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `setting_group` VARCHAR(50) DEFAULT 'general',
    `is_public` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_group` (`setting_group`),
    INDEX `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: PAYMENT_METHODS (Métodos de pago personalizados)
-- ============================================
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `icon` VARCHAR(50) DEFAULT 'fa-money-bill',
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: CASH_REGISTER (Caja registradora)
-- ============================================
CREATE TABLE IF NOT EXISTS `cash_register` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `opening_amount` DECIMAL(10,2) NOT NULL,
    `closing_amount` DECIMAL(10,2) DEFAULT NULL,
    `cash_sales` DECIMAL(10,2) DEFAULT 0.00,
    `card_sales` DECIMAL(10,2) DEFAULT 0.00,
    `transfer_sales` DECIMAL(10,2) DEFAULT 0.00,
    `credit_sales` DECIMAL(10,2) DEFAULT 0.00,
    `total_sales` DECIMAL(10,2) DEFAULT 0.00,
    `total_refunds` DECIMAL(10,2) DEFAULT 0.00,
    `expected_amount` DECIMAL(10,2) DEFAULT 0.00,
    `difference` DECIMAL(10,2) DEFAULT 0.00,
    `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `closed_at` TIMESTAMP NULL DEFAULT NULL,
    `status` ENUM('open', 'closed') DEFAULT 'open',
    `notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_opened` (`opened_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DATOS INICIALES
-- ============================================

-- Categorías por defecto
INSERT INTO `categories` (`name`, `color`, `icon`, `sort_order`) VALUES
('Bebidas', '#3b82f6', 'fa-wine-bottle', 1),
('Alimentos', '#10b981', 'fa-utensils', 2),
('Electrónica', '#8b5cf6', 'fa-laptop', 3),
('Ropa', '#ec4899', 'fa-tshirt', 4),
('Hogar', '#f59e0b', 'fa-home', 5),
('Limpieza', '#06b6d4', 'fa-broom', 6),
('Otros', '#64748b', 'fa-box', 99);

-- Métodos de pago por defecto
INSERT INTO `payment_methods` (`name`, `icon`, `sort_order`) VALUES
('Efectivo', 'fa-money-bill-wave', 1),
('Tarjeta de Crédito', 'fa-credit-card', 2),
('Tarjeta de Débito', 'fa-credit-card', 3),
('Transferencia', 'fa-university', 4),
('Crédito', 'fa-hand-holding-usd', 5);

-- Configuración inicial
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `is_public`) VALUES
('business_name', 'Mi Negocio', 'general', 1),
('business_address', '', 'general', 1),
('business_phone', '', 'general', 1),
('business_email', '', 'general', 1),
('tax_rate', '16', 'tax', 1),
('tax_name', 'IVA', 'tax', 1),
('currency', 'MXN', 'general', 1),
('currency_symbol', '$', 'general', 1),
('receipt_header', '', 'receipt', 1),
('receipt_footer', 'Gracias por su compra!', 'receipt', 1),
('receipt_show_logo', '1', 'receipt', 0),
('ticket_prefix', 'TKT', 'general', 0),
('low_stock_alert', '5', 'inventory', 0),
('negative_stock', '0', 'inventory', 0),
('require_customer', '0', 'sales', 0),
('max_discount_percent', '50', 'sales', 0);

-- Usuario administrador por defecto
-- Contraseña: admin123 (cambiar en producción)
INSERT INTO `users` (`email`, `password`, `name`, `role`, `is_active`) VALUES
('admin@elevenpropos.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin', 1);

-- Productos de ejemplo
INSERT INTO `products` (`sku`, `name`, `description`, `category_id`, `price`, `cost`, `stock`, `min_stock`, `barcode`, `unit`) VALUES
('BEV001', 'Coca Cola 600ml', 'Refresco de cola 600ml', 1, 18.00, 12.00, 50, 10, '7501055301047', 'pieza'),
('BEV002', 'Agua Natural 1L', 'Agua embotellada 1 litro', 1, 12.50, 8.00, 40, 10, '7501055301054', 'pieza'),
('ALI001', 'Sabritas Saladas 45g', 'Papas fritas saladas', 2, 15.00, 10.00, 30, 5, '7501011105012', 'pieza'),
('ELE001', 'Cargador USB-C', 'Cargador rápido USB-C 20W', 3, 129.00, 80.00, 20, 5, '7501011105029', 'pieza'),
('ELE002', 'Audífonos Bluetooth', 'Audífonos inalámbricos', 3, 299.00, 180.00, 15, 3, '7501011105036', 'pieza'),
('HOG001', 'Detergente 1kg', 'Detergente en polvo 1kg', 5, 45.00, 30.00, 25, 5, '7501011105043', 'pieza');

-- Cliente de ejemplo (ventas al público)
INSERT INTO `customers` (`name`, `email`, `phone`, `loyalty_points`) VALUES
('Venta al Público', NULL, NULL, 0),
('Juan Pérez', 'juan@email.com', '5551234567', 100),
('María García', 'maria@email.com', '5559876543', 50);
