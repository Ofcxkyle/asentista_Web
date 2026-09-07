<?php
/**
 * Asentista Bakery - Order Processing Endpoint
 * Handles POST requests for placing orders & table bookings into the MySQL database.
 * Strictly requires authenticated user session (guests can browse, but cannot place orders).
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/function.php';

$isSubfolder = (strpos($_SERVER['REQUEST_URI'] ?? '', '/Cart/') !== false);
$rootPrefix = $isSubfolder ? '../' : '';

// Check if request is AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $isAjax = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    if (!isLoggedIn()) {
        if ($isAjax || isset($_GET['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'      => false,
                'require_auth' => true,
                'message'      => 'An account is required to place an order. Please sign in or register to complete your bakery order.',
                'redirect'     => 'Login/auth.php?msg=login_to_order'
            ]);
            exit;
        }

        header('Location: ' . $rootPrefix . 'Login/auth.php?redirect=Cart/cart.php&msg=login_to_order');
        exit;
    }

    // Collect data (supporting both form-urlencoded and JSON payload)
    $inputData = $_POST;
    if (empty($inputData)) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $inputData = $decoded;
        }
    }

    $result = createOrder($pdo, $inputData);

    if ($isAjax || isset($_GET['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        exit;
    }

    // Standard POST form submission fallback
    if ($result['success']) {
        $_SESSION['flash_order'] = $result['data'];
        header('Location: ' . $rootPrefix . 'database/success.php?order_id=' . $result['order_id']);
        exit;
    } else {
        $_SESSION['flash_error'] = $result['message'];
        header('Location: ' . $rootPrefix . 'index.php?error=' . urlencode($result['message']));
        exit;
    }
} else {
    // Non-POST request
    header('Location: ' . $rootPrefix . 'index.php');
    exit;
}
