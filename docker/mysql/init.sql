-- Asentista Bakery Normalized Relational Database Schema
-- All entities have explicit Foreign Keys, Cascades, and Indexes

CREATE DATABASE IF NOT EXISTS `asentista_bakery_db`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `asentista_bakery_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Categories Table
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `slug` VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Table (Symfony Compatible User entity)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(30) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `roles` JSON NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Products Table (Connected to Categories)
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock` INT NOT NULL DEFAULT 15,
  `description` TEXT NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `is_featured` TINYINT(1) DEFAULT 1,
  `created_by_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product_creator` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_products_cat_feat` (`category_id`, `is_featured`),
  INDEX `idx_products_stock` (`stock`),
  INDEX `idx_products_creator` (`created_by_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Cart Items Table (Connected to Users & Products)
CREATE TABLE `cart_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `session_id` VARCHAR(100) DEFAULT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_cart_session` (`session_id`),
  INDEX `idx_cart_user` (`user_id`),
  INDEX `idx_cart_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Orders Table (Connected to Users)
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(32) NOT NULL UNIQUE,
  `user_id` INT DEFAULT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_email` VARCHAR(150) NOT NULL,
  `customer_phone` VARCHAR(30) NOT NULL,
  `order_type` VARCHAR(50) NOT NULL DEFAULT 'In-Store Pickup',
  `reservation_date` DATE NOT NULL,
  `special_notes` TEXT DEFAULT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_orders_status_date` (`status`, `created_at`),
  INDEX `idx_orders_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Order Items Table (Connected to Orders & Products)
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT DEFAULT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_order_items_order` (`order_id`),
  INDEX `idx_order_items_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Password Resets Table (Connected to Users)
CREATE TABLE `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` INT NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_reset_token` (`token_hash`),
  INDEX `idx_reset_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- SEED DATA
-- ==========================================

-- Insert Categories
INSERT INTO `categories` (`id`, `name`, `slug`) VALUES
(1, 'Bread', 'bread'),
(2, 'Beverage', 'beverage');

-- Insert Users (Password hashes for: admin123 and password123)
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `roles`, `is_active`) VALUES
(1, 'Kyle Asentista (Admin)', 'admin@asentista.com', '09940058425', '$2y$10$bea5BvcP2OSI5VyEedLNFuNov0d.TJrce.lSDIXqtaKOAMkrmthqS', '[\"ROLE_ADMIN\"]', 1),
(2, 'Maria Santos', 'customer@asentista.com', '09123456789', '$2y$10$hs5cl0VIVShDi6r3CCTjRueyxbHD7.f9mnPmNwDl/yU6P9nhXtTdC', '[\"ROLE_CUSTOMER\"]', 1),
(3, 'Bakery Staff', 'staff@asentista.com', '09123456789', '$2y$10$hs5cl0VIVShDi6r3CCTjRueyxbHD7.f9mnPmNwDl/yU6P9nhXtTdC', '[\"ROLE_STAFF\"]', 1);

-- Insert Products with category_id relational mapping
INSERT INTO `products` (`id`, `category_id`, `name`, `price`, `stock`, `description`, `image`, `is_featured`) VALUES
(1, 1, 'Crunchy Crust', 35.00, 18, 'Golden-baked crust with an airy, soft interior. Perfect for morning dips or artisan sandwiches.', 'assets/bread-with-appetizing-crunchy-crust-top-view-isolated-on-white-e1656042939392.png', 1),
(2, 1, 'Crescent Roll', 30.00, 20, 'Buttery, flaky crescent roll sprinkled with aromatic toasted poppy seeds.', 'assets/top-view-of-crescent-roll-with-poppy-seeds-on-white-background-e1656042946947.png', 1),
(3, 1, 'Round Rye', 45.00, 12, 'Traditional European sourdough rye loaf with rich earthy undertones and dense crumb.', 'assets/traditional-round-rye-bread-e1656042958429.png', 1),
(4, 1, 'Yeast Custard', 40.00, 15, 'Sweet yeast bun filled with silky vanilla custard and spiced caramelized apple.', 'assets/yeast-bun-with-apple-and-custard-filling-e1656042965940.png', 1),
(5, 1, 'Bially Sandwich', 50.00, 14, 'Classic bialy bread roll baked with savory roasted onion and savory seeds.', 'assets/breads-e1656042972619.png', 1),
(6, 1, 'Bun Messes', 28.00, 25, 'Tender, pillowy brioche bun dusted with powdered sugar and natural sweetness.', 'assets/bun-e1656042983426.png', 1),
(7, 1, 'Slice Bread', 60.00, 10, 'Daily sliced sandwich rye loaf made from whole grains and natural levain.', 'assets/rye-bread-slice-on-a-white-background--e1656042993568.png', 1),
(8, 1, 'Bun Roll', 25.00, 30, 'Soft dinner roll with a golden finish, perfect with butter or jam.', 'assets/bun-1-e1656043014357.png', 1),
(9, 1, 'Baguette', 25.00, 15, 'Classic French crusty artisan baguette baked fresh every morning.', 'assets/bread-e1656042861839-pqroqtezjh2g0607d0pphz5ddrx6ppa7b44no9oloo.png', 0),
(10, 1, 'Croissant', 25.00, 16, 'Laminated, all-butter flaky French pastry with golden honeycomb layers.', 'assets/top-view-of-crescent-roll-with-poppy-seeds-on-white-background-e1656042946947.png', 0),
(11, 1, 'Sourdough', 25.00, 12, 'Slow-fermented artisan sourdough loaf made with naturally cultured levain.', 'assets/assortment-of-artisan-bread-e1656042887278.png', 0),
(12, 1, 'Ciabatta', 25.00, 14, 'Italian style white bread baked with virgin olive oil and fresh rosemary.', 'assets/italian-ciabatta-bread-on-black-slate-with-herbs-and-olives--e1656043199744 (1).png', 0),
(13, 1, 'Brioche', 25.00, 18, 'Rich golden bread enriched with egg yolk and grass-fed butter.', 'assets/homemade-pumpkin-bread-e1656042901513.png', 0),
(14, 2, 'Americano', 55.00, 50, 'Rich double espresso diluted with hot mountain spring water.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png', 0),
(15, 2, 'Cold Brew', 55.00, 40, 'Smooth, 18-hour cold steeped single-origin Arabica coffee.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png', 0),
(16, 2, 'Carbonated Drink', 35.00, 45, 'Refreshing chilled practical sparkling fruit infusion.', 'assets/cheese-platter-with-nuts-honey-and-bread-square-crop-e1656043218344 (1).png', 0),
(17, 2, 'Cortado', 69.00, 35, 'Equal parts rich espresso and warm textured whole milk.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png', 0),
(18, 2, 'Macchiato', 69.00, 35, 'Fresh espresso stained with a dollop of velvety foamed milk.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png', 0);

-- Insert Sample Orders
INSERT INTO `orders` (`id`, `order_number`, `user_id`, `customer_name`, `customer_email`, `customer_phone`, `order_type`, `reservation_date`, `special_notes`, `total_amount`, `status`) VALUES
(1, 'ORD-2026-0001', 2, 'Maria Santos', 'customer@asentista.com', '09123456789', 'In-Store Pickup', CURDATE(), 'Please slice the crunchy crust bread for sandwiches.', 125.00, 'Confirmed'),
(2, 'ORD-2026-0002', NULL, 'Juan Dela Cruz', 'juan@example.com', '09987654321', 'Direct Delivery', CURDATE(), 'Deliver around 10:00 AM at Sibulan plaza.', 25.00, 'Pending'),
(3, 'ORD-2026-0003', 1, 'Kyle Asentista', 'admin@asentista.com', '09940058425', 'Dine-in Table Booking', CURDATE(), 'Reserve table by the window for 2 guests.', 178.00, 'Completed');

-- Insert Normalized Order Items (Connecting orders to products)
INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `subtotal`) VALUES
(1, 1, 'Crunchy Crust', 35.00, 2, 70.00),
(1, 15, 'Cold Brew', 55.00, 1, 55.00),
(2, 11, 'Sourdough', 25.00, 1, 25.00),
(3, 4, 'Yeast Custard', 40.00, 1, 40.00),
(3, 17, 'Cortado', 69.00, 2, 138.00);
