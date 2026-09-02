<?php
/**
 * Asentista Bakery - Executive Admin Console Home Page
 * Pure PHP implementation providing full bakery management: Analytics, Live Order Dispatching, Product Catalog CRUD, and Customer Management.
 */

require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/database/function.php';

// Strict Admin Access Guard
if (!isAdmin()) {
    header('Location: auth.php?msg=admin_required&redirect=admin.php');
    exit;
}

$currentUser = getCurrentUser();
$actionMsg = '';
$errorMsg = '';
$activeTab = isset($_GET['tab']) ? sanitize_input($_GET['tab']) : 'orders';

// CSV Export Feature
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filterStatus = isset($_GET['status']) ? sanitize_input($_GET['status']) : null;
    $searchKw = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';
    $exportOrders = searchOrders($pdo, $searchKw, $filterStatus, null);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=asentista_orders_export_' . date('Y-m-d_His') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Customer Name', 'Phone', 'Items Ordered', 'Total Price', 'Quantity', 'Order Type', 'Date', 'Status', 'Special Notes', 'Created At']);

    foreach ($exportOrders as $row) {
        fputcsv($output, [
            $row['id'],
            $row['customer_name'],
            $row['customer_phone'],
            $row['item_name'],
            $row['item_price'],
            $row['quantity'] ?? 1,
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

// Handle Form Submissions (Order Status Updates & Product CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
    $action = $_POST['admin_action'];

    if ($action === 'update_order_status') {
        $orderId = (int)$_POST['order_id'];
        $newStatus = sanitize_input($_POST['new_status']);
        if (updateOrderStatus($pdo, $orderId, $newStatus)) {
            $actionMsg = "Order #{$orderId} status successfully updated to <strong>{$newStatus}</strong>.";
            $activeTab = 'orders';
        } else {
            $errorMsg = "Failed to update order status.";
        }
    } elseif ($action === 'delete_order') {
        $orderId = (int)$_POST['order_id'];
        if (deleteOrder($pdo, $orderId)) {
            $actionMsg = "Order #{$orderId} removed from database.";
            $activeTab = 'orders';
        }
    } elseif ($action === 'add_product') {
        $pName     = $_POST['product_name'] ?? '';
        $pCategory = $_POST['product_category'] ?? 'Bread';
        $pPrice    = (float)($_POST['product_price'] ?? 0);
        $pDesc     = $_POST['product_desc'] ?? '';
        $pImage    = $_POST['product_image'] ?? '';
        $pFeatured = isset($_POST['is_featured']) ? 1 : 0;

        if (empty($pName) || $pPrice <= 0) {
            $errorMsg = "Product name and a valid price are required.";
        } else {
            if (addProduct($pdo, $pName, $pCategory, $pPrice, $pDesc, $pImage, $pFeatured)) {
                $actionMsg = "New bakery item <strong>{$pName}</strong> added to the catalog!";
                $activeTab = 'products';
            } else {
                $errorMsg = "Failed to add product.";
            }
        }
    } elseif ($action === 'update_product') {
        $pId       = (int)($_POST['product_id'] ?? 0);
        $pName     = $_POST['product_name'] ?? '';
        $pCategory = $_POST['product_category'] ?? 'Bread';
        $pPrice    = (float)($_POST['product_price'] ?? 0);
        $pDesc     = $_POST['product_desc'] ?? '';
        $pImage    = $_POST['product_image'] ?? '';
        $pFeatured = isset($_POST['is_featured']) ? 1 : 0;

        if ($pId > 0 && updateProduct($pdo, $pId, $pName, $pCategory, $pPrice, $pDesc, $pImage, $pFeatured)) {
            $actionMsg = "Product <strong>{$pName}</strong> updated successfully!";
            $activeTab = 'products';
        } else {
            $errorMsg = "Failed to update product.";
        }
    } elseif ($action === 'delete_product') {
        $pId = (int)($_POST['product_id'] ?? 0);
        if ($pId > 0 && deleteProduct($pdo, $pId)) {
            $actionMsg = "Product #{$pId} was deleted from catalog.";
            $activeTab = 'products';
        }
    }
}

// Load Data
$metrics = getAdminMetrics($pdo);
$orderSearchKw = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';
$orderStatusFilter = isset($_GET['status']) && !empty($_GET['status']) ? sanitize_input($_GET['status']) : null;
$ordersList = searchOrders($pdo, $orderSearchKw, $orderStatusFilter, null);
$productsList = getAllProducts($pdo);
$customersList = getAllCustomers($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Command Center - Asentista's Bakery</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-page-wrap {
            background-color: #F7EFE8;
            min-height: 100vh;
            padding-bottom: 5rem;
        }
        /* Top Command Navigation */
        .admin-nav-bar {
            background-color: var(--color-brown-deep);
            color: var(--color-white);
            padding: 0.9rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid var(--color-yellow);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-md);
        }
        .admin-brand-block {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .admin-badge-pill {
            background: var(--color-yellow);
            color: var(--color-brown-deep);
            font-size: 0.72rem;
            font-weight: 800;
            padding: 3px 9px;
            border-radius: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .admin-top-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-store-preview {
            background: rgba(255, 255, 255, 0.12);
            color: var(--color-cream-light);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: var(--transition-fast);
        }
        .btn-store-preview:hover {
            background: var(--color-yellow);
            color: var(--color-brown-deep);
        }
        .btn-admin-logout {
            background: #DC2626;
            color: #fff;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.78rem;
            font-weight: 700;
            transition: var(--transition-fast);
        }
        .btn-admin-logout:hover {
            background: #B91C1C;
        }

        /* Executive Header Banner */
        .admin-hero-banner {
            background: linear-gradient(135deg, #2B1B15 0%, #3D2B22 100%);
            color: var(--color-white);
            padding: 2.5rem 0 3.5rem 0;
            margin-bottom: -2rem;
        }
        .admin-hero-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .admin-hero-title {
            font-family: var(--font-serif);
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--color-cream-light);
            line-height: 1.2;
        }
        .admin-hero-subtitle {
            font-size: 0.88rem;
            color: var(--color-cream);
            opacity: 0.85;
            margin-top: 4px;
        }

        /* KPI Cards Grid */
        .kpi-cards-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.2rem;
            margin-bottom: 2.2rem;
            position: relative;
            z-index: 2;
        }
        .kpi-card {
            background-color: var(--color-white);
            padding: 1.4rem 1.2rem;
            border-radius: 6px;
            box-shadow: var(--shadow-md);
            border-top: 4px solid var(--color-brown-deep);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: var(--transition-fast);
        }
        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        .kpi-card.revenue { border-top-color: #10B981; }
        .kpi-card.pending { border-top-color: #F59E0B; }
        .kpi-card.baking { border-top-color: #3B82F6; }
        .kpi-card.customers { border-top-color: #8B5CF6; }

        .kpi-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--color-brown-deep);
            line-height: 1;
            margin-top: 6px;
        }
        .kpi-label {
            font-size: 0.72rem;
            color: var(--color-text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* Tabbed Workspace */
        .admin-tab-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid rgba(43, 27, 21, 0.15);
            padding-bottom: 4px;
        }
        .admin-tab-btn {
            padding: 0.8rem 1.4rem;
            font-size: 0.88rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: var(--color-text-muted);
            background: transparent;
            border-radius: 6px 6px 0 0;
            cursor: pointer;
            transition: var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .admin-tab-btn.active, .admin-tab-btn:hover {
            background-color: var(--color-white);
            color: var(--color-brown-deep);
            box-shadow: 0 -2px 10px rgba(43, 27, 21, 0.05);
        }
        .admin-tab-btn.active {
            border-bottom: 3px solid var(--color-yellow);
        }

        /* Content Cards & Tables */
        .admin-content-card {
            background-color: var(--color-white);
            border-radius: 6px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(43, 27, 21, 0.1);
            overflow: hidden;
            padding: 1.8rem;
            margin-bottom: 2rem;
        }
        .card-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .card-heading {
            font-family: var(--font-serif);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--color-brown-deep);
        }

        /* Data Tables */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .admin-table th {
            background-color: var(--color-brown-deep);
            color: var(--color-cream-light);
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.85rem 1rem;
        }
        .admin-table td {
            padding: 0.95rem 1rem;
            border-bottom: 1px solid rgba(43, 27, 21, 0.08);
            font-size: 0.88rem;
            vertical-align: middle;
        }
        .admin-table tr:hover {
            background-color: var(--color-cream-lighter);
        }

        /* Status Badge Pills */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }
        .status-Pending { background-color: #FEF3C7; color: #92400E; }
        .status-Confirmed { background-color: #DBEAFE; color: #1E40AF; }
        .status-Completed { background-color: #D1FAE5; color: #065F46; }
        .status-Cancelled { background-color: #FEE2E2; color: #991B1B; }

        .btn-table-action {
            padding: 5px 10px;
            font-size: 0.74rem;
            font-weight: 700;
            border-radius: 3px;
            cursor: pointer;
            transition: var(--transition-fast);
        }

        /* Product Grid View */
        .products-crud-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }
        .product-crud-card {
            background: var(--color-cream-lighter);
            border: 1px solid rgba(43, 27, 21, 0.12);
            border-radius: 6px;
            padding: 1.2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: var(--transition-fast);
        }
        .product-crud-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            background: var(--color-white);
        }
        .product-crud-thumb {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 0.8rem;
        }

        @media (max-width: 1100px) {
            .kpi-cards-grid { grid-template-columns: repeat(3, 1fr); }
            .products-crud-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .kpi-cards-grid { grid-template-columns: 1fr 1fr; }
            .products-crud-grid { grid-template-columns: 1fr; }
            .admin-table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>

    <!-- Executive Nav Bar -->
    <nav class="admin-nav-bar">
        <div class="admin-brand-block">
            <div class="brand-svg-logo">
                <svg width="34" height="42" viewBox="0 0 44 52" fill="none">
                    <ellipse cx="22" cy="18" rx="12" ry="10" fill="#FFAE34" />
                    <rect x="12" y="20" width="20" height="5" rx="1" fill="#C8A882" />
                    <text x="22" y="50" text-anchor="middle" font-family="serif" font-size="36" font-weight="bold" fill="#FFFFFF">A</text>
                </svg>
            </div>
            <div>
                <span class="brand-title" style="color: var(--color-white); font-size: 1rem;">ASENTISTA'S</span>
                <span class="brand-subtitle" style="color: var(--color-yellow); font-size: 0.75rem;">BAKERY & COFFEE</span>
            </div>
            <span class="admin-badge-pill">👑 Master Admin Console</span>
        </div>

        <div class="admin-top-actions">
            <a href="index.php" class="btn-store-preview" target="_blank" title="Open Customer Storefront">
                👁️ View Live Storefront ↗
            </a>
            <span style="font-size: 0.8rem; opacity: 0.85;">
                Logged in: <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong>
            </span>
            <a href="logout.php" class="btn-admin-logout" title="Sign Out">
                Logout
            </a>
        </div>
    </nav>

    <!-- Hero Header -->
    <header class="admin-hero-banner">
        <div class="container admin-hero-flex">
            <div>
                <h1 class="admin-hero-title">Executive Operations Center</h1>
                <p class="admin-hero-subtitle">
                    Real-time baking dispatch queue, live product catalog, and revenue intelligence connected to XAMPP MySQL.
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="admin.php?export=csv" class="btn-store-preview" style="background: var(--color-yellow); color: var(--color-brown-deep); border: none; font-weight: 700;">
                    📥 Export CSV Report
                </a>
                <button type="button" class="btn-store-preview" onclick="openAddProductModal()">
                    + Add New Bakery Product
                </button>
            </div>
        </div>
    </header>

    <main class="container admin-page-wrap" style="padding-top: 3.5rem;">
        <!-- Flash Feedback Messages -->
        <?php if (!empty($actionMsg)): ?>
            <div style="background-color: #D1FAE5; color: #065F46; padding: 1rem 1.2rem; border-radius: 6px; margin-bottom: 1.5rem; border-left: 4px solid #10B981; box-shadow: var(--shadow-sm);">
                <?php echo $actionMsg; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div style="background-color: #FEE2E2; color: #991B1B; padding: 1rem 1.2rem; border-radius: 6px; margin-bottom: 1.5rem; border-left: 4px solid #DC2626; box-shadow: var(--shadow-sm);">
                <?php echo $errorMsg; ?>
            </div>
        <?php endif; ?>

        <!-- Executive KPI Metrics Cards -->
        <section class="kpi-cards-grid">
            <div class="kpi-card revenue">
                <span class="kpi-label">Total Revenue</span>
                <span class="kpi-value" style="color: #059669;"><?php echo $metrics['revenue_formatted']; ?></span>
            </div>
            <div class="kpi-card pending">
                <span class="kpi-label">Pending Confirmation</span>
                <span class="kpi-value" style="color: #D97706;"><?php echo $metrics['pending_orders']; ?></span>
            </div>
            <div class="kpi-card baking">
                <span class="kpi-label">Confirmed & Baking</span>
                <span class="kpi-value" style="color: #2563EB;"><?php echo $metrics['confirmed_orders']; ?></span>
            </div>
            <div class="kpi-card customers">
                <span class="kpi-label">Registered Customers</span>
                <span class="kpi-value" style="color: #7C3AED;"><?php echo $metrics['customer_count']; ?></span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Catalog Menu Items</span>
                <span class="kpi-value"><?php echo $metrics['product_count']; ?></span>
            </div>
        </section>

        <!-- Navigation Tabs -->
        <div class="admin-tab-nav">
            <button type="button" class="admin-tab-btn <?php echo $activeTab === 'orders' ? 'active' : ''; ?>" onclick="switchAdminTab('orders')">
                📋 Live Orders Queue (<?php echo count($ordersList); ?>)
            </button>
            <button type="button" class="admin-tab-btn <?php echo $activeTab === 'products' ? 'active' : ''; ?>" onclick="switchAdminTab('products')">
                🥖 Product Catalog Manager (<?php echo count($productsList); ?>)
            </button>
            <button type="button" class="admin-tab-btn <?php echo $activeTab === 'customers' ? 'active' : ''; ?>" onclick="switchAdminTab('customers')">
                👥 Customer Directory (<?php echo count($customersList); ?>)
            </button>
        </div>

        <!-- ==============================================================================
             TAB 1: LIVE ORDERS PIPELINE
             ============================================================================== -->
        <section id="tab-orders" class="admin-content-card" style="display: <?php echo $activeTab === 'orders' ? 'block' : 'none'; ?>;">
            <div class="card-title-row">
                <div>
                    <h2 class="card-heading">Baking & Delivery Dispatch Board</h2>
                    <p style="font-size: 0.85rem; color: var(--color-text-muted);">Manage real-time customer orders, update statuses, and track special requests.</p>
                </div>

                <!-- Filters & Search -->
                <form method="GET" action="admin.php" style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <input type="hidden" name="tab" value="orders">
                    <select name="status" class="form-select" style="width: auto; padding: 0.45rem 0.8rem; font-size: 0.82rem;" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php echo $orderStatusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Confirmed" <?php echo $orderStatusFilter === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="Completed" <?php echo $orderStatusFilter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $orderStatusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <input type="text" name="q" class="form-input" style="width: 220px; padding: 0.45rem 0.8rem; font-size: 0.82rem;" placeholder="Search name, phone, item..." value="<?php echo htmlspecialchars($orderSearchKw); ?>">
                    <button type="submit" class="btn-table-action" style="background: var(--color-brown-deep); color: #fff;">Search</button>
                    <?php if ($orderSearchKw || $orderStatusFilter): ?>
                        <a href="admin.php?tab=orders" class="btn-table-action" style="background: #E5E7EB; color: #374151; text-decoration: none; display: flex; align-items: center;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($ordersList)): ?>
                <div style="padding: 3rem; text-align: center; color: var(--color-text-muted);">
                    <p style="font-size: 1.1rem; font-weight: 600;">No orders found matching the filter criteria.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Items Breakdown</th>
                            <th>Total (₱)</th>
                            <th>Order Type</th>
                            <th>Pickup Date</th>
                            <th>Status</th>
                            <th>Quick Status Transition</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordersList as $ord): ?>
                            <tr>
                                <td><strong>#<?php echo $ord['id']; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($ord['customer_name']); ?></strong>
                                    <?php if (!empty($ord['special_notes'])): ?>
                                        <div style="font-size: 0.76rem; color: var(--color-text-muted); font-style: italic; max-width: 200px;">
                                            Note: "<?php echo htmlspecialchars($ord['special_notes']); ?>"
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($ord['customer_phone']); ?></td>
                                <td><span style="font-weight: 600;"><?php echo htmlspecialchars($ord['item_name']); ?></span></td>
                                <td style="font-weight: 700; color: var(--color-brown-deep);">
                                    ₱<?php echo number_format($ord['item_price'], 2); ?>
                                </td>
                                <td><?php echo htmlspecialchars($ord['order_type']); ?></td>
                                <td><?php echo htmlspecialchars($ord['reservation_date']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($ord['status']); ?>">
                                        <?php echo htmlspecialchars($ord['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" action="admin.php" style="display: flex; gap: 4px; align-items: center;">
                                        <input type="hidden" name="admin_action" value="update_order_status">
                                        <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                                        <select name="new_status" class="form-select" style="padding: 3px 6px; font-size: 0.74rem;">
                                            <option value="Pending" <?php echo $ord['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Confirmed" <?php echo $ord['status'] === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="Completed" <?php echo $ord['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Cancelled" <?php echo $ord['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" class="btn-table-action" style="background: var(--color-brown-deep); color: #fff;">Save</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="admin.php" onsubmit="return confirm('Delete order #<?php echo $ord['id']; ?> permanently?')">
                                        <input type="hidden" name="admin_action" value="delete_order">
                                        <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                                        <button type="submit" class="btn-table-action" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA;">✕</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- ==============================================================================
             TAB 2: PRODUCT CATALOG MANAGEMENT (CRUD)
             ============================================================================== -->
        <section id="tab-products" class="admin-content-card" style="display: <?php echo $activeTab === 'products' ? 'block' : 'none'; ?>;">
            <div class="card-title-row">
                <div>
                    <h2 class="card-heading">Bakery & Beverage Menu Manager</h2>
                    <p style="font-size: 0.85rem; color: var(--color-text-muted);">Add, modify, and adjust prices for all catalog items in real time.</p>
                </div>
                <button type="button" class="btn-submit-modal" style="width: auto; padding: 0.6rem 1.2rem; font-size: 0.82rem; margin: 0;" onclick="openAddProductModal()">
                    + Add New Item to Menu
                </button>
            </div>

            <div class="products-crud-grid">
                <?php foreach ($productsList as $prod): ?>
                    <div class="product-crud-card">
                        <div>
                            <img src="<?php echo htmlspecialchars($prod['image']); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" class="product-crud-thumb">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 4px;">
                                <h3 style="font-family: var(--font-serif); font-size: 1.05rem; font-weight: 700; color: var(--color-brown-deep);">
                                    <?php echo htmlspecialchars($prod['name']); ?>
                                </h3>
                                <span style="font-size: 0.95rem; font-weight: 700; color: #059669;">
                                    ₱<?php echo number_format($prod['price'], 2); ?>
                                </span>
                            </div>
                            <span style="font-size: 0.72rem; background: #E5E7EB; padding: 2px 6px; border-radius: 3px; font-weight: 600; text-transform: uppercase;">
                                <?php echo htmlspecialchars($prod['category']); ?>
                            </span>
                            <p style="font-size: 0.78rem; color: var(--color-text-muted); margin-top: 8px; line-height: 1.4;">
                                <?php echo htmlspecialchars($prod['description']); ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: 8px; margin-top: 1rem;">
                            <button type="button" class="btn-table-action" style="flex: 1; background: var(--color-brown-deep); color: #fff;" onclick='openEditProductModal(<?php echo json_encode($prod); ?>)'>
                                ✏️ Edit
                            </button>
                            <form method="POST" action="admin.php" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars(addslashes($prod['name'])); ?>?');" style="flex: 1;">
                                <input type="hidden" name="admin_action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                <button type="submit" class="btn-table-action" style="width: 100%; background: #FEE2E2; color: #DC2626;">
                                    🗑️ Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ==============================================================================
             TAB 3: CUSTOMER DIRECTORY
             ============================================================================== -->
        <section id="tab-customers" class="admin-content-card" style="display: <?php echo $activeTab === 'customers' ? 'block' : 'none'; ?>;">
            <div class="card-title-row">
                <div>
                    <h2 class="card-heading">Registered Customer Directory</h2>
                    <p style="font-size: 0.85rem; color: var(--color-text-muted);">Customer records, contact information, order count, and lifetime value.</p>
                </div>
            </div>

            <?php if (empty($customersList)): ?>
                <p style="padding: 2rem; text-align: center; color: var(--color-text-muted);">No customers registered yet.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Phone Number</th>
                            <th>Orders Placed</th>
                            <th>Total Spent (₱)</th>
                            <th>Registered Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customersList as $cust): ?>
                            <tr>
                                <td><strong>#<?php echo $cust['id']; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($cust['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($cust['email']); ?></td>
                                <td><?php echo htmlspecialchars($cust['phone'] ?: 'N/A'); ?></td>
                                <td><span style="font-weight: 700;"><?php echo $cust['total_orders']; ?> orders</span></td>
                                <td style="font-weight: 700; color: #059669;">₱<?php echo number_format($cust['total_spent'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($cust['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>

    <!-- Product Modal (Add / Edit) -->
    <div class="modal-backdrop" id="productFormModal">
        <div class="modal-window">
            <div class="modal-header">
                <h3 class="modal-title" id="productModalHeading">Add New Bakery Item</h3>
                <button type="button" class="modal-close-btn" onclick="closeProductModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="admin.php">
                    <input type="hidden" name="admin_action" id="prodFormAction" value="add_product">
                    <input type="hidden" name="product_id" id="prodFormId" value="">

                    <div class="form-group">
                        <label class="form-label" for="prodName">Item Name *</label>
                        <input type="text" id="prodName" name="product_name" class="form-input" placeholder="e.g. Sourdough Rye Loaf" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="prodCategory">Category *</label>
                            <select id="prodCategory" name="product_category" class="form-select">
                                <option value="Bread">Bread</option>
                                <option value="Beverage">Beverage</option>
                                <option value="Organic Special">Organic Special</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="prodPrice">Price (₱) *</label>
                            <input type="number" step="0.01" id="prodPrice" name="product_price" class="form-input" placeholder="35.00" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prodImage">Image Asset Path</label>
                        <input type="text" id="prodImage" name="product_image" class="form-input" placeholder="assets/breads-e1656042972619.jpg" value="assets/breads-e1656042972619.jpg">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prodDesc">Description</label>
                        <textarea id="prodDesc" name="product_desc" class="form-textarea" rows="3" placeholder="Handcrafted with organic wheat flour..."></textarea>
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="prodFeatured" name="is_featured" value="1" checked>
                        <label for="prodFeatured" style="font-size: 0.85rem; font-weight: 600; cursor: pointer;">Show in Featured Menu</label>
                    </div>

                    <button type="submit" class="btn-submit-modal" id="prodSubmitBtn">
                        Save to Database Catalog →
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchAdminTab(tabName) {
            document.querySelectorAll('.admin-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('section[id^="tab-"]').forEach(sec => sec.style.display = 'none');

            const targetBtn = Array.from(document.querySelectorAll('.admin-tab-btn')).find(b => b.textContent.toLowerCase().includes(tabName));
            if (targetBtn) targetBtn.classList.add('active');

            const targetSec = document.getElementById('tab-' + tabName);
            if (targetSec) targetSec.style.display = 'block';

            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        }

        const modal = document.getElementById('productFormModal');

        function openAddProductModal() {
            document.getElementById('productModalHeading').textContent = 'Add New Bakery Item';
            document.getElementById('prodFormAction').value = 'add_product';
            document.getElementById('prodFormId').value = '';
            document.getElementById('prodName').value = '';
            document.getElementById('prodCategory').value = 'Bread';
            document.getElementById('prodPrice').value = '';
            document.getElementById('prodImage').value = 'assets/breads-e1656042972619.jpg';
            document.getElementById('prodDesc').value = '';
            document.getElementById('prodFeatured').checked = true;
            document.getElementById('prodSubmitBtn').textContent = 'Add to Database Catalog →';

            modal.classList.add('active');
        }

        function openEditProductModal(prod) {
            document.getElementById('productModalHeading').textContent = 'Edit Bakery Item #' + prod.id;
            document.getElementById('prodFormAction').value = 'update_product';
            document.getElementById('prodFormId').value = prod.id;
            document.getElementById('prodName').value = prod.name;
            document.getElementById('prodCategory').value = prod.category;
            document.getElementById('prodPrice').value = prod.price;
            document.getElementById('prodImage').value = prod.image;
            document.getElementById('prodDesc').value = prod.description;
            document.getElementById('prodFeatured').checked = (prod.is_featured == 1);
            document.getElementById('prodSubmitBtn').textContent = 'Update Item Details →';

            modal.classList.add('active');
        }

        function closeProductModal() {
            modal.classList.remove('active');
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeProductModal();
        });
    </script>
</body>
</html>
