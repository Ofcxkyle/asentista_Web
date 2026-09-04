<?php
/**
 * Asentista Bakery - Database Configuration & PDO Initialization
 * Pure PHP implementation adhering to Week 7 Database CRUD standards.
 */

// Start session if not already started with hardened security flags
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$host    = getenv('DB_HOST') ?: 'localhost';
$dbName  = getenv('DB_NAME') ?: 'asentista_bakery_db';
$user    = getenv('DB_USER') ?: 'root';
$pass    = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // 1. Initial connection to MySQL server
    $pdo = new PDO($dsn, $user, $pass, $options);

    // 2. Ensure database exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET {$charset} COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");

    // 3. Ensure essential tables exist (Auto-Migration fallback)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) NOT NULL UNIQUE,
            `phone` VARCHAR(30) DEFAULT NULL,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'customer') DEFAULT 'customer',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `products` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `category` VARCHAR(50) NOT NULL,
            `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `stock` INT NOT NULL DEFAULT 15,
            `description` TEXT NOT NULL,
            `image` VARCHAR(255) NOT NULL,
            `is_featured` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `cart_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT DEFAULT NULL,
            `session_id` VARCHAR(100) NOT NULL,
            `product_id` INT DEFAULT NULL,
            `product_name` VARCHAR(100) NOT NULL,
            `product_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `product_image` VARCHAR(255) DEFAULT 'assets/breads-e1656042972619.png',
            `quantity` INT NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT DEFAULT NULL,
            `customer_name` VARCHAR(100) NOT NULL,
            `customer_phone` VARCHAR(30) NOT NULL,
            `item_name` VARCHAR(255) NOT NULL,
            `item_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `quantity` INT NOT NULL DEFAULT 1,
            `order_type` VARCHAR(50) NOT NULL DEFAULT 'In-Store Pickup',
            `reservation_date` DATE NOT NULL,
            `special_notes` TEXT DEFAULT NULL,
            `status` ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `login_throttles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `identifier` VARCHAR(128) NOT NULL,
            `attempts` INT NOT NULL DEFAULT 1,
            `first_attempt` INT NOT NULL,
            `lockout_until` INT NOT NULL DEFAULT 0,
            INDEX `idx_throttle_lookup` (`identifier`, `lockout_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Seamlessly add quantity column to orders if missing
    try {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `quantity` INT NOT NULL DEFAULT 1 AFTER `item_price`");
    } catch (Exception $e) {}

    // Seamlessly add stock column to products if missing
    try {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `stock` INT NOT NULL DEFAULT 15 AFTER `price`");
    } catch (Exception $e) {}

    // Seamlessly add is_active column to products if missing (1 = visible in store, 0 = removed from user pages)
    try {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_featured`");
    } catch (Exception $e) {}

    // Seamlessly add product_id column to cart_items if missing
    try {
        $pdo->exec("ALTER TABLE `cart_items` ADD COLUMN `product_id` INT DEFAULT NULL AFTER `session_id`");
    } catch (Exception $e) {}

    // Add Performance Indexes
    try {
        $pdo->exec("ALTER TABLE `cart_items` ADD INDEX `idx_cart_session` (`session_id`)");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `cart_items` ADD INDEX `idx_cart_product` (`product_id`)");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `orders` ADD INDEX `idx_orders_status_date` (`status`, `created_at`)");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `orders` ADD INDEX `idx_orders_user` (`user_id`)");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `products` ADD INDEX `idx_products_cat_feat` (`category`, `is_featured`)");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `products` ADD INDEX `idx_products_stock` (`stock`)");
    } catch (Exception $e) {}

    // Seed default admin and sample data if users table is empty
    $checkUser = $pdo->query("SELECT COUNT(*) as count FROM `users`")->fetch();
    if ($checkUser['count'] == 0) {
        $defaultHash = password_hash('Admin@Asentista2026!', PASSWORD_DEFAULT);
        $customerHash = password_hash('Customer#Asentista2026!', PASSWORD_DEFAULT);

        $seedUser = $pdo->prepare("INSERT INTO `users` (`name`, `email`, `phone`, `password`, `role`) VALUES (?, ?, ?, ?, ?)");
        $seedUser->execute(['Kyle Asentista (Admin)', 'admin@asentista.com', '0994 005 8425', $defaultHash, 'admin']);
        $seedUser->execute(['Maria Santos', 'customer@asentista.com', '0912 345 6789', $customerHash, 'customer']);

        // Seed products with default stock
        $seedProd = $pdo->prepare("INSERT INTO `products` (`name`, `category`, `price`, `stock`, `description`, `image`, `is_featured`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $productsSeed = [
            ['Crunchy Crust', 'Bread', 35.00, 18, 'Golden-baked crust with an airy, soft interior. Perfect for morning dips or artisan sandwiches.', 'assets/bread-with-appetizing-crunchy-crust-top-view-isolated-on-white-e1656042939392.png', 1],
            ['Crescent Roll', 'Bread', 30.00, 20, 'Buttery, flaky crescent roll sprinkled with aromatic toasted poppy seeds.', 'assets/top-view-of-crescent-roll-with-poppy-seeds-on-white-background-e1656042946947.png', 1],
            ['Round Rye', 'Bread', 45.00, 12, 'Traditional European sourdough rye loaf with rich earthy undertones and dense crumb.', 'assets/traditional-round-rye-bread-e1656042958429.png', 1],
            ['Yeast Custard', 'Bread', 40.00, 15, 'Sweet yeast bun filled with silky vanilla custard and spiced caramelized apple.', 'assets/yeast-bun-with-apple-and-custard-filling-e1656042965940.png', 1],
            ['Bially Sandwich', 'Bread', 50.00, 14, 'Classic bialy bread roll baked with savory roasted onion and savory seeds.', 'assets/breads-e1656042972619.png', 1],
            ['Bun Messes', 'Bread', 28.00, 25, 'Tender, pillowy brioche bun dusted with powdered sugar and natural sweetness.', 'assets/bun-e1656042983426.png', 1],
            ['Slice Bread', 'Bread', 60.00, 10, 'Daily sliced sandwich rye loaf made from whole grains and natural levain.', 'assets/rye-bread-slice-on-a-white-background--e1656042993568.png', 1],
            ['Bun Roll', 'Bread', 25.00, 30, 'Soft dinner roll with a golden finish, perfect with butter or jam.', 'assets/bun-1-e1656043014357.png', 1],
            ['Baguette', 'Bread', 25.00, 15, 'Classic French crusty artisan baguette baked fresh every morning.', 'assets/bread-e1656042861839-pqroqtezjh2g0607d0pphz5ddrx6ppa7b44no9oloo.png', 0],
            ['Croissant', 'Bread', 25.00, 16, 'Laminated, all-butter flaky French pastry with golden honeycomb layers.', 'assets/top-view-of-crescent-roll-with-poppy-seeds-on-white-background-e1656042946947.png', 0],
            ['Sourdough', 'Bread', 25.00, 12, 'Slow-fermented artisan sourdough loaf made with naturally cultured levain.', 'assets/assortment-of-artisan-bread-e1656042887278.png', 0],
            ['Ciabatta', 'Bread', 25.00, 14, 'Italian style white bread baked with virgin olive oil and fresh rosemary.', 'assets/italian-ciabatta-bread-on-black-slate-with-herbs-and-olives--e1656043199744 (1).png', 0],
            ['Brioche', 'Bread', 25.00, 18, 'Rich golden bread enriched with egg yolk and grass-fed butter.', 'assets/homemade-pumpkin-bread-e1656042901513.png', 0],
            ['Americano', 'Beverage', 55.00, 50, 'Rich double espresso diluted with hot mountain spring water.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png', 0],
            ['Cold Brew', 'Beverage', 55.00, 40, 'Smooth, 18-hour cold steeped single-origin Arabica coffee.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png', 0],
            ['Carbonated Drink', 'Beverage', 35.00, 45, 'Refreshing chilled sparkling fruit infusion.', 'assets/cheese-platter-with-nuts-honey-and-bread-square-crop-e1656043218344 (1).png', 0],
            ['Cortado', 'Beverage', 69.00, 35, 'Equal parts rich espresso and warm textured whole milk.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png', 0],
            ['Macchiato', 'Beverage', 69.00, 35, 'Fresh espresso stained with a dollop of velvety foamed milk.', 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png', 0]
        ];
        foreach ($productsSeed as $p) {
            $seedProd->execute($p);
        }

        // Seed sample order
        $pdo->exec("INSERT INTO `orders` (`user_id`, `customer_name`, `customer_phone`, `item_name`, `item_price`, `quantity`, `order_type`, `reservation_date`, `special_notes`, `status`) 
                    VALUES (2, 'Maria Santos', '0912 345 6789', 'Crunchy Crust', 35.00, 1, 'In-Store Pickup', CURDATE(), 'Please slice for sandwiches', 'Confirmed')");
    }

} catch (PDOException $e) {
    error_log("Secure Database Error: " . $e->getMessage());
    die("<!DOCTYPE html><html><head><title>System Notice</title><style>body{font-family:sans-serif;background:#2B1B15;color:#FFF8F0;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}.box{background:rgba(255,255,255,0.06);padding:2rem 3rem;border-radius:8px;text-align:center;max-width:480px;border:1px solid rgba(255,255,255,0.15);}h1{color:#EBB22F;font-size:1.5rem;margin-bottom:0.75rem;}p{color:#D0C4B8;line-height:1.6;font-size:0.95rem;}</style></head><body><div class='box'><h1>Bakery System Notice</h1><p>Our database service is temporarily undergoing scheduled maintenance or initialization. Please refresh in a moment or contact our support team.</p></div></body></html>");
}

