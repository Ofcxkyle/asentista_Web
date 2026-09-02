<?php
/**
 * Asentista Bakery - Database Helper Functions, Auth & Cart CRUD Operations
 * Pure PHP implementation adhering to PDO Prepared Statements & Security standards.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/validation.php';

// ==============================================================================
// USER AUTHENTICATION FUNCTIONS
// ==============================================================================

/**
 * Register a new user in the database with secure password hashing.
 */
function registerUser(PDO $pdo, $name, $email, $phone, $password, $role = 'customer') {
    $errors = [];
    
    validate_required($name, 'Full Name', $errors);
    validate_length($name, 'Full Name', 2, 100, $errors);
    validate_email($email, $errors);
    if (!empty($phone)) {
        validate_phone($phone, $errors);
    }
    validate_required($password, 'Password', $errors);
    validate_length($password, 'Password', 6, 255, $errors);

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    $stmt = $pdo->prepare("SELECT id FROM `users` WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'An account with this email address already exists.'];
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $insertSql = "INSERT INTO `users` (name, email, phone, password, role) VALUES (:name, :email, :phone, :password, :role)";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->bindValue(':name', $name, PDO::PARAM_STR);
    $insertStmt->bindValue(':email', $email, PDO::PARAM_STR);
    $insertStmt->bindValue(':phone', $phone, PDO::PARAM_STR);
    $insertStmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
    $insertStmt->bindValue(':role', $role, PDO::PARAM_STR);
    
    try {
        $insertStmt->execute();
        $userId = (int)$pdo->lastInsertId();
        
        // Migrate guest cart to newly registered user
        if (session_id()) {
            $updateCart = $pdo->prepare("UPDATE `cart_items` SET user_id = :user_id WHERE session_id = :session_id AND user_id IS NULL");
            $updateCart->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $updateCart->bindValue(':session_id', session_id(), PDO::PARAM_STR);
            $updateCart->execute();
        }

        $_SESSION['user_id']    = $userId;
        $_SESSION['user_name']  = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_phone'] = $phone;
        $_SESSION['user_role']  = $role;
        unset($_SESSION['guest_mode']);

        return ['success' => true, 'message' => 'Account registered successfully!', 'user_id' => $userId];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Registration error: ' . $e->getMessage()];
    }
}

/**
 * Authenticate user credentials and establish session.
 */
function loginUser(PDO $pdo, $email, $password) {
    $errors = [];
    validate_email($email, $errors);
    validate_required($password, 'Password', $errors);

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    $stmt = $pdo->prepare("SELECT id, name, email, phone, password, role FROM `users` WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email address or password.'];
    }

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_phone'] = $user['phone'];
    $_SESSION['user_role']  = $user['role'];
    unset($_SESSION['guest_mode']);

    // Link guest cart items to this logged-in account
    if (session_id()) {
        $updateCart = $pdo->prepare("UPDATE `cart_items` SET user_id = :user_id WHERE session_id = :session_id AND user_id IS NULL");
        $updateCart->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
        $updateCart->bindValue(':session_id', session_id(), PDO::PARAM_STR);
        $updateCart->execute();
    }

    return ['success' => true, 'message' => 'Welcome back, ' . $user['name'] . '!', 'user' => $user];
}

/**
 * Check if user is logged in.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if logged in user is admin.
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Get current user.
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? 'Guest',
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => $_SESSION['user_phone'] ?? '',
        'role'  => $_SESSION['user_role'] ?? 'customer'
    ];
}

/**
 * Log out user and destroy session.
 */
function logoutUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// ==============================================================================
// SHOPPING CART FUNCTIONS (Database-Backed)
// ==============================================================================

function getEffectiveSessionId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return session_id();
}

/**
 * Add a bakery item to the shopping cart.
 */
function addToCart(PDO $pdo, $productName, $price = 0, $image = '', $qty = 1) {
    $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    $sessionId = getEffectiveSessionId();
    $qty = max(1, (int)$qty);

    // Auto-fetch price & image if omitted
    if ($price <= 0 || empty($image)) {
        $stmt = $pdo->prepare("SELECT price, image FROM `products` WHERE name = :name LIMIT 1");
        $stmt->bindValue(':name', $productName, PDO::PARAM_STR);
        $stmt->execute();
        $prod = $stmt->fetch();
        if ($prod) {
            if ($price <= 0) $price = (float)$prod['price'];
            if (empty($image)) $image = $prod['image'];
        }
    }

    if (empty($image)) {
        $image = 'assets/breads-e1656042972619.jpg';
    }

    // Check if this item is already in the user's/session's cart
    if ($userId) {
        $check = $pdo->prepare("SELECT id, quantity FROM `cart_items` WHERE user_id = :user_id AND product_name = :pname LIMIT 1");
        $check->bindValue(':user_id', $userId, PDO::PARAM_INT);
    } else {
        $check = $pdo->prepare("SELECT id, quantity FROM `cart_items` WHERE session_id = :session_id AND user_id IS NULL AND product_name = :pname LIMIT 1");
        $check->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
    }
    $check->bindValue(':pname', $productName, PDO::PARAM_STR);
    $check->execute();
    $existing = $check->fetch();

    if ($existing) {
        $newQty = $existing['quantity'] + $qty;
        $update = $pdo->prepare("UPDATE `cart_items` SET quantity = :qty, product_price = :price WHERE id = :id");
        $update->bindValue(':qty', $newQty, PDO::PARAM_INT);
        $update->bindValue(':price', $price);
        $update->bindValue(':id', $existing['id'], PDO::PARAM_INT);
        $update->execute();
        $cartId = $existing['id'];
    } else {
        $insert = $pdo->prepare("INSERT INTO `cart_items` (user_id, session_id, product_name, product_price, product_image, quantity) VALUES (:uid, :sid, :pname, :price, :img, :qty)");
        $insert->bindValue(':uid', $userId, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insert->bindValue(':sid', $sessionId, PDO::PARAM_STR);
        $insert->bindValue(':pname', $productName, PDO::PARAM_STR);
        $insert->bindValue(':price', $price);
        $insert->bindValue(':img', $image, PDO::PARAM_STR);
        $insert->bindValue(':qty', $qty, PDO::PARAM_INT);
        $insert->execute();
        $cartId = (int)$pdo->lastInsertId();
    }

    $summary = getCartSummary($pdo);
    return [
        'success'    => true,
        'message'    => "Added <strong>{$productName}</strong> (x{$qty}) to your cart!",
        'cart_id'    => $cartId,
        'cart_count' => $summary['total_items'],
        'cart_total' => $summary['total_price']
    ];
}

/**
 * Retrieve all items in the active shopping cart.
 */
function getCartItems(PDO $pdo) {
    $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    $sessionId = getEffectiveSessionId();

    if ($userId) {
        $stmt = $pdo->prepare("SELECT * FROM `cart_items` WHERE user_id = :uid ORDER BY created_at DESC");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `cart_items` WHERE session_id = :sid AND user_id IS NULL ORDER BY created_at DESC");
        $stmt->bindValue(':sid', $sessionId, PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Retrieve summary calculation (total items count, subtotal sum).
 */
function getCartSummary(PDO $pdo) {
    $items = getCartItems($pdo);
    $totalItems = 0;
    $totalPrice = 0.0;

    foreach ($items as $item) {
        $qty = (int)$item['quantity'];
        $price = (float)$item['product_price'];
        $totalItems += $qty;
        $totalPrice += ($qty * $price);
    }

    return [
        'items'       => $items,
        'total_items' => $totalItems,
        'total_price' => $totalPrice,
        'total_formatted' => '₱' . number_format($totalPrice, 2)
    ];
}

/**
 * Update quantity of a cart item.
 */
function updateCartQty(PDO $pdo, $cartId, $qty) {
    $qty = (int)$qty;
    if ($qty <= 0) {
        return removeFromCart($pdo, $cartId);
    }
    $stmt = $pdo->prepare("UPDATE `cart_items` SET quantity = :qty WHERE id = :id");
    $stmt->bindValue(':qty', $qty, PDO::PARAM_INT);
    $stmt->bindValue(':id', $cartId, PDO::PARAM_INT);
    $stmt->execute();
    return getCartSummary($pdo);
}

/**
 * Remove an item from the cart.
 */
function removeFromCart(PDO $pdo, $cartId) {
    $stmt = $pdo->prepare("DELETE FROM `cart_items` WHERE id = :id");
    $stmt->bindValue(':id', $cartId, PDO::PARAM_INT);
    $stmt->execute();
    return getCartSummary($pdo);
}

/**
 * Clear the entire cart.
 */
function clearCart(PDO $pdo) {
    $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    $sessionId = getEffectiveSessionId();

    if ($userId) {
        $stmt = $pdo->prepare("DELETE FROM `cart_items` WHERE user_id = :uid");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("DELETE FROM `cart_items` WHERE session_id = :sid AND user_id IS NULL");
        $stmt->bindValue(':sid', $sessionId, PDO::PARAM_STR);
    }
    $stmt->execute();
    return true;
}

/**
 * Checkout entire cart: creates an order in `orders` table and clears the cart.
 */
function checkoutCart(PDO $pdo, array $formData) {
    $errors = [];
    $summary = getCartSummary($pdo);

    if (empty($summary['items'])) {
        return ['success' => false, 'message' => 'Your shopping cart is empty. Please add bakery items first!'];
    }

    $customerName    = sanitize_input($formData['customer_name'] ?? '');
    $customerPhone   = sanitize_input($formData['customer_phone'] ?? '');
    $orderType       = sanitize_input($formData['order_type'] ?? 'In-Store Pickup');
    $reservationDate = sanitize_input($formData['reservation_date'] ?? '');
    $specialNotes    = sanitize_input($formData['special_notes'] ?? '');
    $userId          = isLoggedIn() ? (int)$_SESSION['user_id'] : null;

    validate_required($customerName, 'Customer Name', $errors);
    validate_phone($customerPhone, $errors);
    validate_required($reservationDate, 'Pickup / Reservation Date', $errors);

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    // Build bundled item name summary (e.g. "Crunchy Crust (x2), Cold Brew (x1)")
    $itemNames = [];
    foreach ($summary['items'] as $it) {
        $itemNames[] = "{$it['product_name']} (x{$it['quantity']})";
    }
    $bundledNames = implode(', ', $itemNames);
    $totalPrice = (float)$summary['total_price'];
    $totalQty = (int)$summary['total_items'];

    $sql = "INSERT INTO `orders` 
            (user_id, customer_name, customer_phone, item_name, item_price, quantity, order_type, reservation_date, special_notes, status) 
            VALUES (:user_id, :customer_name, :customer_phone, :item_name, :item_price, :quantity, :order_type, :reservation_date, :special_notes, 'Pending')";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':customer_name', $customerName, PDO::PARAM_STR);
    $stmt->bindValue(':customer_phone', $customerPhone, PDO::PARAM_STR);
    $stmt->bindValue(':item_name', $bundledNames, PDO::PARAM_STR);
    $stmt->bindValue(':item_price', $totalPrice);
    $stmt->bindValue(':quantity', $totalQty, PDO::PARAM_INT);
    $stmt->bindValue(':order_type', $orderType, PDO::PARAM_STR);
    $stmt->bindValue(':reservation_date', $reservationDate, PDO::PARAM_STR);
    $stmt->bindValue(':special_notes', $specialNotes, PDO::PARAM_STR);

    try {
        $stmt->execute();
        $orderId = (int)$pdo->lastInsertId();

        // Clear cart after successful checkout
        clearCart($pdo);

        return [
            'success'  => true,
            'message'  => "Thank you, {$customerName}! Your bakery order (#{$orderId}) for {$totalQty} item(s) has been placed successfully.",
            'order_id' => $orderId,
            'data'     => [
                'order_id'         => $orderId,
                'customer_name'    => $customerName,
                'customer_phone'   => $customerPhone,
                'item_name'        => $bundledNames,
                'item_price'       => $totalPrice,
                'quantity'         => $totalQty,
                'order_type'       => $orderType,
                'reservation_date' => $reservationDate,
                'special_notes'    => $specialNotes
            ]
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error placing cart order: ' . $e->getMessage()];
    }
}

// ==============================================================================
// ORDER & BOOKING CRUD FUNCTIONS
// ==============================================================================

/**
 * Create a direct single order or table reservation.
 */
function createOrder(PDO $pdo, array $data) {
    $errors = [];

    $customerName    = sanitize_input($data['customer_name'] ?? '');
    $customerPhone   = sanitize_input($data['customer_phone'] ?? '');
    $itemName        = sanitize_input($data['item_name'] ?? 'Custom Bakery Selection');
    $itemPrice       = isset($data['item_price']) ? (float)$data['item_price'] : 0.00;
    $quantity        = isset($data['quantity']) ? max(1, (int)$data['quantity']) : 1;
    $orderType       = sanitize_input($data['order_type'] ?? 'In-Store Pickup');
    $reservationDate = sanitize_input($data['reservation_date'] ?? '');
    $specialNotes    = sanitize_input($data['special_notes'] ?? '');
    $userId          = isset($data['user_id']) && !empty($data['user_id']) ? (int)$data['user_id'] : (isLoggedIn() ? $_SESSION['user_id'] : null);

    validate_required($customerName, 'Customer Name', $errors);
    validate_phone($customerPhone, $errors);
    validate_required($reservationDate, 'Reservation / Pickup Date', $errors);

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    if ($itemPrice <= 0 && !empty($itemName)) {
        $stmtPrice = $pdo->prepare("SELECT price FROM `products` WHERE name LIKE :name LIMIT 1");
        $stmtPrice->bindValue(':name', "%{$itemName}%", PDO::PARAM_STR);
        $stmtPrice->execute();
        $p = $stmtPrice->fetch();
        if ($p) {
            $itemPrice = (float)$p['price'] * $quantity;
        }
    }

    $sql = "INSERT INTO `orders` 
            (user_id, customer_name, customer_phone, item_name, item_price, quantity, order_type, reservation_date, special_notes, status) 
            VALUES (:user_id, :customer_name, :customer_phone, :item_name, :item_price, :quantity, :order_type, :reservation_date, :special_notes, 'Pending')";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':customer_name', $customerName, PDO::PARAM_STR);
    $stmt->bindValue(':customer_phone', $customerPhone, PDO::PARAM_STR);
    $stmt->bindValue(':item_name', $itemName, PDO::PARAM_STR);
    $stmt->bindValue(':item_price', $itemPrice);
    $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
    $stmt->bindValue(':order_type', $orderType, PDO::PARAM_STR);
    $stmt->bindValue(':reservation_date', $reservationDate, PDO::PARAM_STR);
    $stmt->bindValue(':special_notes', $specialNotes, PDO::PARAM_STR);

    try {
        $stmt->execute();
        $orderId = (int)$pdo->lastInsertId();
        return [
            'success'  => true,
            'message'  => "Thank you, {$customerName}! Your order (#{$orderId}) has been received and saved in our database.",
            'order_id' => $orderId,
            'data'     => [
                'order_id'         => $orderId,
                'customer_name'    => $customerName,
                'customer_phone'   => $customerPhone,
                'item_name'        => $itemName,
                'item_price'       => $itemPrice,
                'quantity'         => $quantity,
                'order_type'       => $orderType,
                'reservation_date' => $reservationDate
            ]
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error placing order: ' . $e->getMessage()];
    }
}

/**
 * Retrieve and filter orders for the dashboard with search keyword support.
 */
function searchOrders(PDO $pdo, $keyword = '', $statusFilter = null, $userId = null) {
    $sql = "SELECT * FROM `orders` WHERE 1=1";
    $params = [];

    if ($userId) {
        $sql .= " AND user_id = :uid";
        $params[':uid'] = $userId;
    }

    if (!empty($statusFilter)) {
        $sql .= " AND status = :status";
        $params[':status'] = $statusFilter;
    }

    if (!empty($keyword)) {
        $sql .= " AND (customer_name LIKE :kw OR customer_phone LIKE :kw OR item_name LIKE :kw OR id LIKE :kw)";
        $params[':kw'] = "%{$keyword}%";
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        if ($k === ':uid') {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function getAllOrders(PDO $pdo, $statusFilter = null) {
    return searchOrders($pdo, '', $statusFilter, null);
}

function getUserOrders(PDO $pdo, $userId) {
    return searchOrders($pdo, '', null, $userId);
}

function getOrderById(PDO $pdo, $orderId) {
    $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch();
}

function updateOrderStatus(PDO $pdo, $orderId, $status) {
    $allowed = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowed)) {
        return false;
    }
    $stmt = $pdo->prepare("UPDATE `orders` SET status = :status WHERE id = :id");
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
    return $stmt->execute();
}

function deleteOrder(PDO $pdo, $orderId) {
    $stmt = $pdo->prepare("DELETE FROM `orders` WHERE id = :id");
    $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
    return $stmt->execute();
}

// ==============================================================================
// PRODUCT CATALOG HELPERS
// ==============================================================================

function getAllProducts(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM `products` ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getFeaturedProducts(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM `products` WHERE is_featured = 1 ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getProductById(PDO $pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM `products` WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch();
}

function addProduct(PDO $pdo, $name, $category, $price, $desc, $image, $isFeatured = 1) {
    $name = sanitize_input($name);
    $category = sanitize_input($category);
    $price = (float)$price;
    $desc = sanitize_input($desc);
    $image = sanitize_input($image);
    $isFeatured = $isFeatured ? 1 : 0;

    if (empty($image)) {
        $image = 'assets/breads-e1656042972619.jpg';
    }

    $stmt = $pdo->prepare("INSERT INTO `products` (name, category, price, description, image, is_featured) VALUES (:name, :category, :price, :desc, :image, :is_featured)");
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':price', $price);
    $stmt->bindValue(':desc', $desc, PDO::PARAM_STR);
    $stmt->bindValue(':image', $image, PDO::PARAM_STR);
    $stmt->bindValue(':is_featured', $isFeatured, PDO::PARAM_INT);
    return $stmt->execute();
}

function updateProduct(PDO $pdo, $id, $name, $category, $price, $desc, $image, $isFeatured = 1) {
    $id = (int)$id;
    $name = sanitize_input($name);
    $category = sanitize_input($category);
    $price = (float)$price;
    $desc = sanitize_input($desc);
    $image = sanitize_input($image);
    $isFeatured = $isFeatured ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE `products` SET name = :name, category = :category, price = :price, description = :desc, image = :image, is_featured = :is_featured WHERE id = :id");
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':price', $price);
    $stmt->bindValue(':desc', $desc, PDO::PARAM_STR);
    $stmt->bindValue(':image', $image, PDO::PARAM_STR);
    $stmt->bindValue(':is_featured', $isFeatured, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}

function deleteProduct(PDO $pdo, $id) {
    $id = (int)$id;
    $stmt = $pdo->prepare("DELETE FROM `products` WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}

// ==============================================================================
// ADMIN CONSOLE METRICS & CUSTOMER REPORTING
// ==============================================================================

function getAdminMetrics(PDO $pdo) {
    $orders = getAllOrders($pdo);
    $totalOrders = count($orders);
    $totalRevenue = 0.0;
    $pendingCount = 0;
    $confirmedCount = 0;
    $completedCount = 0;
    $cancelledCount = 0;

    foreach ($orders as $o) {
        $price = (float)$o['item_price'];
        if ($o['status'] !== 'Cancelled') {
            $totalRevenue += $price;
        }
        if ($o['status'] === 'Pending') $pendingCount++;
        elseif ($o['status'] === 'Confirmed') $confirmedCount++;
        elseif ($o['status'] === 'Completed') $completedCount++;
        elseif ($o['status'] === 'Cancelled') $cancelledCount++;
    }

    $customerCount = (int)$pdo->query("SELECT COUNT(*) FROM `users` WHERE role = 'customer'")->fetchColumn();
    $productCount = (int)$pdo->query("SELECT COUNT(*) FROM `products`")->fetchColumn();

    return [
        'total_revenue'    => $totalRevenue,
        'revenue_formatted'=> '₱' . number_format($totalRevenue, 2),
        'total_orders'     => $totalOrders,
        'pending_orders'   => $pendingCount,
        'confirmed_orders' => $confirmedCount,
        'completed_orders' => $completedCount,
        'cancelled_orders' => $cancelledCount,
        'customer_count'   => $customerCount,
        'product_count'    => $productCount
    ];
}

function getAllCustomers(PDO $pdo) {
    $sql = "SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at,
                   COUNT(o.id) AS total_orders,
                   COALESF(SUM(CASE WHEN o.status != 'Cancelled' THEN o.item_price ELSE 0 END), 0) AS total_spent
            FROM `users` u
            LEFT JOIN `orders` o ON u.id = o.user_id
            WHERE u.role = 'customer'
            GROUP BY u.id
            ORDER BY u.created_at DESC";
    
    // SQLite/MySQL compatibility fallback if COALESF typo: use COALESCE
    $sql = str_replace('COALESF', 'COALESCE', $sql);
    return $pdo->query($sql)->fetchAll();
}
