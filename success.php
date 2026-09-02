<?php
/**
 * Asentista Bakery - Order Success & Receipt Confirmation
 */

require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/database/function.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$order = null;

if ($orderId > 0) {
    $order = getOrderById($pdo, $orderId);
}

if (!$order && isset($_SESSION['flash_order'])) {
    $order = $_SESSION['flash_order'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - Asentista's Bakery</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .receipt-container {
            max-width: 620px;
            margin: 4rem auto;
            background-color: var(--color-white);
            border-radius: 6px;
            box-shadow: 0 15px 35px rgba(43, 27, 21, 0.12);
            border: 1px solid rgba(43, 27, 21, 0.15);
            overflow: hidden;
        }
        .receipt-header {
            background-color: var(--color-brown-deep);
            color: var(--color-white);
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .receipt-badge-check {
            width: 56px;
            height: 56px;
            background-color: var(--color-yellow);
            color: var(--color-brown-deep);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        .receipt-body {
            padding: 2.5rem 2rem;
        }
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px dashed rgba(43, 27, 21, 0.15);
            font-size: 0.92rem;
            gap: 1rem;
        }
        .receipt-label {
            color: var(--color-text-muted);
            font-weight: 500;
        }
        .receipt-value {
            color: var(--color-brown-deep);
            font-weight: 700;
            text-align: right;
        }
        .receipt-status-pill {
            background-color: #FEF3C7;
            color: #92400E;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-block;
        }
        .receipt-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        @media print {
            .site-nav, .receipt-actions { display: none !important; }
            .receipt-container { box-shadow: none; border: 1px solid #000; margin: 0 auto; }
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="site-nav">
        <div class="container nav-container">
            <a href="index.php" class="brand-logo-wrap">
                <div class="brand-svg-logo">
                    <svg width="38" height="46" viewBox="0 0 44 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="22" cy="18" rx="12" ry="10" fill="#FFAE34" />
                        <ellipse cx="22" cy="20" rx="8" ry="5" fill="#FFAE34" opacity="0.7" />
                        <rect x="12" y="20" width="20" height="5" rx="1" fill="#C8A882" />
                        <text x="22" y="50" text-anchor="middle" font-family="'Times New Roman', serif" font-size="36" font-weight="bold" fill="#2B1B15">A</text>
                    </svg>
                </div>
                <div class="brand-text-block">
                    <span class="brand-title">ASENTISTA'S</span>
                    <span class="brand-subtitle">BAKERY</span>
                </div>
            </a>
            <div>
                <a href="index.php" class="nav-link">← Back to Home</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="receipt-container">
            <div class="receipt-header">
                <div class="receipt-badge-check">✓</div>
                <h1 style="font-family: var(--font-serif); font-size: 1.8rem; color: var(--color-cream-light); margin-bottom: 0.4rem;">
                    Bakery Order Confirmed!
                </h1>
                <p style="font-size: 0.85rem; opacity: 0.85; color: var(--color-cream-light);">
                    Your order details have been saved directly to our database.
                </p>
            </div>

            <div class="receipt-body">
                <?php if ($order): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Order Reference #</span>
                        <span class="receipt-value">#<?php echo htmlspecialchars($order['id'] ?? $order['order_id'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Customer Name</span>
                        <span class="receipt-value"><?php echo htmlspecialchars($order['customer_name'] ?? ''); ?></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Contact Phone</span>
                        <span class="receipt-value"><?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Items Ordered</span>
                        <span class="receipt-value"><?php echo htmlspecialchars($order['item_name'] ?? ''); ?></span>
                    </div>
                    <?php if (isset($order['item_price']) && $order['item_price'] > 0): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Total Amount</span>
                        <span class="receipt-value" style="color: var(--color-yellow-hover); font-size: 1.1rem;">
                            ₱<?php echo number_format($order['item_price'], 2); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Order Type</span>
                        <span class="receipt-value"><?php echo htmlspecialchars($order['order_type'] ?? 'In-Store Pickup'); ?></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Scheduled Date</span>
                        <span class="receipt-value"><?php echo htmlspecialchars($order['reservation_date'] ?? ''); ?></span>
                    </div>
                    <?php if (!empty($order['special_notes'])): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Special Notes</span>
                        <span class="receipt-value"><?php echo htmlspecialchars($order['special_notes']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Current Status</span>
                        <span class="receipt-value">
                            <span class="receipt-status-pill"><?php echo htmlspecialchars($order['status'] ?? 'Pending'); ?></span>
                        </span>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--color-text-muted);">
                        No active order receipt found.
                    </p>
                <?php endif; ?>

                <div class="receipt-actions">
                    <button type="button" class="btn-submit-modal" style="flex:1; background: var(--color-cream-light); color: var(--color-brown-deep);" onclick="window.print()">
                        🖨️ Print Receipt
                    </button>
                    <a href="index.php" class="btn-submit-modal" style="flex:1; text-align: center; text-decoration: none;">
                        ← Back to Home
                    </a>
                    <a href="dashboard.php" class="btn-submit-modal" style="flex:1; background-color: var(--color-brown-mid); text-align: center; text-decoration: none;">
                        Orders Portal →
                    </a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
