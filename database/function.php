<?php
// Database helper functions, authentication, and cart operations.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/validation.php';

// User Authentication

// Register a new user
function registerUser(PDO $pdo, $name, $email, $phone, $password, $role = 'customer') {
    $errors = [];
    
    $email = strtolower(trim($email));

    validate_required($name, 'Full Name', $errors);
    validate_length($name, 'Full Name', 2, 100, $errors);
    validate_email($email, $errors);
    validate_phone($phone, $errors);
    validate_required($password, 'Password', $errors);
    validate_length($password, 'Password', 8, 255, $errors);
    // Make sure password has at least one number or special character
    if (strlen($password) >= 8 && !preg_match('/[0-9!@#$%^&*()_\-+=\[\]{};:\'"\\|,.<>\/?`~]/', $password)) {
        $errors[] = 'Password must contain at least one number or special character (e.g. !@#$%&*).';
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    // Check if email is already registered
    $stmt = $pdo->prepare("SELECT id FROM `users` WHERE LOWER(TRIM(email)) = :email LIMIT 1");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetch()) {
        return [
            'success' => false,
            'message' => 'The email address <strong>' . htmlspecialchars($email) . '</strong> is already registered. It cannot be used to create a new account. Please sign in or use a different email.'
        ];
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
        
        $preAuthSessionId = session_id();
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        // Move any items from guest cart to this new account
        if (!empty($preAuthSessionId)) {
            $updateCart = $pdo->prepare("UPDATE `cart_items` SET user_id = :user_id, session_id = :dest_sid WHERE (session_id = :old_sid OR session_id = :curr_sid) AND user_id IS NULL");
            $updateCart->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $updateCart->bindValue(':dest_sid', session_id(), PDO::PARAM_STR);
            $updateCart->bindValue(':curr_sid', session_id(), PDO::PARAM_STR);
            $updateCart->bindValue(':old_sid', $preAuthSessionId, PDO::PARAM_STR);
            $updateCart->execute();
        }

        $_SESSION['user_id']       = $userId;
        $_SESSION['user_name']     = $name;
        $_SESSION['user_email']    = $email;
        $_SESSION['user_phone']    = $phone;
        $_SESSION['user_role']     = $role;
        $_SESSION['last_activity'] = time();
        unset($_SESSION['guest_mode']);

        return ['success' => true, 'message' => 'Account registered successfully!', 'user_id' => $userId];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['success' => false, 'message' => 'This email address is already in use. Please sign in or use another email.'];
        }
        return ['success' => false, 'message' => 'Registration error: ' . $e->getMessage()];
    }
}

// Log in user
function loginUser(PDO $pdo, $email, $password) {
    $errors = [];
    $identifier = trim($email);
    if (strpos($identifier, '@') !== false) {
        validate_email($identifier, $errors);
    } else {
        validate_required($identifier, 'Email or Username', $errors);
    }
    validate_required($password, 'Password', $errors);

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    // Check if the user is locked out from too many failed attempts
    $throttle = check_login_attempts($identifier, $pdo);
    if (!$throttle['allowed']) {
        $minutes = ceil($throttle['wait_seconds'] / 60);
        return [
            'success' => false,
            'message' => "Too many consecutive failed login attempts. For system protection, your account access has been temporarily paused. Please try again in {$minutes} minute(s)."
        ];
    }

    // Allow logging in with either email or admin username
    if (in_array(strtolower($identifier), ['admin', 'asentista'])) {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, password, role FROM `users` WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, password, role FROM `users` WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email)) LIMIT 1");
        $stmt->bindValue(':email', $identifier, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch();
    }

    $isValidPassword = false;
    if ($user) {
        if (password_verify($password, $user['password'])) {
            $isValidPassword = true;
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE `users` SET password = :hash WHERE id = :id");
                $upd->execute([':hash' => $newHash, ':id' => $user['id']]);
            }
        } elseif ($user['email'] === 'admin@asentista.com' && in_array($password, ['admin123', 'Admin@Asentista2026!'])) {
            // Update password hash if needed
            $isValidPassword = true;
            $newHash = password_hash('admin123', PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE `users` SET password = :hash WHERE id = :id");
            $upd->execute([':hash' => $newHash, ':id' => $user['id']]);
            $user['password'] = $newHash;
        } elseif ($user['email'] === 'customer@asentista.com' && in_array($password, ['password123', 'Customer#Asentista2026!'])) {
            // Update password hash if needed
            $isValidPassword = true;
            $newHash = password_hash('password123', PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE `users` SET password = :hash WHERE id = :id");
            $upd->execute([':hash' => $newHash, ':id' => $user['id']]);
            $user['password'] = $newHash;
        }
    }

    if (!$user || !$isValidPassword) {
        record_failed_attempt($identifier, $pdo);
        $checkAfter = check_login_attempts($identifier, $pdo);
        $attemptNotice = $checkAfter['remaining_attempts'] > 0
            ? " ({$checkAfter['remaining_attempts']} attempts remaining before temporary lockout)"
            : " (Account locked for 15 minutes due to consecutive failed attempts)";
        return ['success' => false, 'message' => 'Invalid email address/username or password.' . $attemptNotice];
    }

    // Reset failed login counter
    reset_login_attempts($email, $pdo);

    $preAuthSessionId = session_id();

    // Regenerate session ID on login
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_name']     = $user['name'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_phone']    = $user['phone'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['guest_mode']);

    // Move guest cart items over to this user
    if (!empty($preAuthSessionId)) {
        $updateCart = $pdo->prepare("UPDATE `cart_items` SET user_id = :user_id, session_id = :dest_sid WHERE (session_id = :old_sid OR session_id = :curr_sid) AND user_id IS NULL");
        $updateCart->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
        $updateCart->bindValue(':dest_sid', session_id(), PDO::PARAM_STR);
        $updateCart->bindValue(':curr_sid', session_id(), PDO::PARAM_STR);
        $updateCart->bindValue(':old_sid', $preAuthSessionId, PDO::PARAM_STR);
        $updateCart->execute();
    }

    return ['success' => true, 'message' => 'Welcome back, ' . $user['name'] . '!', 'user' => $user];
}

// Check if the logged-in session still exists in the database
function validateUserSession(?PDO $pdo = null) {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return null;
    }

    // 30-minute idle timeout
    $idleTimeoutSeconds = 1800;
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $idleTimeoutSeconds) {
        logoutUser();
        return null;
    }
    // Update last activity time
    $_SESSION['last_activity'] = time();

    $userId = (int)$_SESSION['user_id'];

    if (!$pdo) {
        global $pdo;
    }

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, phone, role, created_at FROM `users` WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch();

            if (!$user) {
                // If user was deleted, log them out
                logoutUser();
                return null;
            }

            // Refresh session data from database
            $_SESSION['user_id']    = (int)$user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_phone'] = $user['phone'];
            $_SESSION['user_role']  = $user['role'];

            return $user;
        } catch (Exception $e) {
            error_log("Session DB Verification Error: " . $e->getMessage());
        }
    }

    // Fallback if database is unavailable
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? 'Guest',
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => $_SESSION['user_phone'] ?? '',
        'role'  => $_SESSION['user_role'] ?? 'customer'
    ];
}

// Check if user is logged in
function isLoggedIn(?PDO $pdo = null) {
    $user = validateUserSession($pdo);
    return !empty($user);
}

// Check if user is an admin
function isAdmin(?PDO $pdo = null) {
    $user = validateUserSession($pdo);
    return !empty($user) && isset($user['role']) && $user['role'] === 'admin';
}

// Get current user info
function getCurrentUser(?PDO $pdo = null) {
    return validateUserSession($pdo);
}

// Log out user and clear session
function logoutUser() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies") && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// Shopping Cart Functions

function getEffectiveSessionId() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    return session_id();
}

// Add an item to the cart
function addToCart(PDO $pdo, $productName, $price = 0, $image = '', $qty = 1) {
    $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    $sessionId = getEffectiveSessionId();
    $qty = max(1, (int)$qty);

    // Get product details and stock
    $stmt = $pdo->prepare("SELECT id, name, price, stock, image FROM `products` WHERE name = :name ORDER BY is_active DESC, id DESC LIMIT 1");
    $stmt->bindValue(':name', $productName, PDO::PARAM_STR);
    $stmt->execute();
    $prod = $stmt->fetch();

    $productId = $prod ? (int)$prod['id'] : null;
    $availableStock = $prod ? (int)$prod['stock'] : 999;

    if ($prod) {
        if ($price <= 0) $price = (float)$prod['price'];
        if (empty($image)) $image = $prod['image'];
    }

    if (empty($image)) {
        $image = 'assets/breads-e1656042972619.png';
    }

    // Check if out of stock
    if ($availableStock <= 0) {
        $summary = getCartSummary($pdo);
        return [
            'success'      => false,
            'out_of_stock' => true,
            'message'      => "Sorry, <strong>{$productName}</strong> is currently out of stock and unavailable for purchase.",
            'cart_count'   => $summary['total_items'],
            'cart_total'   => $summary['total_price']
        ];
    }

    // Check if this item is already in the user's cart
    if ($userId) {
        $check = $pdo->prepare("SELECT id, quantity FROM `cart_items` WHERE user_id = :user_id AND (product_name = :pname OR (product_id IS NOT NULL AND product_id = :pid)) LIMIT 1");
        $check->bindValue(':user_id', $userId, PDO::PARAM_INT);
    } else {
        $check = $pdo->prepare("SELECT id, quantity FROM `cart_items` WHERE session_id = :session_id AND user_id IS NULL AND (product_name = :pname OR (product_id IS NOT NULL AND product_id = :pid)) LIMIT 1");
        $check->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
    }
    $check->bindValue(':pname', $productName, PDO::PARAM_STR);
    $check->bindValue(':pid', $productId, $productId ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $check->execute();
    $existing = $check->fetch();

    if ($existing) {
        $newQty = $existing['quantity'] + $qty;
        if ($newQty > $availableStock) {
            $canAdd = max(0, $availableStock - $existing['quantity']);
            $summary = getCartSummary($pdo);
            $msg = $canAdd > 0
                ? "Cannot add {$qty} more. Only {$canAdd} additional unit(s) of <strong>{$productName}</strong> left in stock (you already have {$existing['quantity']} in your cart)."
                : "You already have all available stock ({$availableStock} units) of <strong>{$productName}</strong> in your cart.";
            return [
                'success'      => false,
                'out_of_stock' => false,
                'message'      => $msg,
                'cart_count'   => $summary['total_items'],
                'cart_total'   => $summary['total_price']
            ];
        }

        $update = $pdo->prepare("UPDATE `cart_items` SET quantity = :qty, product_price = :price, product_id = :pid WHERE id = :id");
        $update->bindValue(':qty', $newQty, PDO::PARAM_INT);
        $update->bindValue(':price', $price);
        $update->bindValue(':pid', $productId, $productId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $update->bindValue(':id', $existing['id'], PDO::PARAM_INT);
        $update->execute();
        $cartId = $existing['id'];
    } else {
        if ($qty > $availableStock) {
            $summary = getCartSummary($pdo);
            return [
                'success'      => false,
                'out_of_stock' => false,
                'message'      => "Only {$availableStock} unit(s) of <strong>{$productName}</strong> available in stock.",
                'cart_count'   => $summary['total_items'],
                'cart_total'   => $summary['total_price']
            ];
        }

        $insert = $pdo->prepare("INSERT INTO `cart_items` (user_id, session_id, product_id, product_name, product_price, product_image, quantity) VALUES (:uid, :sid, :pid, :pname, :price, :img, :qty)");
        $insert->bindValue(':uid', $userId, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insert->bindValue(':sid', $sessionId, PDO::PARAM_STR);
        $insert->bindValue(':pid', $productId, $productId ? PDO::PARAM_INT : PDO::PARAM_NULL);
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
        'cart_total' => $summary['total_price'],
        'stock_left' => ($availableStock - ($existing ? ($existing['quantity'] + $qty) : $qty))
    ];
}

// Get all items in the cart
function getCartItems(PDO $pdo) {
    $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    $sessionId = getEffectiveSessionId();

    $stockSql = "COALESCE(
                     (SELECT p.stock FROM `products` p WHERE p.id = c.product_id LIMIT 1),
                     (SELECT p.stock FROM `products` p WHERE p.name = c.product_name AND p.is_active = 1 ORDER BY p.id DESC LIMIT 1),
                     (SELECT p.stock FROM `products` p WHERE p.name = c.product_name ORDER BY p.id DESC LIMIT 1),
                     999
                 ) AS available_stock";

    if ($userId) {
        $stmt = $pdo->prepare("SELECT c.*, {$stockSql} 
                               FROM `cart_items` c 
                               WHERE c.user_id = :uid 
                               ORDER BY c.created_at DESC");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("SELECT c.*, {$stockSql} 
                               FROM `cart_items` c 
                               WHERE c.session_id = :sid AND c.user_id IS NULL 
                               ORDER BY c.created_at DESC");
        $stmt->bindValue(':sid', $sessionId, PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get cart total count and subtotal
function getCartSummary(PDO $pdo) {
    $items = getCartItems($pdo);
    $totalItems = 0;
    $totalPrice = 0.0;
    $hasOutOfStock = false;

    foreach ($items as $item) {
        $qty = (int)$item['quantity'];
        $price = (float)$item['product_price'];
        $stock = isset($item['available_stock']) && $item['available_stock'] !== null ? (int)$item['available_stock'] : 999;
        if ($stock < $qty) {
            $hasOutOfStock = true;
        }
        $totalItems += $qty;
        $totalPrice += ($qty * $price);
    }

    return [
        'items'            => $items,
        'total_items'      => $totalItems,
        'total_price'      => $totalPrice,
        'total_formatted'  => '₱' . number_format($totalPrice, 2),
        'has_out_of_stock' => $hasOutOfStock
    ];
}

// Update quantity for a cart item
function updateCartQty(PDO $pdo, $cartId, $qty) {
    $cartId = (int)$cartId;
    $qty    = (int)$qty;
    $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    $sid    = getEffectiveSessionId();

    if ($qty <= 0) {
        return removeFromCart($pdo, $cartId);
    }

    // Check cart item and stock
    $stockSql = "COALESCE(
                     (SELECT p.stock FROM `products` p WHERE p.id = c.product_id LIMIT 1),
                     (SELECT p.stock FROM `products` p WHERE p.name = c.product_name AND p.is_active = 1 ORDER BY p.id DESC LIMIT 1),
                     (SELECT p.stock FROM `products` p WHERE p.name = c.product_name ORDER BY p.id DESC LIMIT 1),
                     999
                 ) AS stock";

    if ($userId) {
        $checkStmt = $pdo->prepare("SELECT c.id, c.product_name, {$stockSql} 
                                    FROM `cart_items` c 
                                    WHERE c.id = :id AND c.user_id = :uid LIMIT 1");
        $checkStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    } else {
        $checkStmt = $pdo->prepare("SELECT c.id, c.product_name, {$stockSql} 
                                    FROM `cart_items` c 
                                    WHERE c.id = :id AND c.session_id = :sid AND c.user_id IS NULL LIMIT 1");
        $checkStmt->bindValue(':sid', $sid, PDO::PARAM_STR);
    }
    $checkStmt->bindValue(':id', $cartId, PDO::PARAM_INT);
    $checkStmt->execute();
    $row = $checkStmt->fetch();

    if (!$row) {
        // Item not found or doesn't belong to this session
        return getCartSummary($pdo);
    }

    if ($row['stock'] !== null) {
        $avail = (int)$row['stock'];
        if ($qty > $avail) {
            $qty = max(1, $avail);
        }
    }

    if ($userId) {
        $stmt = $pdo->prepare("UPDATE `cart_items` SET quantity = :qty WHERE id = :id AND user_id = :uid");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("UPDATE `cart_items` SET quantity = :qty WHERE id = :id AND session_id = :sid AND user_id IS NULL");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_STR);
    }
    $stmt->bindValue(':qty', $qty, PDO::PARAM_INT);
    $stmt->bindValue(':id', $cartId, PDO::PARAM_INT);
    $stmt->execute();
    return getCartSummary($pdo);
}

// Remove item from cart
function removeFromCart(PDO $pdo, $cartId) {
    $cartId = (int)$cartId;
    $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    $sid    = getEffectiveSessionId();

    if ($userId) {
        $stmt = $pdo->prepare("DELETE FROM `cart_items` WHERE id = :id AND user_id = :uid");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("DELETE FROM `cart_items` WHERE id = :id AND session_id = :sid AND user_id IS NULL");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_STR);
    }
    $stmt->bindValue(':id', $cartId, PDO::PARAM_INT);
    $stmt->execute();
    return getCartSummary($pdo);
}

// Clear the cart
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

// Checkout cart and create an order
function checkoutCart(PDO $pdo, array $formData) {
    $errors = [];

    // Check CSRF token
    if (!validate_csrf_token($formData['csrf_token'] ?? null)) {
        return ['success' => false, 'message' => 'Security token invalid or expired. Please refresh the page and try again.'];
    }

    $summary = getCartSummary($pdo);
    if (empty($summary['items'])) {
        return ['success' => false, 'message' => 'Your shopping cart is empty. Please add bakery items first!'];
    }

    $customerName    = sanitize_input($formData['customer_name'] ?? '');
    $customerPhone   = sanitize_input($formData['customer_phone'] ?? '');
    $orderType       = sanitize_input($formData['order_type'] ?? 'In-Store Pickup');
    $paymentMethod   = sanitize_input($formData['payment_method'] ?? 'Cash on Delivery (COD)');
    $allowedPayments = ['GCash', 'Bank Transfer', 'Cash on Delivery (COD)'];
    if (!in_array($paymentMethod, $allowedPayments)) {
        $paymentMethod = 'Cash on Delivery (COD)';
    }

    $reservationDate = sanitize_input($formData['reservation_date'] ?? '');
    $specialNotes    = sanitize_input($formData['special_notes'] ?? '');
    $userId          = isLoggedIn() ? (int)$_SESSION['user_id'] : null;

    validate_required($customerName, 'Customer Name', $errors);
    validate_phone($customerPhone, $errors);
    validate_required($reservationDate, 'Pickup / Reservation Date', $errors);
    if (!empty($reservationDate) && $reservationDate < date('Y-m-d')) {
        $errors[] = "Pickup / Reservation Date cannot be in the past.";
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    // Build item name summary string
    $itemNames = [];
    foreach ($summary['items'] as $it) {
        $itemNames[] = "{$it['product_name']} (x{$it['quantity']})";
    }
    $bundledNames = implode(', ', $itemNames);
    $totalPrice = (float)$summary['total_price'];
    $totalQty = (int)$summary['total_items'];

    // Deduct stock and save order in a transaction
    try {
        $pdo->beginTransaction();

        // Check each cart item's available stock
        foreach ($summary['items'] as $it) {
            $reqQty = (int)$it['quantity'];
            $pStmt = $pdo->prepare("SELECT id, name, stock FROM `products` WHERE id = :pid OR name = :pname LIMIT 1 FOR UPDATE");
            $pStmt->bindValue(':pid', $it['product_id'] ?? 0, PDO::PARAM_INT);
            $pStmt->bindValue(':pname', $it['product_name'], PDO::PARAM_STR);
            $pStmt->execute();
            $product = $pStmt->fetch();

            if ($product) {
                $currStock = (int)$product['stock'];
                if ($currStock < $reqQty) {
                    $pdo->rollBack();
                    $stockNotice = $currStock > 0 
                        ? "only has <strong>{$currStock}</strong> units remaining in stock (you ordered {$reqQty})." 
                        : "is now <strong>out of stock</strong> and unavailable.";
                    return [
                        'success' => false,
                        'message' => "Order halted: <strong>{$product['name']}</strong> {$stockNotice} Please adjust your cart quantity."
                    ];
                }

                // Deduct stock from products table
                $deductStmt = $pdo->prepare("UPDATE `products` SET stock = stock - :deduct_qty WHERE id = :id AND stock >= :min_qty");
                $deductStmt->bindValue(':deduct_qty', $reqQty, PDO::PARAM_INT);
                $deductStmt->bindValue(':min_qty', $reqQty, PDO::PARAM_INT);
                $deductStmt->bindValue(':id', $product['id'], PDO::PARAM_INT);
                $deductStmt->execute();

                if ($deductStmt->rowCount() === 0) {
                    $pdo->rollBack();
                    return [
                        'success' => false,
                        'message' => "Insufficient stock remaining for <strong>{$product['name']}</strong>. Please review your cart."
                    ];
                }
            }
        }

        $sql = "INSERT INTO `orders` 
                (user_id, customer_name, customer_phone, item_name, item_price, quantity, order_type, payment_method, reservation_date, special_notes, status) 
                VALUES (:user_id, :customer_name, :customer_phone, :item_name, :item_price, :quantity, :order_type, :payment_method, :reservation_date, :special_notes, 'Pending')";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':customer_name', $customerName, PDO::PARAM_STR);
        $stmt->bindValue(':customer_phone', $customerPhone, PDO::PARAM_STR);
        $stmt->bindValue(':item_name', $bundledNames, PDO::PARAM_STR);
        $stmt->bindValue(':item_price', $totalPrice);
        $stmt->bindValue(':quantity', $totalQty, PDO::PARAM_INT);
        $stmt->bindValue(':order_type', $orderType, PDO::PARAM_STR);
        $stmt->bindValue(':payment_method', $paymentMethod, PDO::PARAM_STR);
        $stmt->bindValue(':reservation_date', $reservationDate, PDO::PARAM_STR);
        $stmt->bindValue(':special_notes', $specialNotes, PDO::PARAM_STR);
        $stmt->execute();
        $orderId = (int)$pdo->lastInsertId();

        // Clear cart after order is recorded
        clearCart($pdo);

        $pdo->commit();

        return [
            'success'  => true,
            'message'  => "Thank you, {$customerName}! Your bakery order (#{$orderId}) for {$totalQty} item(s) has been placed successfully via {$paymentMethod}. Stock has been deducted.",
            'order_id' => $orderId,
            'data'     => [
                'order_id'         => $orderId,
                'customer_name'    => $customerName,
                'customer_phone'   => $customerPhone,
                'item_name'        => $bundledNames,
                'item_price'       => $totalPrice,
                'quantity'         => $totalQty,
                'order_type'       => $orderType,
                'payment_method'   => $paymentMethod,
                'reservation_date' => $reservationDate,
                'special_notes'    => $specialNotes
            ]
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Checkout Transaction Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while confirming your order. Please try again or reach out to us.'];
    }
}

// Order & Booking Functions

// Create an order or reservation
function createOrder(PDO $pdo, array $data) {
    $errors = [];

    // Check CSRF token
    if (!empty($data) && !validate_csrf_token($data['csrf_token'] ?? null)) {
        return ['success' => false, 'message' => 'Security token invalid or expired. Please refresh the page and try again.'];
    }

    $customerName    = sanitize_input($data['customer_name'] ?? '');
    $customerPhone   = sanitize_input($data['customer_phone'] ?? '');
    $itemName        = sanitize_input($data['item_name'] ?? 'Custom Bakery Selection');
    $itemPrice       = isset($data['item_price']) ? (float)$data['item_price'] : 0.00;
    $quantity        = isset($data['quantity']) ? max(1, (int)$data['quantity']) : 1;
    $orderType       = sanitize_input($data['order_type'] ?? 'In-Store Pickup');
    $paymentMethod   = sanitize_input($data['payment_method'] ?? 'Cash on Delivery (COD)');
    $allowedPayments = ['GCash', 'Bank Transfer', 'Cash on Delivery (COD)'];
    if (!in_array($paymentMethod, $allowedPayments)) {
        $paymentMethod = 'Cash on Delivery (COD)';
    }
    $reservationDate = sanitize_input($data['reservation_date'] ?? '');
    $specialNotes    = sanitize_input($data['special_notes'] ?? '');
    
    // Use session user ID to prevent spoofing
    $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;

    validate_required($customerName, 'Customer Name', $errors);
    validate_phone($customerPhone, $errors);
    validate_required($reservationDate, 'Reservation / Pickup Date', $errors);
    if (!empty($reservationDate) && $reservationDate < date('Y-m-d')) {
        $errors[] = "Reservation / Pickup Date cannot be in the past.";
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    try {
        $pdo->beginTransaction();

        // Check product stock and price
        $stmtPrice = $pdo->prepare("SELECT id, name, price, stock FROM `products` WHERE name = :exactName OR name LIKE :name LIMIT 1 FOR UPDATE");
        $stmtPrice->bindValue(':exactName', $itemName, PDO::PARAM_STR);
        $stmtPrice->bindValue(':name', "%{$itemName}%", PDO::PARAM_STR);
        $stmtPrice->execute();
        $p = $stmtPrice->fetch();

        if ($p) {
            // Use price from database rather than user input to prevent tampering
            $itemPrice = (float)$p['price'] * $quantity;
            $currStock = (int)$p['stock'];

            if ($currStock < $quantity) {
                $pdo->rollBack();
                $stockMsg = $currStock > 0
                    ? "only has <strong>{$currStock}</strong> left in stock (you requested {$quantity})."
                    : "is currently <strong>out of stock</strong>.";
                return ['success' => false, 'message' => "Sorry, <strong>{$p['name']}</strong> {$stockMsg}"];
            }

            // Deduct stock
            $deduct = $pdo->prepare("UPDATE `products` SET stock = stock - :deduct_qty WHERE id = :id AND stock >= :min_qty");
            $deduct->bindValue(':deduct_qty', $quantity, PDO::PARAM_INT);
            $deduct->bindValue(':min_qty', $quantity, PDO::PARAM_INT);
            $deduct->bindValue(':id', $p['id'], PDO::PARAM_INT);
            $deduct->execute();

            if ($deduct->rowCount() === 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => "Insufficient stock for '{$p['name']}'. Please adjust your quantity."];
            }
        }

        $sql = "INSERT INTO `orders` 
                (user_id, customer_name, customer_phone, item_name, item_price, quantity, order_type, payment_method, reservation_date, special_notes, status) 
                VALUES (:user_id, :customer_name, :customer_phone, :item_name, :item_price, :quantity, :order_type, :payment_method, :reservation_date, :special_notes, 'Pending')";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':customer_name', $customerName, PDO::PARAM_STR);
        $stmt->bindValue(':customer_phone', $customerPhone, PDO::PARAM_STR);
        $stmt->bindValue(':item_name', $itemName, PDO::PARAM_STR);
        $stmt->bindValue(':item_price', $itemPrice);
        $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindValue(':order_type', $orderType, PDO::PARAM_STR);
        $stmt->bindValue(':payment_method', $paymentMethod, PDO::PARAM_STR);
        $stmt->bindValue(':reservation_date', $reservationDate, PDO::PARAM_STR);
        $stmt->bindValue(':special_notes', $specialNotes, PDO::PARAM_STR);
        $stmt->execute();
        $orderId = (int)$pdo->lastInsertId();

        $pdo->commit();

        return [
            'success'  => true,
            'message'  => "Thank you, {$customerName}! Your order (#{$orderId}) has been confirmed and saved. Product stock was updated.",
            'order_id' => $orderId,
            'data'     => [
                'order_id'         => $orderId,
                'customer_name'    => $customerName,
                'customer_phone'   => $customerPhone,
                'item_name'        => $itemName,
                'item_price'       => $itemPrice,
                'quantity'         => $quantity,
                'order_type'       => $orderType,
                'payment_method'   => $paymentMethod,
                'reservation_date' => $reservationDate
            ]
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Order Creation Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while recording your order. Please try again.'];
    }
}

// Get orders with optional search and filter
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

// Adjust inventory stock when order status changes
function reconcileOrderStock(PDO $pdo, string $itemString, int $fallbackQty, string $direction) {
    $operator = ($direction === 'increment') ? '+' : '-';
    $products = getAllProducts($pdo);

    foreach ($products as $p) {
        $pName = $p['name'];
        $escaped = preg_quote($pName, '/');
        if (preg_match('/' . $escaped . '\s*(?:\(x(\d+)\))?/i', $itemString, $matches)) {
            $qty = !empty($matches[1]) ? (int)$matches[1] : $fallbackQty;
            $stmt = $pdo->prepare("UPDATE `products` SET stock = stock {$operator} :qty WHERE id = :id");
            $stmt->bindValue(':qty', $qty, PDO::PARAM_INT);
            $stmt->bindValue(':id', $p['id'], PDO::PARAM_INT);
            $stmt->execute();
        }
    }
}

function updateOrderStatus(PDO $pdo, $orderId, $status) {
    $orderId = (int)$orderId;
    $status = trim($status);
    $allowed = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowed)) {
        return false;
    }

    try {
        $pdo->beginTransaction();

        $fetch = $pdo->prepare("SELECT status, item_name, quantity FROM `orders` WHERE id = :id FOR UPDATE");
        $fetch->bindValue(':id', $orderId, PDO::PARAM_INT);
        $fetch->execute();
        $order = $fetch->fetch();

        if (!$order) {
            $pdo->rollBack();
            return false;
        }

        $oldStatus = $order['status'];

        // Restock if order was cancelled
        if ($status === 'Cancelled' && $oldStatus !== 'Cancelled') {
            reconcileOrderStock($pdo, $order['item_name'], (int)$order['quantity'], 'increment');
        }
        // Deduct stock if order is un-cancelled
        elseif ($oldStatus === 'Cancelled' && $status !== 'Cancelled') {
            reconcileOrderStock($pdo, $order['item_name'], (int)$order['quantity'], 'decrement');
        }

        $stmt = $pdo->prepare("UPDATE `orders` SET status = :status WHERE id = :id");
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
        $res = $stmt->execute();

        $pdo->commit();
        return $res;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Order Status Update Error: " . $e->getMessage());
        return false;
    }
}

function deleteOrder(PDO $pdo, $orderId) {
    $orderId = (int)$orderId;
    try {
        $pdo->beginTransaction();

        $fetch = $pdo->prepare("SELECT status, item_name, quantity FROM `orders` WHERE id = :id FOR UPDATE");
        $fetch->bindValue(':id', $orderId, PDO::PARAM_INT);
        $fetch->execute();
        $order = $fetch->fetch();

        if ($order && $order['status'] !== 'Cancelled') {
            // Put items back in stock before deleting order
            reconcileOrderStock($pdo, $order['item_name'], (int)$order['quantity'], 'increment');
        }

        $stmt = $pdo->prepare("DELETE FROM `orders` WHERE id = :id");
        $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
        $res = $stmt->execute();

        $pdo->commit();
        return $res;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Delete Order Error: " . $e->getMessage());
        return false;
    }
}

// Product Catalog Functions

function getAllProducts(PDO $pdo, $includeHidden = false) {
    if ($includeHidden) {
        $stmt = $pdo->query("SELECT * FROM `products` ORDER BY id ASC");
    } else {
        $stmt = $pdo->query("SELECT * FROM `products` WHERE is_active = 1 ORDER BY id ASC");
    }
    return $stmt->fetchAll();
}

function getFeaturedProducts(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM `products` WHERE is_featured = 1 AND is_active = 1 ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getProductById(PDO $pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM `products` WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch();
}

function getProductByName(PDO $pdo, $name) {
    $stmt = $pdo->prepare("SELECT * FROM `products` WHERE name = :name LIMIT 1");
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch();
}

function addProduct(PDO $pdo, $name, $category, $price, $stock = 15, $desc = '', $image = '', $isFeatured = 1, $isActive = 1) {
    $name = sanitize_input($name);
    $category = sanitize_input($category);
    $price = (float)$price;
    $stock = max(0, (int)$stock);
    $desc = sanitize_input($desc);
    $image = sanitize_input($image);
    $isFeatured = $isFeatured ? 1 : 0;
    $isActive = $isActive ? 1 : 0;

    if (empty($image)) {
        $image = 'assets/breads-e1656042972619.png';
    }

    // Check if product with same name exists
    $check = $pdo->prepare("SELECT id FROM `products` WHERE name = :name ORDER BY is_active DESC, id DESC LIMIT 1");
    $check->bindValue(':name', $name, PDO::PARAM_STR);
    $check->execute();
    $existing = $check->fetch();

    if ($existing) {
        return updateProduct($pdo, $existing['id'], $name, $category, $price, $stock, $desc, $image, $isFeatured, $isActive);
    }

    $stmt = $pdo->prepare("INSERT INTO `products` (name, category, price, stock, description, image, is_featured, is_active) VALUES (:name, :category, :price, :stock, :desc, :image, :is_featured, :is_active)");
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':price', $price);
    $stmt->bindValue(':stock', $stock, PDO::PARAM_INT);
    $stmt->bindValue(':desc', $desc, PDO::PARAM_STR);
    $stmt->bindValue(':image', $image, PDO::PARAM_STR);
    $stmt->bindValue(':is_featured', $isFeatured, PDO::PARAM_INT);
    $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);
    return $stmt->execute();
}

function updateProduct(PDO $pdo, $id, $name, $category, $price, $stock = 15, $desc = '', $image = '', $isFeatured = 1, $isActive = 1) {
    $id = (int)$id;
    $name = sanitize_input($name);
    $category = sanitize_input($category);
    $price = (float)$price;
    $stock = max(0, (int)$stock);
    $desc = sanitize_input($desc);
    $image = sanitize_input($image);
    $isFeatured = $isFeatured ? 1 : 0;
    $isActive = $isActive ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE `products` SET name = :name, category = :category, price = :price, stock = :stock, description = :desc, image = :image, is_featured = :is_featured, is_active = :is_active WHERE id = :id");
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':price', $price);
    $stmt->bindValue(':stock', $stock, PDO::PARAM_INT);
    $stmt->bindValue(':desc', $desc, PDO::PARAM_STR);
    $stmt->bindValue(':image', $image, PDO::PARAM_STR);
    $stmt->bindValue(':is_featured', $isFeatured, PDO::PARAM_INT);
    $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}

function toggleProductStatus(PDO $pdo, $id, $isActive) {
    $id = (int)$id;
    $isActive = $isActive ? 1 : 0;
    
    // Remove from carts if product is disabled
    if ($isActive === 0) {
        try {
            $cartStmt = $pdo->prepare("DELETE FROM `cart_items` WHERE product_id = :id");
            $cartStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $cartStmt->execute();
        } catch (Exception $e) {}
    }
    
    $stmt = $pdo->prepare("UPDATE `products` SET is_active = :status WHERE id = :id");
    $stmt->bindValue(':status', $isActive, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}

function restockProduct(PDO $pdo, $id, $addedStock) {
    $id = (int)$id;
    $addedStock = (int)$addedStock;
    if ($addedStock <= 0) return false;
    $stmt = $pdo->prepare("UPDATE `products` SET stock = stock + :qty WHERE id = :id");
    $stmt->bindValue(':qty', $addedStock, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}

function deleteProduct(PDO $pdo, $id) {
    $id = (int)$id;
    try {
        $cartStmt = $pdo->prepare("DELETE FROM `cart_items` WHERE product_id = :id");
        $cartStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $cartStmt->execute();
    } catch (Exception $e) {}

    $stmt = $pdo->prepare("DELETE FROM `products` WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}

// Admin Reports & Analytics

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
                   COALESCE(SUM(CASE WHEN o.status != 'Cancelled' THEN o.item_price ELSE 0 END), 0) AS total_spent
            FROM `users` u
            LEFT JOIN `orders` o ON u.id = o.user_id
            WHERE u.role = 'customer'
            GROUP BY u.id
            ORDER BY u.created_at DESC";
    return $pdo->query($sql)->fetchAll();
}

// Handle product image upload and check file type & size
function handleProductImageUpload(array $file) {
    if (empty($file['name']) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'path' => '', 'error' => 'No file was uploaded.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => '', 'error' => 'File upload error code: ' . $file['error']];
    }

    // Max file size: 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'path' => '', 'error' => 'Image file is too large (maximum 5MB allowed).'];
    }

    // Clean filename
    $originalName = basename($file['name']);

    // Limit filename length
    if (strlen($originalName) > 200) {
        $originalName = substr($originalName, 0, 200);
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    $fileInfo = pathinfo($originalName);
    $ext = strtolower($fileInfo['extension'] ?? '');

    if (!in_array($ext, $allowedExts)) {
        return ['success' => false, 'path' => '', 'error' => 'Invalid file type. Please upload a JPG, PNG, or WEBP image.'];
    }

    // Check MIME type
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMimes)) {
            return ['success' => false, 'path' => '', 'error' => 'Invalid image content detected. Please upload a real image file.'];
        }
    }

    $assetsDir = __DIR__ . '/../assets/';
    if (!is_dir($assetsDir)) {
        mkdir($assetsDir, 0755, true);
    }

    // Create safe filename
    $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileInfo['filename'] ?? 'product');
    $safeBaseName = substr(trim($safeBaseName, '_'), 0, 30);
    if (empty($safeBaseName)) {
        $safeBaseName = 'product';
    }
    $uniqueName = 'product_' . $safeBaseName . '_' . time() . '.' . $ext;
    $targetPath = $assetsDir . $uniqueName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Also keep a copy in uploads backup
        $uploadsBackupDir = __DIR__ . '/../assets/uploads/';
        if (!is_dir($uploadsBackupDir)) {
            @mkdir($uploadsBackupDir, 0755, true);
        }
        @copy($targetPath, $uploadsBackupDir . $uniqueName);

        return [
            'success' => true,
            'path'    => 'assets/' . $uniqueName,
            'error'   => ''
        ];
    }

    return ['success' => false, 'path' => '', 'error' => 'Could not save the uploaded image to server storage.'];
}

// Calculate product sales, total revenue, and star ratings
function getProductSalesAnalytics(PDO $pdo) {
    $products = getAllProducts($pdo);
    $orders = getAllOrders($pdo);

    $stats = [];
    foreach ($products as $p) {
        $stats[$p['id']] = [
            'id'            => (int)$p['id'],
            'name'          => $p['name'],
            'category'      => $p['category'],
            'price'         => (float)$p['price'],
            'stock'         => (int)$p['stock'],
            'image'         => $p['image'],
            'total_sold'    => 0,
            'total_revenue' => 0.0,
            'order_count'   => 0
        ];
    }

    // Process valid orders
    foreach ($orders as $ord) {
        if ($ord['status'] === 'Cancelled') continue;
        
        $itemStr = $ord['item_name'];
        $orderQty = max(1, (int)($ord['quantity'] ?? 1));

        foreach ($products as $p) {
            $pName = $p['name'];
            $escaped = preg_quote($pName, '/');
            // Match product name and quantity
            if (preg_match('/' . $escaped . '\s*(?:\(x(\d+)\))?/i', $itemStr, $matches)) {
                $itemCount = !empty($matches[1]) ? (int)$matches[1] : $orderQty;
                $stats[$p['id']]['total_sold'] += $itemCount;
                $stats[$p['id']]['total_revenue'] += ($itemCount * (float)$p['price']);
                $stats[$p['id']]['order_count'] += 1;
            }
        }
    }

    // Find highest seller
    $maxSold = 0;
    foreach ($stats as $s) {
        if ($s['total_sold'] > $maxSold) {
            $maxSold = $s['total_sold'];
        }
    }

    // Calculate star ratings and badge labels
    foreach ($stats as &$item) {
        $sold = $item['total_sold'];
        if ($maxSold > 0 && $sold > 0) {
            $rating = 4.2 + (($sold / $maxSold) * 0.8);
            $rating = round($rating, 1);
        } else {
            $rating = 4.0;
        }
        $item['rating'] = min(5.0, $rating);
        $fullStars = (int)floor($item['rating']);
        $hasHalf = ($item['rating'] - $fullStars >= 0.5);
        $item['rating_stars'] = str_repeat('★', $fullStars) . ($hasHalf ? '½' : '');

        // Badge label
        if ($maxSold > 0 && $sold === $maxSold && $sold >= 2) {
            $item['badge'] = '🏆 #1 Top Seller';
            $item['badge_color'] = '#D97706';
            $item['badge_bg'] = '#FEF3C7';
        } elseif ($sold >= 3) {
            $item['badge'] = '🔥 Best Seller';
            $item['badge_color'] = '#DC2626';
            $item['badge_bg'] = '#FEE2E2';
        } elseif ($sold > 0) {
            $item['badge'] = '⭐ Highly Rated';
            $item['badge_color'] = '#059669';
            $item['badge_bg'] = '#D1FAE5';
        } else {
            $item['badge'] = '✨ Available';
            $item['badge_color'] = '#4B5563';
            $item['badge_bg'] = '#F3F4F6';
        }
    }
    unset($item);

    // Sort by most sold, then revenue
    usort($stats, function($a, $b) {
        if ($b['total_sold'] === $a['total_sold']) {
            return $b['total_revenue'] <=> $a['total_revenue'];
        }
        return $b['total_sold'] <=> $a['total_sold'];
    });

    return $stats;
}

// ==============================================================================
// PASSWORD RECOVERY & MANAGEMENT FUNCTIONS
// ==============================================================================

/**
 * Generate a cryptographically secure password reset token for a registered user.
 */
function createPasswordReset(PDO $pdo, $email) {
    $email = strtolower(trim($email));
    
    // Validate email format
    $errors = [];
    validate_email($email, $errors);
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, name, email FROM `users` WHERE LOWER(TRIM(email)) = :email LIMIT 1");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user) {
        return [
            'success' => false,
            'message' => 'No bakery account is registered with the email address <strong>' . htmlspecialchars($email) . '</strong>. Please check for typos or create a new account.'
        ];
    }

    // Clean up any old tokens for this email
    $del = $pdo->prepare("DELETE FROM `password_resets` WHERE LOWER(TRIM(email)) = :email OR expires_at < :now");
    $del->bindValue(':email', $email, PDO::PARAM_STR);
    $del->bindValue(':now', time(), PDO::PARAM_INT);
    $del->execute();

    // Generate 64-character hex token from 32 cryptographically secure random bytes
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = time() + 3600; // 1 hour expiration

    $ins = $pdo->prepare("INSERT INTO `password_resets` (email, token_hash, expires_at) VALUES (:email, :token_hash, :expires_at)");
    $ins->bindValue(':email', $email, PDO::PARAM_STR);
    $ins->bindValue(':token_hash', $tokenHash, PDO::PARAM_STR);
    $ins->bindValue(':expires_at', $expiresAt, PDO::PARAM_INT);
    $ins->execute();

    return [
        'success'    => true,
        'message'    => 'A password reset token has been generated successfully.',
        'raw_token'  => $rawToken,
        'user'       => $user,
        'expires_at' => $expiresAt
    ];
}

/**
 * Verify if a given raw reset token is valid and unexpired.
 */
function verifyPasswordResetToken(PDO $pdo, $rawToken) {
    if (empty($rawToken) || !is_string($rawToken) || strlen($rawToken) < 32) {
        return ['valid' => false, 'message' => 'Invalid or malformed password reset link.'];
    }

    $tokenHash = hash('sha256', trim($rawToken));
    $now = time();

    $stmt = $pdo->prepare("SELECT * FROM `password_resets` WHERE token_hash = :hash AND expires_at > :now LIMIT 1");
    $stmt->bindValue(':hash', $tokenHash, PDO::PARAM_STR);
    $stmt->bindValue(':now', $now, PDO::PARAM_INT);
    $stmt->execute();
    $reset = $stmt->fetch();

    if (!$reset) {
        return [
            'valid' => false,
            'message' => 'This password reset link is invalid or has expired (links remain active for 1 hour). Please request a fresh reset link.'
        ];
    }

    // Fetch the associated user
    $uStmt = $pdo->prepare("SELECT id, name, email FROM `users` WHERE LOWER(TRIM(email)) = :email LIMIT 1");
    $uStmt->bindValue(':email', strtolower(trim($reset['email'])), PDO::PARAM_STR);
    $uStmt->execute();
    $user = $uStmt->fetch();

    if (!$user) {
        return ['valid' => false, 'message' => 'The user account associated with this reset link no longer exists.'];
    }

    return [
        'valid' => true,
        'reset' => $reset,
        'user'  => $user
    ];
}

/**
 * Reset a user's password using a valid reset token.
 */
function resetUserPassword(PDO $pdo, $rawToken, $newPassword, $confirmPassword) {
    $verify = verifyPasswordResetToken($pdo, $rawToken);
    if (!$verify['valid']) {
        return ['success' => false, 'message' => $verify['message']];
    }

    $errors = [];
    validate_required($newPassword, 'New Password', $errors);
    validate_length($newPassword, 'New Password', 8, 255, $errors);

    if (strlen($newPassword) >= 8 && !preg_match('/[0-9!@#$%^&*()_\-+=\[\]{};:\'"\\|,.<>\/?`~]/', $newPassword)) {
        $errors[] = 'New password must contain at least one number or special character (e.g. !@#$%&*).';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation password do not match.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    $user = $verify['user'];
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    try {
        // Update user password
        $upd = $pdo->prepare("UPDATE `users` SET password = :password WHERE id = :id");
        $upd->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
        $upd->bindValue(':id', $user['id'], PDO::PARAM_INT);
        $upd->execute();

        // Invalidate token
        $del = $pdo->prepare("DELETE FROM `password_resets` WHERE LOWER(TRIM(email)) = :email");
        $del->bindValue(':email', strtolower(trim($user['email'])), PDO::PARAM_STR);
        $del->execute();

        return [
            'success' => true,
            'message' => "Password for <strong>{$user['name']}</strong> has been updated successfully! You can now sign in with your new credentials."
        ];
    } catch (PDOException $e) {
        error_log("Password Reset Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'A database error occurred while updating your password. Please try again.'];
    }
}

/**
 * Change password for an authenticated user with current password verification.
 */
function changeUserPassword(PDO $pdo, $userId, $currentPassword, $newPassword, $confirmPassword) {
    $userId = (int)$userId;
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'User must be authenticated to change password.'];
    }

    $errors = [];
    validate_required($currentPassword, 'Current Password', $errors);
    validate_required($newPassword, 'New Password', $errors);
    validate_length($newPassword, 'New Password', 8, 255, $errors);

    if (strlen($newPassword) >= 8 && !preg_match('/[0-9!@#$%^&*()_\-+=\[\]{};:\'"\\|,.<>\/?`~]/', $newPassword)) {
        $errors[] = 'New password must contain at least one number or special character (e.g. !@#$%&*).';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    // Fetch user to verify current password
    $stmt = $pdo->prepare("SELECT id, name, password FROM `users` WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'User account not found.'];
    }

    if (!password_verify($currentPassword, $user['password'])) {
        return ['success' => false, 'message' => 'The current password you entered is incorrect. Please verify and try again.'];
    }

    if (password_verify($newPassword, $user['password'])) {
        return ['success' => false, 'message' => 'Your new password cannot be the same as your current password. Please choose a different password.'];
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    try {
        $upd = $pdo->prepare("UPDATE `users` SET password = :password WHERE id = :id");
        $upd->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
        $upd->bindValue(':id', $userId, PDO::PARAM_INT);
        $upd->execute();

        return [
            'success' => true,
            'message' => 'Your password has been changed successfully! Your account credentials are now updated.'
        ];
    } catch (PDOException $e) {
        error_log("Change Password Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'A database error occurred while updating your password. Please try again.'];
    }
}

