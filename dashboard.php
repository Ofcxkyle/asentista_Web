<?php
/**
 * Asentista Bakery - Orders & Bookings Management Dashboard
 * Pure PHP CRUD Portal connected to MySQL database with search, filters & export.
 */

require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/database/function.php';

$user = getCurrentUser();
$filterStatus = isset($_GET['status']) && !empty($_GET['status']) ? sanitize_input($_GET['status']) : null;
$searchKeyword = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';

// CSV Export Feature (Admin or Customer export)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportUserId = ($user && $user['role'] === 'customer') ? $user['id'] : null;
    $exportOrders = searchOrders($pdo, $searchKeyword, $filterStatus, $exportUserId);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=asentista_bakery_orders_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Customer Name', 'Phone', 'Items Ordered', 'Total Price', 'Quantity', 'Order Type', 'Date', 'Status', 'Special Notes', 'Created At']);

    foreach ($exportOrders as $row) {
        fputcsv($output, [
            $row['id'],
            $row['customer_name'],
            $row['customer_phone'],
            $row['item_name'],
            $row['item_price'],
            $row['quantity'],
            $row['order_type'],
            $row['reservation_date'],
            $row['status'],
            $row['special_notes'] ?? '',
            $row['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Handle Status Updates (Protected: Only authenticated Administrators can update order statuses)
$actionMsg = '';
$errorMsg  = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isAdmin()) {
        $errorMsg = "Unauthorized action: Only store administrators can modify or delete orders.";
    } elseif (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errorMsg = "Security token invalid or expired. Please refresh the page.";
    } else {
        if ($_POST['action'] === 'update_status') {
            $orderId = (int)$_POST['order_id'];
            $newStatus = sanitize_input($_POST['new_status']);
            if (updateOrderStatus($pdo, $orderId, $newStatus)) {
                $actionMsg = "Order #{$orderId} status successfully updated to <strong>{$newStatus}</strong>.";
            } else {
                $errorMsg = "Failed to update order status.";
            }
        } elseif ($_POST['action'] === 'delete_order') {
            $orderId = (int)$_POST['order_id'];
            if (deleteOrder($pdo, $orderId)) {
                $actionMsg = "Order #{$orderId} was removed from the database.";
            } else {
                $errorMsg = "Failed to delete order.";
            }
        }
    }
}

// Fetch filtered orders list
if ($user && $user['role'] === 'customer') {
    $orders = searchOrders($pdo, $searchKeyword, $filterStatus, $user['id']);
    $pageTitle = "My Bakery Orders";
} else {
    $orders = searchOrders($pdo, $searchKeyword, $filterStatus, null);
    $pageTitle = "All Customer Orders & Reservations";
}

// Calculate Global Stats
$allOrders = getAllOrders($pdo);
$totalCount = count($allOrders);
$pendingCount = count(array_filter($allOrders, fn($o) => $o['status'] === 'Pending'));
$confirmedCount = count(array_filter($allOrders, fn($o) => $o['status'] === 'Confirmed'));
$completedCount = count(array_filter($allOrders, fn($o) => $o['status'] === 'Completed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Asentista's Bakery</title>
    <!-- Website Favicon / Main Logo -->
    <link rel="icon" type="image/png" href="assets/ASENTISTA FINAL.png">
    <link rel="apple-touch-icon" href="assets/ASENTISTA FINAL.png">
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-container {
            padding: 3rem 0 5rem 0;
            min-height: 80vh;
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background-color: var(--color-white);
            padding: 1.5rem;
            border-radius: 6px;
            box-shadow: var(--shadow-sm);
            border-left: 5px solid var(--color-brown-deep);
        }
        .stat-card.pending { border-left-color: #F59E0B; }
        .stat-card.confirmed { border-left-color: #3B82F6; }
        .stat-card.completed { border-left-color: #10B981; }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-brown-deep);
            line-height: 1;
            margin-bottom: 0.3rem;
        }
        .stat-label {
            font-size: 0.78rem;
            color: var(--color-text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .filter-bar {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 0.5rem 1.1rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            background-color: var(--color-white);
            color: var(--color-brown-deep);
            border: 1px solid rgba(43, 27, 21, 0.2);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .filter-btn.active, .filter-btn:hover {
            background-color: var(--color-brown-deep);
            color: var(--color-white);
        }

        .search-orders-form {
            display: flex;
            gap: 6px;
        }
        .search-orders-input {
            padding: 0.5rem 0.85rem;
            border: 1px solid rgba(43, 27, 21, 0.25);
            border-radius: 4px;
            font-size: 0.85rem;
            width: 240px;
        }

        .orders-table-card {
            background-color: var(--color-white);
            border-radius: 6px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(43, 27, 21, 0.12);
            overflow: hidden;
        }
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .orders-table th {
            background-color: var(--color-brown-deep);
            color: var(--color-cream-light);
            font-family: var(--font-sans);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 1rem 1.2rem;
        }
        .orders-table td {
            padding: 1rem 1.2rem;
            border-bottom: 1px solid rgba(43, 27, 21, 0.08);
            font-size: 0.88rem;
            color: var(--color-brown-deep);
            vertical-align: middle;
        }
        .orders-table tr:hover {
            background-color: var(--color-cream-light);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
        }
        .status-Pending { background-color: #FEF3C7; color: #92400E; }
        .status-Confirmed { background-color: #DBEAFE; color: #1E40AF; }
        .status-Completed { background-color: #D1FAE5; color: #065F46; }
        .status-Cancelled { background-color: #FEE2E2; color: #991B1B; }

        .action-select-form {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .status-select {
            padding: 4px 8px;
            font-size: 0.78rem;
            border: 1px solid rgba(43, 27, 21, 0.3);
            border-radius: 3px;
            background: #fff;
        }
        .btn-status-update {
            padding: 4px 8px;
            background: var(--color-brown-deep);
            color: #fff;
            font-size: 0.75rem;
            border-radius: 3px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .orders-table-card { overflow-x: auto; }
            .search-orders-input { width: 100%; }
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
                <a href="index.php" class="nav-link">← Home Menu</a>
                <a href="cart.php" class="nav-link">🛒 Cart</a>
                <?php if ($user): ?>
                    <span style="font-size: 0.8rem; font-weight: 600;">👤 <?php echo htmlspecialchars($user['name']); ?> (<?php echo ucfirst($user['role']); ?>)</span>
                    <a href="logout.php" class="btn-submit-modal" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; text-decoration: none;">Logout</a>
                <?php else: ?>
                    <a href="auth.php" class="btn-submit-modal" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; text-decoration: none;">Login / Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1 style="font-family: var(--font-serif); font-size: 2.2rem; color: var(--color-brown-deep); margin-bottom: 0.3rem;">
                    <?php echo $pageTitle; ?>
                </h1>
                <p style="color: var(--color-text-muted); font-size: 0.9rem;">
                    Real-time MySQL Database view of customer orders, cart checkouts, and baking statuses.
                </p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <a href="dashboard.php?export=csv<?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?><?php echo $searchKeyword ? '&q=' . urlencode($searchKeyword) : ''; ?>" class="btn-clear-cart" style="padding: 0.8rem 1.2rem; font-weight: 700; background: var(--color-white); border-color: var(--color-brown-deep); text-decoration: none;">
                    📥 Export CSV
                </a>
                <a href="index.php#bread-menu" class="btn-book-now" style="text-decoration: none;">
                    + Place New Order
                </a>
            </div>
        </div>

        <?php if (!empty($actionMsg)): ?>
            <div style="background-color: #D1FAE5; color: #065F46; padding: 1rem 1.2rem; border-radius: 4px; margin-bottom: 1.5rem; border-left: 4px solid #10B981;">
                <?php echo $actionMsg; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div style="background-color: #FEE2E2; color: #991B1B; padding: 1rem 1.2rem; border-radius: 4px; margin-bottom: 1.5rem; border-left: 4px solid #DC2626;">
                <?php echo $errorMsg; ?>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalCount; ?></div>
                <div class="stat-label">Total Orders in DB</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-value"><?php echo $pendingCount; ?></div>
                <div class="stat-label">Pending Confirmation</div>
            </div>
            <div class="stat-card confirmed">
                <div class="stat-value"><?php echo $confirmedCount; ?></div>
                <div class="stat-label">Confirmed & Baking</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-value"><?php echo $completedCount; ?></div>
                <div class="stat-label">Completed / Picked Up</div>
            </div>
        </div>

        <!-- Controls Row: Filter Tabs & Search Bar -->
        <div class="controls-row">
            <div class="filter-bar">
                <a href="dashboard.php<?php echo $searchKeyword ? '?q=' . urlencode($searchKeyword) : ''; ?>" class="filter-btn <?php echo !$filterStatus ? 'active' : ''; ?>">All Orders</a>
                <a href="dashboard.php?status=Pending<?php echo $searchKeyword ? '&q=' . urlencode($searchKeyword) : ''; ?>" class="filter-btn <?php echo $filterStatus === 'Pending' ? 'active' : ''; ?>">Pending</a>
                <a href="dashboard.php?status=Confirmed<?php echo $searchKeyword ? '&q=' . urlencode($searchKeyword) : ''; ?>" class="filter-btn <?php echo $filterStatus === 'Confirmed' ? 'active' : ''; ?>">Confirmed</a>
                <a href="dashboard.php?status=Completed<?php echo $searchKeyword ? '&q=' . urlencode($searchKeyword) : ''; ?>" class="filter-btn <?php echo $filterStatus === 'Completed' ? 'active' : ''; ?>">Completed</a>
                <a href="dashboard.php?status=Cancelled<?php echo $searchKeyword ? '&q=' . urlencode($searchKeyword) : ''; ?>" class="filter-btn <?php echo $filterStatus === 'Cancelled' ? 'active' : ''; ?>">Cancelled</a>
            </div>

            <!-- Search Form -->
            <form method="GET" action="dashboard.php" class="search-orders-form">
                <?php if ($filterStatus): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                <?php endif; ?>
                <input type="text" name="q" class="search-orders-input" placeholder="Search name, phone, item..." value="<?php echo htmlspecialchars($searchKeyword); ?>">
                <button type="submit" class="btn-submit-modal" style="margin-top: 0; padding: 0.5rem 1rem; width: auto;">Search</button>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="orders-table-card">
            <?php if (empty($orders)): ?>
                <div style="padding: 3.5rem 2rem; text-align: center; color: var(--color-text-muted);">
                    <h3>No orders found matching your search.</h3>
                    <p style="margin-top: 0.5rem;">
                        <a href="dashboard.php" style="color: var(--color-brown-deep); text-decoration: underline;">Clear filters</a> or 
                        <a href="index.php" style="color: var(--color-brown-deep); text-decoration: underline;">Browse bakery menu</a>.
                    </p>
                </div>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer Info</th>
                            <th>Contact</th>
                            <th>Items & Breakdown</th>
                            <th>Total Price</th>
                            <th>Order Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Manage Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $row): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($row['id']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['customer_name']); ?></strong>
                                    <?php if (!empty($row['special_notes'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--color-text-muted); font-style: italic; max-width: 220px;">
                                            Note: "<?php echo htmlspecialchars($row['special_notes']); ?>"
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['customer_phone']); ?></td>
                                <td>
                                    <span style="font-weight: 600;"><?php echo htmlspecialchars($row['item_name']); ?></span>
                                </td>
                                <td style="font-weight: 700; color: var(--color-brown-deep);">
                                    ₱<?php echo number_format($row['item_price'], 2); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['order_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['reservation_date']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($row['status']); ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (isAdmin()): ?>
                                        <form method="POST" action="dashboard.php" class="action-select-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                            <select name="new_status" class="status-select">
                                                <option value="Pending" <?php echo $row['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Confirmed" <?php echo $row['status'] === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="Completed" <?php echo $row['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="Cancelled" <?php echo $row['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" class="btn-status-update">Save</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size: 0.8rem; color: var(--color-text-muted); font-style: italic;">
                                            Customer Order
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
