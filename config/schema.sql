-- Create Database
CREATE DATABASE IF NOT EXISTS `inventory_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `inventory_db`;

-- Create Items Table
CREATE TABLE IF NOT EXISTS `items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `sku` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `quantity` INT NOT NULL DEFAULT 0,
    `category` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_name` (`name`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Seed Data
INSERT INTO `items` (`name`, `sku`, `description`, `price`, `quantity`, `category`) VALUES
('Wireless Ergonomic Mouse', 'MS-ERG-01', 'A comfortable vertical wireless mouse designed for long hours of office work.', 49.99, 120, 'Electronics'),
('Mechanical Keyboard', 'KB-MECH-02', 'RGB Backlit mechanical keyboard with brown tactile switches.', 89.95, 75, 'Electronics'),
('Office Ergonomic Chair', 'CH-ERG-03', 'High-back mesh office chair with lumbar support and adjustable armrests.', 189.00, 30, 'Furniture'),
('Standing Desk Converter', 'DK-STND-04', 'Dual monitor height adjustable sit-to-stand converter.', 149.50, 45, 'Furniture'),
('Stainless Steel Water Bottle', 'BT-SST-05', 'Double-wall vacuum insulated water bottle (32oz), keeps drinks cold for 24h.', 24.99, 250, 'Kitchenware'),
('USB-C Hub Multiport Adapter', 'HB-USBC-06', '7-in-1 USB-C hub with 4K HDMI, 3 USB 3.0 ports, SD/TF card reader, and 100W PD.', 34.99, 150, 'Electronics'),
('Noise Cancelling Headphones', 'HP-ANC-07', 'Over-ear active noise cancelling bluetooth headphones with 40h playtime.', 129.99, 60, 'Electronics'),
('Leather Portfolio Notebook', 'NB-LTH-08', 'Premium refillable A5 leather journal for writing and drawing.', 19.99, 300, 'Office Supplies'),
('Smart LED Desk Lamp', 'LP-LED-09', 'Dimmable eye-caring desk light with USB charging port and 5 color modes.', 39.99, 90, 'Furniture'),
('Bluetooth Trackball Mouse', 'MS-TRK-10', 'Ergonomic trackball mouse with adjustable DPI and rechargeable battery.', 59.99, 50, 'Electronics');

-- Create Users Table for Authentication
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NULL,
    `token_expires_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

