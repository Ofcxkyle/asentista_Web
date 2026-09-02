-- ==============================================================================
-- ASENTISTA BAKERY - COMPLETE DATABASE MIGRATION & SEED SCRIPT
-- Compatible with MySQL / MariaDB (XAMPP phpMyAdmin / MySQL Workbench / CLI)
-- ==============================================================================

-- 1. Create Database if it does not exist
CREATE DATABASE IF NOT EXISTS `asentista_bakery_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `asentista_bakery_db`;

-- 2. Drop existing tables in reverse order of foreign keys
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- 3. Create Users Table
-- ==============================================================================
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(30) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'customer') DEFAULT 'customer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 4. Create Products Catalog Table
-- ==============================================================================
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) NOT NULL, -- 'Bread', 'Beverage', 'Organic Special'
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `description` TEXT NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `is_featured` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 5. Create Cart Items Table (Shopping Cart Persistence)
-- ==============================================================================
CREATE TABLE `cart_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `session_id` VARCHAR(100) NOT NULL,
  `product_name` VARCHAR(100) NOT NULL,
  `product_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `product_image` VARCHAR(255) DEFAULT 'assets/breads-e1656042972619.jpg',
  `quantity` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 6. Create Orders & Bookings Table
-- ==============================================================================
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(30) NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `item_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 1,
  `order_type` VARCHAR(50) NOT NULL DEFAULT 'In-Store Pickup', -- 'In-Store Pickup', 'Dine-in Table Booking', 'Direct Delivery'
  `reservation_date` DATE NOT NULL,
  `special_notes` TEXT DEFAULT NULL,
  `status` ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 7. Insert Default Seed Data
-- ==============================================================================

-- Default Users:
-- Admin User: admin@asentista.com / admin123
-- Customer User: customer@asentista.com / password123
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`) VALUES
(1, 'Kyle Asentista (Admin)', 'admin@asentista.com', '0994 005 8425', '$2y$10$v7g0l1eCskvVn6WnEcwv2.b2o65sBfqDfgZlqK5K62x4Y2aH2oVge', 'admin'),
(2, 'Maria Santos', 'customer@asentista.com', '0912 345 6789', '$2y$10$v7g0l1eCskvVn6WnEcwv2.b2o65sBfqDfgZlqK5K62x4Y2aH2oVge', 'customer');

-- Default Bakery Products
INSERT INTO `products` (`id`, `name`, `category`, `price`, `description`, `image`, `is_featured`) VALUES
(1, 'Crunchy Crust', 'Bread', 35.00, 'Golden-baked crust with an airy, soft interior. Perfect for morning dips or artisan sandwiches.', 'assets/bread-with-appetizing-crunchy-crust-top-view-isolated-on-white-e1656042939392.jpg', 1),
(2, 'Crescent Roll', 'Bread', 30.00, 'Buttery, flaky crescent roll sprinkled with aromatic toasted poppy seeds.', 'assets/top-view-of-crescent-roll-with-poppy-seeds-on-white-background-e1656042946947.jpg', 1),
(3, 'Round Rye', 'Bread', 45.00, 'Traditional European sourdough rye loaf with rich earthy undertones and dense crumb.', 'assets/traditional-round-rye-bread-e1656042958429.jpg', 1),
(4, 'Yeast Custard', 'Bread', 40.00, 'Sweet yeast bun filled with silky vanilla custard and spiced caramelized apple.', 'assets/yeast-bun-with-apple-and-custard-filling-e1656042965940.jpg', 1),
(5, 'Bially Sandwich', 'Bread', 50.00, 'Classic bialy bread roll baked with savory roasted onion and savory seeds.', 'assets/breads-e1656042972619.jpg', 1),
(6, 'Bun Messes', 'Bread', 28.00, 'Tender, pillowy brioche bun dusted with powdered sugar and natural sweetness.', 'assets/bun-e1656042983426.jpg', 1),
(7, 'Slice Bread', 'Bread', 60.00, 'Daily sliced sandwich rye loaf made from whole grains and natural levain.', 'assets/rye-bread-slice-on-a-white-background--e1656042993568.jpg', 1),
(8, 'Bun Roll', 'Bread', 25.00, 'Soft dinner roll with a golden finish, perfect with butter or jam.', 'assets/bun-1-e1656043014357.jpg', 1),
(9, 'Baguette', 'Bread', 25.00, 'Classic French crusty artisan baguette baked fresh every morning.', 'assets/bread-e1656042861839-pqroqtezjh2g0607d0pphz5ddrx6ppa7b44no9oloo.jpg', 0),
(10, 'Croissant', 'Bread', 25.00, 'Laminated, all-butter flaky French pastry with golden honeycomb layers.', 'assets/top-view-of-crescent-roll-with-poppy-seeds-on-white-background-e1656042946947.jpg', 0),
(11, 'Sourdough', 'Bread', 25.00, 'Slow-fermented artisan sourdough loaf made with naturally cultured levain.', 'assets/assortment-of-artisan-bread-e1656042887278.jpg', 0),
(12, 'Ciabatta', 'Bread', 25.00, 'Italian style white bread baked with virgin olive oil and fresh rosemary.', 'assets/italian-ciabatta-bread-on-black-slate-with-herbs-and-olives--e1656043199744 (1).jpg', 0),
(13, 'Brioche', 'Bread', 25.00, 'Rich golden bread enriched with egg yolk and grass-fed butter.', 'assets/homemade-pumpkin-bread-e1656042901513.jpg', 0),
(14, 'Americano', 'Beverage', 55.00, 'Rich double espresso diluted with hot mountain spring water.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).jpg', 0),
(15, 'Cold Brew', 'Beverage', 55.00, 'Smooth, 18-hour cold steeped single-origin Arabica coffee.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).jpg', 0),
(16, 'Carbonated Drink', 'Beverage', 35.00, 'Refreshing chilled sparkling fruit infusion.', 'assets/cheese-platter-with-nuts-honey-and-bread-square-crop-e1656043218344 (1).jpg', 0),
(17, 'Cortado', 'Beverage', 69.00, 'Equal parts rich espresso and warm textured whole milk.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).jpg', 0),
(18, 'Macchiato', 'Beverage', 69.00, 'Fresh espresso stained with a dollop of velvety foamed milk.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).jpg', 0);

-- Default Sample Orders
INSERT INTO `orders` (`id`, `user_id`, `customer_name`, `customer_phone`, `item_name`, `item_price`, `quantity`, `order_type`, `reservation_date`, `special_notes`, `status`) VALUES
(1, 2, 'Maria Santos', '0912 345 6789', 'Crunchy Crust (x2), Cold Brew (x1)', 125.00, 3, 'In-Store Pickup', CURDATE(), 'Please slice the crunchy crust bread for sandwiches.', 'Confirmed'),
(2, NULL, 'Juan Dela Cruz', '0998 765 4321', 'Sourdough (x1)', 25.00, 1, 'Direct Delivery', CURDATE(), 'Deliver around 10:00 AM at Sibulan plaza.', 'Pending'),
(3, 1, 'Kyle Asentista', '0994 005 8425', 'Yeast Custard (x1), Cortado (x2)', 178.00, 3, 'Dine-in Table Booking', CURDATE(), 'Reserve table by the window for 2 guests.', 'Completed');
