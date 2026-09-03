<?php
/**
 * Asentista Bakery - Shopping Cart & Checkout Page
 * Full cart management & multi-item checkout connected to MySQL database.
 */

require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/database/function.php';

$currentUser = getCurrentUser();
$cartSummary = getCartSummary($pdo);
$cartItems = $cartSummary['items'];

$errorMsg = '';
$successMsg = '';

// Handle Checkout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_action'])) {
    if (!isLoggedIn()) {
        header('Location: auth.php?redirect=cart.php&msg=login_to_order');
        exit;
    }
    $checkoutResult = checkoutCart($pdo, $_POST);
    if ($checkoutResult['success']) {
        $_SESSION['flash_order'] = $checkoutResult['data'];
        header('Location: success.php?order_id=' . $checkoutResult['order_id']);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Bakery Cart - Asentista's Bakery</title>
    <!-- Website Favicon / Main Logo -->
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
            border: 1px solid rgba(43, 27, 21, 0.2);
            border-radius: 4px;
            overflow: hidden;
            width: fit-content;
        }
        .qty-btn {
            background: var(--color-cream-light);
            color: var(--color-brown-deep);
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: background-color 0.2s ease;
        }
        .qty-btn:hover {
            background: var(--color-yellow);
        }
        .qty-val {
            width: 32px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .btn-remove-item {
            color: #DC2626;
            background: transparent;
            font-size: 0.8rem;
            cursor: pointer;
            border: none;
            text-decoration: underline;
        }
        .btn-remove-item:hover {
            color: #991B1B;
        }
        .cart-footer-bar {
            padding: 1.25rem 1.5rem;
            background-color: var(--color-cream-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-clear-cart {
            font-size: 0.78rem;
            color: var(--color-text-muted);
            cursor: pointer;
            background: transparent;
            border: 1px solid rgba(43, 27, 21, 0.2);
            padding: 4px 10px;
            border-radius: 3px;
        }
        .btn-clear-cart:hover {
            background: #FEE2E2;
            color: #991B1B;
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
<body>

    <!-- Header Navigation -->
    <nav class="site-nav">
        <div class="container nav-container">
            <a href="index.php" class="brand-logo-wrap">
                <div class="brand-svg-logo">
                    <img src="assets/ASENTISTA FINAL.png" alt="Asentista's Bakery Logo" class="brand-logo-img">
                </div>
                <div class="brand-text-block">
                    <span class="brand-title">ASENTISTA'S</span>
                    <span class="brand-subtitle">BAKERY</span>
                </div>
            </a>
            
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="index.php" class="nav-link">← Continue Shopping</a>
                <a href="dashboard.php" class="nav-link">Orders Portal</a>
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
            <div class="cart-items-card" style="padding: 4rem 2rem; text-align: center;">
                <span style="font-size: 3.5rem; display: block; margin-bottom: 1rem;">🧺</span>
                <h2 style="font-family: var(--font-serif); font-size: 1.6rem; color: var(--color-brown-deep); margin-bottom: 0.5rem;">
                    Your Bakery Cart is Empty
                </h2>
                <p style="color: var(--color-text-muted); font-size: 0.95rem; margin-bottom: 2rem;">
                    Explore our fresh daily sourdough, crunchy crusts, warm rolls, and specialty beverages!
                </p>
                <a href="index.php#bread-menu" class="btn-book-now" style="text-decoration: none; padding: 1rem 2.5rem;">
                    Browse Bread Menu →
                </a>
            </div>
        <?php else: ?>
            <div class="cart-grid-layout">
                <!-- Left: Cart Items Table -->
                <div class="cart-items-card">
                    <div class="card-header-bar">
                        <span class="card-header-title">Items in Your Cart (<?php echo $cartSummary['total_items']; ?>)</span>
                        <button type="button" class="btn-clear-cart" onclick="clearCartAsync()">Clear Cart</button>
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

                <!-- Right: Fast Checkout Form (Account Required) -->
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

                                <a href="auth.php?redirect=cart.php&msg=login_to_order" class="btn-submit-modal shimmer-btn" style="text-decoration: none; padding: 1rem; font-size: 0.92rem; margin-top: 1.2rem;">
                                    Sign In / Register to Place Order →
                                </a>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($cartSummary['has_out_of_stock'])): ?>
                                <div style="background:#FFEBEE; border:1px solid #FFCDD2; color:#C62828; padding:0.85rem 1rem; border-radius:4px; font-size:0.85rem; margin-bottom:1.2rem; line-height:1.4;">
                                    ⚠️ <strong>Inventory Alert:</strong> One or more items in your cart exceed available bakery inventory. Please adjust quantities or remove out-of-stock items before placing your order.
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="cart.php">
                                <input type="hidden" name="checkout_action" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

                                <div class="form-group">
                                    <label class="form-label" for="custName">Customer Full Name *</label>
                                    <input type="text" id="custName" name="customer_name" class="form-input" placeholder="e.g. Maria Santos" required value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="custPhone">Phone Number *</label>
                                    <input type="tel" id="custPhone" name="customer_phone" class="form-input" placeholder="e.g. 0994 005 8425" required value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
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

                                <button type="submit" class="btn-submit-modal shimmer-btn" style="font-size: 0.95rem; padding: 1rem; margin-top: 1rem; <?php echo !empty($cartSummary['has_out_of_stock']) ? 'opacity:0.5; cursor:not-allowed;' : ''; ?>" <?php echo !empty($cartSummary['has_out_of_stock']) ? 'disabled title="Adjust out-of-stock items first"' : ''; ?>>
                                    <?php echo !empty($cartSummary['has_out_of_stock']) ? 'Items Out of Stock - Adjust Cart' : 'Place Order (Save to DB) →'; ?>
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

        async function updateQtyAsync(cartId, qty) {
            const formData = new FormData();
            formData.append('action', 'update_qty');
            formData.append('cart_id', cartId);
            formData.append('quantity', qty);
            formData.append('csrf_token', CSRF_TOKEN);

            const res = await fetch('cart_action.php', { method: 'POST', body: formData });
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

            const res = await fetch('cart_action.php', { method: 'POST', body: formData });
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

            const res = await fetch('cart_action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            }
        }
    </script>
</body>
</html>
