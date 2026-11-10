<?php
// Shopping cart and checkout page.

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/function.php';

$currentUser = getCurrentUser();
$cartSummary = getCartSummary($pdo);
$cartItems = $cartSummary['items'];

$errorMsg = '';
$successMsg = '';

// Handle Checkout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_action'])) {
    if (!isLoggedIn()) {
        header('Location: ../Login/auth.php?redirect=Cart/cart.php&msg=login_to_order');
        exit;
    }
    $checkoutResult = checkoutCart($pdo, $_POST);
    if ($checkoutResult['success']) {
        $_SESSION['flash_order'] = $checkoutResult['data'];
        header('Location: ../database/success.php?order_id=' . $checkoutResult['order_id']);
        exit;
    } else {
        $errorMsg = $checkoutResult['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
<?php
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$parts = array_values(array_filter(explode('/', trim($scriptDir, '/'))));
if (!empty($parts) && in_array(end($parts), ['Admin', 'User', 'Login', 'Cart', 'database'])) {
    array_pop($parts);
}
$appBasePath = !empty($parts) ? '/' . implode('/', $parts) . '/' : '/';
?>
    <base href="<?php echo htmlspecialchars($appBasePath); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Bakery Cart - Asentista's Bakery</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/ASENTISTA FINAL.png">
    <link rel="apple-touch-icon" href="assets/ASENTISTA FINAL.png">
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-page-container {
            padding: 3.5rem 0 5rem 0;
            min-height: 80vh;
        }
        .cart-grid-layout {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 2.5rem;
            align-items: start;
        }
        .cart-items-card, .checkout-card {
            background-color: var(--color-white);
            border-radius: 6px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(43, 27, 21, 0.12);
            overflow: hidden;
        }
        .card-header-bar {
            background-color: var(--color-brown-deep);
            color: var(--color-cream-light);
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header-title {
            font-family: var(--font-serif);
            font-size: 1.25rem;
            font-style: italic;
            font-weight: 700;
        }
        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }
        .cart-table th {
            background-color: var(--color-cream-light);
            padding: 0.85rem 1rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--color-brown-deep);
            text-align: left;
        }
        .cart-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(43, 27, 21, 0.08);
            vertical-align: middle;
        }
        .cart-item-preview {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cart-thumb-img {
            width: 54px;
            height: 54px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid rgba(43, 27, 21, 0.15);
        }
        .cart-item-title {
            font-family: var(--font-serif);
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--color-brown-deep);
        }
        .cart-qty-ctrl {
            display: flex;
            align-items: center;
            border: 1px solid rgba(43, 27, 21, 0.16);
            border-radius: 6px;
            overflow: hidden;
            width: fit-content;
            background: #fff;
            box-shadow: 0 1px 2px rgba(43, 27, 21, 0.04);
        }
        .qty-btn {
            background: var(--color-cream-light);
            color: var(--color-brown-deep);
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: background-color 140ms ease, color 140ms ease;
        }
        .qty-btn:hover {
            background: #FAF7F2;
            color: var(--color-brown-deep);
        }
        .qty-btn:active {
            transform: scale(0.92);
        }
        .qty-val {
            width: 36px;
            text-align: center;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--color-brown-deep);
        }
        .btn-remove-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #991B1B;
            background: #FEF2F2;
            border: 1px solid #FECACA;
            padding: 4px 9px;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 140ms var(--ease-out-expo), background-color 140ms ease, color 140ms ease;
        }
        .btn-remove-item:hover {
            background: #991B1B;
            color: #FFFFFF;
            transform: translateY(-1px);
        }
        .cart-footer-bar {
            padding: 1.25rem 1.5rem;
            background-color: var(--color-cream-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        /* Buttons & micro-interactions */
        .btn-clear-cart {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #991B1B;
            cursor: pointer;
            background: #FEF2F2;
            border: 1px solid #FECACA;
            padding: 6px 14px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(153, 27, 27, 0.06);
            transition: transform 140ms var(--ease-out-expo), background-color 140ms ease, color 140ms ease, box-shadow 140ms ease;
        }
        .btn-clear-cart:hover {
            background: #991B1B;
            color: #FFFFFF;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(153, 27, 27, 0.22);
        }
        .btn-clear-cart:active {
            transform: translateY(0) scale(0.97);
        }

        /* Payment Method Selector */
        .payment-method-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 0.6rem;
            margin-bottom: 0.75rem;
        }
        .payment-option-card {
            border: 2px solid rgba(43, 27, 21, 0.12);
            border-radius: 10px;
            padding: 12px 8px;
            cursor: pointer;
            background: #FFFFFF;
            transition: all 180ms cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            user-select: none;
        }
        .payment-option-card:hover {
            border-color: #D97706;
            background: #FFFDF9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.12);
        }
        .payment-option-card.active {
            border-color: #D97706;
            background: linear-gradient(180deg, #FFFBEB 0%, #FEF3C7 100%);
            box-shadow: 0 0 0 1px #D97706, 0 4px 14px rgba(217, 119, 6, 0.18);
        }
        .payment-radio {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .payment-card-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            width: 100%;
        }
        .payment-card-top {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        .payment-icon {
            font-size: 1.55rem;
            line-height: 1;
            margin-bottom: 2px;
        }
        .payment-name {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--color-brown-deep);
            line-height: 1.2;
        }
        .payment-tag {
            font-size: 0.68rem;
            font-weight: 600;
            color: var(--color-text-muted);
            line-height: 1.1;
        }
        .payment-option-card.active .payment-name {
            color: #78350F;
        }
        .payment-option-card.active .payment-tag {
            color: #92400E;
            font-weight: 700;
        }
        .payment-instruction-box {
            background: #FAF7F2;
            border: 1px solid rgba(43, 27, 21, 0.12);
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 1.2rem;
            transition: all 200ms ease;
        }
        .checkout-body {
            padding: 1.5rem;
        }
        .cart-summary-line {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.9rem;
            color: var(--color-text-muted);
        }
        .cart-summary-total {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-top: 2px dashed rgba(43, 27, 21, 0.2);
            margin-top: 0.8rem;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--color-brown-deep);
        }
        @media (max-width: 900px) {
            .cart-grid-layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="<?php echo isAdmin($pdo) ? 'admin-logged-in' : ''; ?>">

    <!-- Modern Luxury Navbar -->
    <nav class="site-nav" style="background: #2B1B15; border-bottom: 2px solid #FFAE34; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
        <div class="container nav-container" style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.5rem;">
            <a href="index.php" class="brand-logo-wrap" style="text-decoration: none; display: flex; align-items: center; gap: 12px;">
                <div class="brand-svg-logo" style="width: 44px; height: 44px; border-radius: 50%; background: #FFF; padding: 2px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                    <img src="assets/ASENTISTA FINAL.png" alt="Asentista's Bakery Logo" class="brand-logo-img" style="width: 38px; height: 38px; object-fit: contain;">
                </div>
                <div class="brand-text-block">
                    <span class="brand-title" style="font-family: var(--font-serif); font-size: 1.15rem; font-weight: 800; color: #FFAE34; letter-spacing: 0.05em; display: block;">ASENTISTA'S</span>
                    <span class="brand-subtitle" style="font-size: 0.68rem; color: #FAF7F2; letter-spacing: 0.15em; display: block; opacity: 0.85;">ARTISAN BAKERY</span>
                </div>
            </a>
            
            <div style="display: flex; align-items: center; gap: 0.85rem;">
                <a href="index.php" class="btn btn-secondary btn-sm" style="text-decoration: none; font-weight: 600;">
                    ← Continue Shopping
                </a>
                <a href="User/dashboard.php" class="btn btn-secondary btn-sm" style="text-decoration: none; font-weight: 600;">
                    Orders Portal
                </a>
                <?php if (isAdmin($pdo)): ?>
                    <a href="Admin/admin.php" class="btn btn-accent btn-sm" style="text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;" title="Return to Executive Admin Console">
                        <span>👑</span> Admin Console
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container cart-page-container">
        <h1 style="font-family: var(--font-serif); font-size: 2.2rem; color: var(--color-brown-deep); margin-bottom: 1.5rem;">
            Shopping Cart & Checkout
        </h1>

        <?php if (!empty($errorMsg)): ?>
            <div style="background-color: #FEE2E2; color: #991B1B; padding: 1rem 1.2rem; border-radius: 4px; margin-bottom: 1.5rem; border-left: 4px solid #DC2626;">
                <?php echo $errorMsg; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <div class="cart-items-card" style="padding: 4.5rem 2rem; text-align: center; border-radius: 12px; box-shadow: 0 10px 30px rgba(43,27,21,0.08); background: #FFFFFF;">
                <div style="width: 84px; height: 84px; margin: 0 auto 1.5rem auto; background: #FFFBEB; border: 2px dashed #FDE68A; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.1);">
                    🧺
                </div>
                <h2 style="font-family: var(--font-serif); font-size: 1.75rem; color: var(--color-brown-deep); margin-bottom: 0.6rem; font-weight: 800;">
                    Your Bakery Cart is Empty
                </h2>
                <p style="color: var(--color-text-muted); font-size: 0.96rem; max-width: 440px; margin: 0 auto 2.2rem auto; line-height: 1.6;">
                    Explore our fresh daily sourdough, crunchy crusts, warm rolls, and artisan bakery delights!
                </p>
                <a href="index.php#bread-menu" class="btn btn-primary btn-lg btn-shimmer" style="text-decoration: none; display: inline-flex; align-items: center; gap: 10px; padding: 0.95rem 2.5rem; font-size: 0.92rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;">
                    <span>Browse Bread Menu</span>
                    <span style="font-size: 1.1rem;">→</span>
                </a>
            </div>
        <?php else: ?>
            <div class="cart-grid-layout">
                <!-- Cart items table -->
                <div class="cart-items-card">
                    <div class="card-header-bar">
                        <span class="card-header-title">Items in Your Cart (<?php echo $cartSummary['total_items']; ?>)</span>
                        <button type="button" class="btn-clear-cart" onclick="clearCartAsync()">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            <span>Clear Cart</span>
                        </button>
                    </div>

                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <?php 
                                    $itemSubtotal = (float)$item['product_price'] * (int)$item['quantity'];
                                    $availStock = isset($item['available_stock']) && $item['available_stock'] !== null ? (int)$item['available_stock'] : 999;
                                    $isItemOutOfStock = ($availStock <= 0);
                                    $isItemStockInsufficient = ($availStock < (int)$item['quantity']);
                                ?>
                                <tr id="cartRow-<?php echo $item['id']; ?>" style="<?php echo $isItemOutOfStock ? 'background-color: rgba(229,57,53,0.06);' : ''; ?>">
                                    <td>
                                        <div class="cart-item-preview">
                                            <img src="<?php echo htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="cart-thumb-img">
                                            <div>
                                                <div class="cart-item-title"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                <?php if ($isItemOutOfStock): ?>
                                                    <span style="display:inline-block; font-size:0.72rem; font-weight:700; background:#E53935; color:#fff; padding:2px 7px; border-radius:3px; margin-top:4px;">OUT OF STOCK</span>
                                                <?php elseif ($isItemStockInsufficient): ?>
                                                    <span style="display:inline-block; font-size:0.72rem; font-weight:700; background:#FB8C00; color:#fff; padding:2px 7px; border-radius:3px; margin-top:4px;">Only <?php echo $availStock; ?> left in stock</span>
                                                <?php else: ?>
                                                    <span style="font-size:0.72rem; color: #2E7D32; font-weight:600;">In Stock (<?php echo $availStock; ?> left)</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-weight: 600;">₱<?php echo number_format($item['product_price'], 2); ?></td>
                                    <td>
                                        <div class="cart-qty-ctrl">
                                            <button type="button" class="qty-btn" onclick="updateQtyAsync(<?php echo $item['id']; ?>, <?php echo $item['quantity'] - 1; ?>)">−</button>
                                            <span class="qty-val"><?php echo $item['quantity']; ?></span>
                                            <button type="button" class="qty-btn" onclick="updateQtyAsync(<?php echo $item['id']; ?>, <?php echo $item['quantity'] + 1; ?>)" <?php echo ($item['quantity'] >= $availStock) ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : ''; ?>>+</button>
                                        </div>
                                    </td>
                                    <td style="font-weight: 700; color: var(--color-brown-deep);">
                                        ₱<?php echo number_format($itemSubtotal, 2); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <button type="button" class="btn-remove-item" onclick="removeItemAsync(<?php echo $item['id']; ?>)">✕</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="cart-footer-bar">
                        <a href="index.php#bread-menu" style="font-size: 0.85rem; color: var(--color-brown-deep); font-weight: 600; text-decoration: underline;">
                            + Add more items from menu
                        </a>
                        <span style="font-size: 0.95rem; font-weight: 700;">
                            Cart Subtotal: <span style="color: var(--color-yellow-hover); font-size: 1.1rem;"><?php echo $cartSummary['total_formatted']; ?></span>
                        </span>
                    </div>
                </div>

                <!-- Checkout form -->
                <div class="checkout-card">
                    <div class="card-header-bar">
                        <span class="card-header-title">Order Details & Checkout</span>
                    </div>
                    <div class="checkout-body">
                        <?php if (!$currentUser): ?>
                            <div class="guest-checkout-gate">
                                <div class="guest-badge-icon">🔒</div>
                                <h3 style="font-family: var(--font-serif); font-size: 1.25rem; color: var(--color-brown-deep); margin-bottom: 0.5rem; font-weight: 700;">
                                    Account Required to Order
                                </h3>
                                <p style="font-size: 0.86rem; color: var(--color-text-muted); line-height: 1.6; margin-bottom: 1.5rem;">
                                    You are currently browsing as a <strong>Guest</strong>. While you can explore the menu and calculate your cart total, you must <strong>Sign In or Create an Account</strong> to confirm and place your order into our bakery schedule.
                                </p>

                                <div class="cart-summary-line">
                                    <span>Total Items Selected:</span>
                                    <strong style="color: var(--color-brown-deep);"><?php echo $cartSummary['total_items']; ?> items</strong>
                                </div>
                                <div class="cart-summary-total">
                                    <span>Estimated Total:</span>
                                    <span style="color: var(--color-amber-accessible, #92400E); font-weight: 800;"><?php echo $cartSummary['total_formatted']; ?></span>
                                </div>

                                <a href="Login/auth.php?redirect=Cart/cart.php&msg=login_to_order" class="btn btn-primary btn-lg shimmer-btn" style="text-decoration: none; width: 100%; margin-top: 1.2rem;">
                                    <span>Sign In to Place Order →</span>
                                </a>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($cartSummary['has_out_of_stock'])): ?>
                                <div style="background:#FFEBEE; border:1px solid #FFCDD2; color:#C62828; padding:0.85rem 1rem; border-radius:4px; font-size:0.85rem; margin-bottom:1.2rem; line-height:1.4;">
                                    ⚠️ <strong>Inventory Alert:</strong> One or more items in your cart exceed available bakery inventory. Please adjust quantities or remove out-of-stock items before placing your order.
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="Cart/cart.php">
                                <input type="hidden" name="checkout_action" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

                                <div class="form-group">
                                    <label class="form-label" for="custName">Customer Full Name *</label>
                                    <input type="text" id="custName" name="customer_name" class="form-input" placeholder="e.g. Maria Santos" required value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="custPhone">Phone Number *</label>
                                    <input type="tel" id="custPhone" name="customer_phone" class="form-input" placeholder="09940058425" required maxlength="11" minlength="11" pattern="[0-9]{11}" inputmode="numeric" title="Phone number must consist only of 11 digits (numbers only, e.g. 09940058425)" value="<?php echo htmlspecialchars(preg_replace('/\D/', '', $currentUser['phone'] ?? '')); ?>">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="resDate">Pickup / Reservation Date *</label>
                                        <input type="date" id="resDate" name="reservation_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="orderType">Order Option</label>
                                        <select id="orderType" name="order_type" class="form-select">
                                            <option value="In-Store Pickup">In-Store Pickup</option>
                                            <option value="Dine-in Table Booking">Dine-in Table Booking</option>
                                            <option value="Direct Delivery">Direct Delivery</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Select Payment Method Section -->
                                <div class="form-group" style="margin-top: 1rem; margin-bottom: 1.25rem;">
                                    <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                                        <span>Payment Method *</span>
                                        <span style="font-size: 0.72rem; color: #059669; font-weight: 700; background: #ECFDF5; padding: 2px 8px; border-radius: 999px;">✓ Verified Options</span>
                                    </label>
                                    
                                    <div class="payment-method-selector">
                                        <!-- GCash Option -->
                                        <label class="payment-option-card" id="opt-gcash" onclick="selectPaymentMethod('GCash')">
                                            <input type="radio" name="payment_method" value="GCash" class="payment-radio" id="radio-gcash">
                                            <div class="payment-card-inner">
                                                <div class="payment-card-top">
                                                    <span class="payment-icon">📱</span>
                                                    <span class="payment-name">GCash</span>
                                                </div>
                                                <span class="payment-tag">e-Wallet</span>
                                            </div>
                                        </label>

                                        <!-- Bank Transfer Option -->
                                        <label class="payment-option-card" id="opt-bank" onclick="selectPaymentMethod('Bank Transfer')">
                                            <input type="radio" name="payment_method" value="Bank Transfer" class="payment-radio" id="radio-bank">
                                            <div class="payment-card-inner">
                                                <div class="payment-card-top">
                                                    <span class="payment-icon">🏦</span>
                                                    <span class="payment-name">Bank</span>
                                                </div>
                                                <span class="payment-tag">BDO / BPI</span>
                                            </div>
                                        </label>

                                        <!-- COD Option (Default) -->
                                        <label class="payment-option-card active" id="opt-cod" onclick="selectPaymentMethod('Cash on Delivery (COD)')">
                                            <input type="radio" name="payment_method" value="Cash on Delivery (COD)" class="payment-radio" id="radio-cod" checked>
                                            <div class="payment-card-inner">
                                                <div class="payment-card-top">
                                                    <span class="payment-icon">💵</span>
                                                    <span class="payment-name">COD</span>
                                                </div>
                                                <span class="payment-tag">Pay on Pickup</span>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Dynamic Payment Instruction Box -->
                                    <div id="payment-instruction-box" class="payment-instruction-box">
                                        <div id="instruction-cod" class="instruction-panel">
                                            <div style="display: flex; gap: 8px; align-items: flex-start;">
                                                <span style="font-size: 1.15rem;">💵</span>
                                                <div>
                                                    <strong style="color: var(--color-brown-deep); font-size: 0.84rem;">Cash on Delivery / In-Store Cashier:</strong>
                                                    <p style="margin: 3px 0 0 0; font-size: 0.77rem; color: var(--color-text-muted); line-height: 1.4;">Pay with cash upon collecting your bread at the bakery counter or when your delivery arrives.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="instruction-gcash" class="instruction-panel" style="display: none;">
                                            <div style="display: flex; gap: 8px; align-items: flex-start;">
                                                <span style="font-size: 1.15rem;">📱</span>
                                                <div style="width: 100%;">
                                                    <strong style="color: #1E40AF; font-size: 0.84rem;">GCash Transfer Details:</strong>
                                                    <div style="background: #EFF6FF; border: 1px dashed #93C5FD; border-radius: 6px; padding: 6px 10px; margin-top: 4px; font-size: 0.82rem; color: #1E3A8A;">
                                                        <div>Account Name: <strong>Asentista Bakery (Kyle A.)</strong></div>
                                                        <div>GCash Number: <strong style="letter-spacing: 0.04em;">0994 005 8425</strong></div>
                                                    </div>
                                                    <p style="margin: 4px 0 0 0; font-size: 0.74rem; color: var(--color-text-muted);">Please keep your transaction reference number handy upon pickup or dispatch.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="instruction-bank" class="instruction-panel" style="display: none;">
                                            <div style="display: flex; gap: 8px; align-items: flex-start;">
                                                <span style="font-size: 1.15rem;">🏦</span>
                                                <div style="width: 100%;">
                                                    <strong style="color: #065F46; font-size: 0.84rem;">Online Bank Transfer Details:</strong>
                                                    <div style="background: #ECFDF5; border: 1px dashed #6EE7B7; border-radius: 6px; padding: 6px 10px; margin-top: 4px; font-size: 0.82rem; color: #064E3B;">
                                                        <div>Bank: <strong>BDO Unibank / BPI</strong></div>
                                                        <div>Account Name: <strong>Asentista Artisan Bakery</strong></div>
                                                        <div>Account Number: <strong style="letter-spacing: 0.04em;">1234-5678-9012</strong></div>
                                                    </div>
                                                    <p style="margin: 4px 0 0 0; font-size: 0.74rem; color: var(--color-text-muted);">Transfers are automatically verified by our kitchen team during preparation.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="notes">Special Preparation Notes</label>
                                    <textarea id="notes" name="special_notes" class="form-textarea" rows="2" placeholder="e.g. Please slice for breakfast sandwiches, deliver at 9 AM."></textarea>
                                </div>

                                <div class="cart-summary-line">
                                    <span>Total Bakery Items:</span>
                                    <span><?php echo $cartSummary['total_items']; ?> items</span>
                                </div>
                                <div class="cart-summary-line">
                                    <span>Estimated Prep Time:</span>
                                    <span>Fresh Baked Today</span>
                                </div>
                                <div class="cart-summary-total">
                                    <span>Grand Total:</span>
                                    <span style="color: var(--color-amber-accessible, #92400E); font-weight: 800;"><?php echo $cartSummary['total_formatted']; ?></span>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg btn-shimmer" style="width: 100%; margin-top: 1.2rem; font-weight: 800; letter-spacing: 0.03em; <?php echo !empty($cartSummary['has_out_of_stock']) ? 'opacity:0.5; cursor:not-allowed;' : ''; ?>" <?php echo !empty($cartSummary['has_out_of_stock']) ? 'disabled title="Adjust out-of-stock items first"' : ''; ?>>
                                    <span><?php echo !empty($cartSummary['has_out_of_stock']) ? 'Items Out of Stock - Adjust Cart' : 'Confirm & Place Bakery Order →'; ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const CSRF_TOKEN = '<?php echo get_csrf_token(); ?>';

        function selectPaymentMethod(method) {
            // Remove active from all cards
            document.querySelectorAll('.payment-option-card').forEach(c => c.classList.remove('active'));
            // Hide all instruction panels
            document.querySelectorAll('.instruction-panel').forEach(p => p.style.display = 'none');

            if (method === 'GCash') {
                document.getElementById('opt-gcash').classList.add('active');
                document.getElementById('radio-gcash').checked = true;
                document.getElementById('instruction-gcash').style.display = 'block';
            } else if (method === 'Bank Transfer') {
                document.getElementById('opt-bank').classList.add('active');
                document.getElementById('radio-bank').checked = true;
                document.getElementById('instruction-bank').style.display = 'block';
            } else {
                document.getElementById('opt-cod').classList.add('active');
                document.getElementById('radio-cod').checked = true;
                document.getElementById('instruction-cod').style.display = 'block';
            }
        }

        async function updateQtyAsync(cartId, qty) {
            const formData = new FormData();
            formData.append('action', 'update_qty');
            formData.append('cart_id', cartId);
            formData.append('quantity', qty);
            formData.append('csrf_token', CSRF_TOKEN);

            const res = await fetch('Cart/cart_action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Could not update quantity.');
            }
        }

        async function removeItemAsync(cartId) {
            if (!confirm('Remove this item from your cart?')) return;
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('cart_id', cartId);
            formData.append('csrf_token', CSRF_TOKEN);

            const res = await fetch('Cart/cart_action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            }
        }

        async function clearCartAsync() {
            if (!confirm('Are you sure you want to clear your shopping cart?')) return;
            const formData = new FormData();
            formData.append('action', 'clear');
            formData.append('csrf_token', CSRF_TOKEN);

            const res = await fetch('Cart/cart_action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            }
        }

        // Phone number restriction: only 11 digits, do not accept letters
        const custPhoneInput = document.getElementById('custPhone');
        if (custPhoneInput) {
            custPhoneInput.addEventListener('keydown', function(e) {
                if ([8, 9, 13, 27, 46].indexOf(e.keyCode) !== -1 ||
                    ((e.ctrlKey || e.metaKey) && [65, 67, 86, 88].indexOf(e.keyCode) !== -1) ||
                    (e.keyCode >= 35 && e.keyCode <= 40)) {
                    return;
                }
                if (e.key < '0' || e.key > '9') {
                    e.preventDefault();
                }
            });

            custPhoneInput.addEventListener('input', function() {
                const cleaned = this.value.replace(/\D/g, '').slice(0, 11);
                if (this.value !== cleaned) {
                    this.value = cleaned;
                }
            });

            custPhoneInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const pasteText = (e.clipboardData || window.clipboardData).getData('text') || '';
                const digits = pasteText.replace(/\D/g, '').slice(0, 11);
                const start = this.selectionStart;
                const end = this.selectionEnd;
                const val = this.value;
                this.value = (val.slice(0, start) + digits + val.slice(end)).replace(/\D/g, '').slice(0, 11);
                this.dispatchEvent(new Event('input'));
            });
        }
    </script>
</body>
</html>
