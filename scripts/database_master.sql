-- ============================================
-- BASE DE DATOS MAESTRA - ELEVENPRO POS
-- URL: https://elevenpropos.com
-- ============================================

-- Crear base de datos maestra
CREATE DATABASE IF NOT EXISTS `u108221933_elevenpro` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `u108221933_elevenpro`;

-- ============================================
-- TABLA: TENANTS (Negocios registrados)
-- ============================================
CREATE TABLE IF NOT EXISTS `tenants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `db_name` VARCHAR(100) NOT NULL UNIQUE,
    `admin_email` VARCHAR(255) NOT NULL UNIQUE,
    `admin_password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `logo_url` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `plan` ENUM('free', 'basic', 'premium', 'enterprise') DEFAULT 'free',
    `max_users` INT DEFAULT 5,
    `max_products` INT DEFAULT 100,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_plan` (`plan`),
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: SUBSCRIPTIONS (Suscripciones)
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `plan` ENUM('free', 'basic', 'premium', 'enterprise') DEFAULT 'free',
    `price` DECIMAL(10,2) DEFAULT 0.00,
    `billing_cycle` ENUM('monthly', 'yearly') DEFAULT 'monthly',
    `status` ENUM('active', 'cancelled', 'expired', 'pending') DEFAULT 'pending',
    `starts_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `payment_reference` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: ACTIVITY_LOG (Registro de actividad)
-- ============================================
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_tenant_action` (`tenant_id`, `action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: SYSTEM_SETTINGS (Configuración del sistema)
-- ============================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERTAR CONFIGURACIÓN INICIAL
-- ============================================
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('app_name', 'ElevenPro POS', 'Nombre de la aplicación'),
('app_version', '1.0.0', 'Versión del sistema'),
('default_plan', 'free', 'Plan por defecto para nuevos tenants'),
('free_trial_days', '14', 'Días de prueba gratuita'),
('max_file_size', '5242880', 'Tamaño máximo de archivos en bytes'),
('allowed_image_types', 'jpg,jpeg,png,webp', 'Tipos de imagen permitidos'),
('receipt_footer', 'Gracias por su compra!', 'Pie de página en recibos'),
('tax_rate', '16', 'Porcentaje de impuesto por defecto'),
('currency', 'MXN', 'Moneda por defecto'),
('currency_symbol', '$', 'Símbolo de moneda');

-- ============================================
-- CREAR USUARIO ADMINISTRADOR PRINCIPAL
-- ============================================
-- Nota: La contraseña debe ser hasheada con password_hash() en PHP
-- Ejemplo: password_hash('Admin123!', PASSWORD_BCRYPT)
INSERT INTO `tenants` (
    `business_name`, 
    `slug`, 
    `db_name`, 
    `admin_email`, 
    `admin_password`, 
    `status`, 
    `plan`, 
    `max_users`, 
    `max_products`
) VALUES (
    'ElevenPro Demo',
    'elevenpro-demo',
    'u108221933_elevenpro_tenant_1',
    'admin@elevenpropos.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'active',
    'premium',
    10,
    1000
);

-- Crear suscripción para el tenant demo
INSERT INTO `subscriptions` (
    `tenant_id`, 
    `plan`, 
    `price`, 
    `billing_cycle`, 
    `status`, 
    `expires_at`
) VALUES (
    1,
    'premium',
    299.00,
    'monthly',
    'active',
    DATE_ADD(NOW(), INTERVAL 1 YEAR)
);
