<?php
/**
 * Asentista Bakery - Shopping Cart AJAX Action Handler
 * Returns JSON for all client-side cart interactions.
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/function.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? 'get_cart';

// SECURITY: All cart mutations MUST have a valid CSRF token — no Referer/XHR fallback
if (in_array($action, ['add', 'update_qty', 'remove', 'clear'])) {
    if (!validate_csrf_token()) {
        echo json_encode([
            'success' => false,
            'message' => 'Security token expired or invalid. Please refresh the page.'
        ]);
        exit;
    }

    // SECURITY: Simple per-session rate limit — max 60 cart mutations per minute
    $rateLimitKey = 'cart_rate_' . session_id();
    $now = time();
    $windowStart = $now - 60; // 1-minute rolling window
    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = [];
    }
    // Remove timestamps older than 1 minute
    $_SESSION[$rateLimitKey] = array_filter($_SESSION[$rateLimitKey], fn($t) => $t > $windowStart);
    if (count($_SESSION[$rateLimitKey]) >= 60) {
        echo json_encode([
            'success' => false,
            'message' => 'Too many cart requests. Please slow down and try again in a moment.'
        ]);
        exit;
    }
    $_SESSION[$rateLimitKey][] = $now;
}


switch ($action) {
    case 'add':
        $productName = sanitize_input($_POST['product_name'] ?? '');
        $price       = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
        $image       = sanitize_input($_POST['image'] ?? '');
        $qty         = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

        if (empty($productName)) {
            echo json_encode(['success' => false, 'message' => 'Product name is required.']);
            exit;
        }

        $res = addToCart($pdo, $productName, $price, $image, $qty);
        echo json_encode($res);
        exit;

    case 'update_qty':
        $cartId = (int)($_POST['cart_id'] ?? 0);
        $qty    = (int)($_POST['quantity'] ?? 1);

        if ($cartId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid cart item ID.']);
            exit;
        }

        $summary = updateCartQty($pdo, $cartId, $qty);
        echo json_encode([
            'success'     => true,
            'message'     => 'Cart quantity updated.',
            'cart_count'  => $summary['total_items'],
            'cart_total'  => $summary['total_price'],
            'total_formatted' => $summary['total_formatted'],
            'has_out_of_stock' => $summary['has_out_of_stock']
        ]);
        exit;

    case 'remove':
        $cartId = (int)($_POST['cart_id'] ?? 0);
        if ($cartId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid cart item ID.']);
            exit;
        }

        $summary = removeFromCart($pdo, $cartId);
        echo json_encode([
            'success'     => true,
            'message'     => 'Item removed from your cart.',
            'cart_count'  => $summary['total_items'],
            'cart_total'  => $summary['total_price'],
            'total_formatted' => $summary['total_formatted']
        ]);
        exit;

    case 'clear':
        clearCart($pdo);
        echo json_encode([
            'success'     => true,
            'message'     => 'Cart cleared.',
            'cart_count'  => 0,
            'cart_total'  => 0,
            'total_formatted' => '₱0.00'
        ]);
        exit;

    case 'get_cart':
    default:
        $summary = getCartSummary($pdo);
        echo json_encode([
            'success'     => true,
            'cart_count'  => $summary['total_items'],
            'cart_total'  => $summary['total_price'],
            'total_formatted' => $summary['total_formatted'],
            'items'       => $summary['items']
        ]);
        exit;
}
