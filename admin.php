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

// Handle Form Submissions (Order Status Updates, Product CRUD & Restocking)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
    // CSRF Protection Guard for all administrative actions
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errorMsg = "Security token invalid or expired. Please refresh the page.";
    } else {
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
            $pStock    = isset($_POST['product_stock']) ? max(0, (int)$_POST['product_stock']) : 15;
            $pDesc     = $_POST['product_desc'] ?? '';
            $pImage    = $_POST['product_image'] ?? 'assets/breads-e1656042972619.jpg';
            $pFeatured = isset($_POST['is_featured']) ? 1 : 0;

            // Handle custom uploaded photo
            if (!empty($_FILES['product_photo']['name'])) {
                $uploadRes = handleProductImageUpload($_FILES['product_photo']);
                if ($uploadRes['success']) {
                    $pImage = $uploadRes['path'];
                } else {
                    $errorMsg = $uploadRes['error'];
                }
            }

            if (empty($pName) || $pPrice <= 0) {
                $errorMsg = "Product name and a valid price are required.";
            } elseif (empty($errorMsg)) {
                if (addProduct($pdo, $pName, $pCategory, $pPrice, $pStock, $pDesc, $pImage, $pFeatured)) {
                    $actionMsg = "New bakery item <strong>{$pName}</strong> (Stock: {$pStock}) added to the catalog with photo!";
                    $activeTab = 'products';
                } else {
                    $errorMsg = "Failed to add product to database.";
                }
            }
        } elseif ($action === 'update_product') {
            $pId       = (int)($_POST['product_id'] ?? 0);
            $pName     = $_POST['product_name'] ?? '';
            $pCategory = $_POST['product_category'] ?? 'Bread';
            $pPrice    = (float)($_POST['product_price'] ?? 0);
            $pStock    = isset($_POST['product_stock']) ? max(0, (int)$_POST['product_stock']) : 15;
            $pDesc     = $_POST['product_desc'] ?? '';
            $pImage    = $_POST['product_image'] ?? '';
            $pFeatured = isset($_POST['is_featured']) ? 1 : 0;

            // Handle optional new photo upload
            if (!empty($_FILES['product_photo']['name'])) {
                $uploadRes = handleProductImageUpload($_FILES['product_photo']);
                if ($uploadRes['success']) {
                    $pImage = $uploadRes['path'];
                } else {
                    $errorMsg = $uploadRes['error'];
                }
            }

            if ($pId > 0 && empty($errorMsg) && updateProduct($pdo, $pId, $pName, $pCategory, $pPrice, $pStock, $pDesc, $pImage, $pFeatured)) {
                $actionMsg = "Product <strong>{$pName}</strong> (Stock: {$pStock}) updated successfully!";
                $activeTab = 'products';
            } else {
                if (empty($errorMsg)) $errorMsg = "Failed to update product.";
            }
        } elseif ($action === 'restock_product') {
            $pId   = (int)($_POST['product_id'] ?? 0);
            $pQty  = (int)($_POST['restock_qty'] ?? 0);

            if ($pId > 0 && $pQty > 0 && restockProduct($pdo, $pId, $pQty)) {
                $p = getProductById($pdo, $pId);
                $pNameDisplay = htmlspecialchars($p['name'] ?? "Item #{$pId}");
                $newStockDisplay = $p['stock'] ?? 'updated';
                $actionMsg = "Inventory replenished! Added <strong>+{$pQty}</strong> units to <strong>{$pNameDisplay}</strong> (New Stock: {$newStockDisplay}).";
                $activeTab = 'products';
            } else {
                $errorMsg = "Please specify a valid restock quantity greater than 0.";
                $activeTab = 'products';
            }
        } elseif ($action === 'delete_product') {
            $pId = (int)($_POST['product_id'] ?? 0);
            if ($pId > 0 && deleteProduct($pdo, $pId)) {
                $actionMsg = "Product #{$pId} was deleted from catalog.";
                $activeTab = 'products';
            }
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

// Sales Ratings & Most Sold Analytics
$salesAnalytics = getProductSalesAnalytics($pdo);
$analyticsMap = [];
foreach ($salesAnalytics as $a) {
    $analyticsMap[$a['id']] = $a;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Command Center - Asentista's Bakery</title>
    <!-- Website Favicon / Main Logo -->
    <link rel="icon" type="image/png" href="assets/ASENTISTA FINAL.png">
    <link rel="apple-touch-icon" href="assets/ASENTISTA FINAL.png">
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

        /* Best Sellers Podium & Leaderboard */
        .podium-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .podium-card {
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            position: relative;
            box-shadow: var(--shadow-sm);
            border: 2px solid #E5E7EB;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: var(--transition-fast);
        }
        .podium-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        .podium-card.rank-1 { border-color: #F59E0B; background: linear-gradient(180deg, #FFFBEB 0%, #FFFFFF 100%); }
        .podium-card.rank-2 { border-color: #9CA3AF; background: linear-gradient(180deg, #F3F4F6 0%, #FFFFFF 100%); }
        .podium-card.rank-3 { border-color: #D97706; background: linear-gradient(180deg, #FEF3C7 0%, #FFFFFF 100%); }
        .podium-medal {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        .podium-thumb {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: var(--shadow-sm);
            margin-bottom: 0.8rem;
        }
        .rating-stars-gold {
            color: #F59E0B;
            letter-spacing: 1px;
            font-size: 1.05rem;
        }
        .sales-bar-wrap {
            width: 100%;
            height: 8px;
            background: #E5E7EB;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }
        .sales-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #F59E0B, #10B981);
            border-radius: 4px;
        }

        /* Upload photo preview */
        .upload-dropzone {
            background: #F9FAFB;
            border: 2px dashed #D1D5DB;
            border-radius: 6px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition-fast);
        }
        .upload-dropzone:hover {
            border-color: var(--color-yellow);
            background: #FEF3C7;
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
                <img src="assets/ASENTISTA FINAL.png" alt="Asentista's Bakery Logo" class="brand-logo-img" style="height: 40px;">
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
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="admin.php?export=csv" class="btn-store-preview" style="background: var(--color-yellow); color: var(--color-brown-deep); border: none; font-weight: 700;">
                    📥 Export CSV Report
                </a>
                <button type="button" class="btn-store-preview" style="background: #059669; color: #fff; border: none; font-weight: 700;" onclick="openRestockModal()">
                    📦 + Add Stock / Restock
                </button>
                <button type="button" class="btn-store-preview" onclick="openAddProductModal()">
                    🥖 + Add New Product & Photo
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
        <section class="kpi-cards-grid" style="grid-template-columns: repeat(6, 1fr);">
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
            <div class="kpi-card" style="border-left: 4px solid #F59E0B;">
                <span class="kpi-label">⭐ Top Sold Product</span>
                <span class="kpi-value" style="font-size: 1.05rem; color: #D97706; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo !empty($salesAnalytics[0]) ? htmlspecialchars($salesAnalytics[0]['name']) : 'N/A'; ?>">
                    <?php echo !empty($salesAnalytics[0]) ? htmlspecialchars($salesAnalytics[0]['name']) : 'None yet'; ?>
                </span>
                <span style="font-size: 0.72rem; color: var(--color-text-muted); font-weight: 600;">
                    <?php echo !empty($salesAnalytics[0]) ? $salesAnalytics[0]['total_sold'] . ' units sold (' . $salesAnalytics[0]['rating'] . ' ★)' : 'No sales yet'; ?>
                </span>
            </div>
        </section>

        <!-- Navigation Tabs -->
        <div class="admin-tab-nav">
            <button type="button" class="admin-tab-btn <?php echo $activeTab === 'orders' ? 'active' : ''; ?>" onclick="switchAdminTab('orders')">
                📋 Live Orders Queue (<?php echo count($ordersList); ?>)
            </button>
            <button type="button" class="admin-tab-btn <?php echo $activeTab === 'products' ? 'active' : ''; ?>" onclick="switchAdminTab('products')">
                🥖 Product Catalog & Inventory (<?php echo count($productsList); ?>)
            </button>
            <button type="button" class="admin-tab-btn <?php echo $activeTab === 'analytics' ? 'active' : ''; ?>" onclick="switchAdminTab('analytics')">
                ⭐ Best-Sellers & Sales Ratings (<?php echo count($salesAnalytics); ?>)
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
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
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
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
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
                    <p style="font-size: 0.85rem; color: var(--color-text-muted);">Monitor inventory stock levels, restock items, add new bakery goods, and adjust prices.</p>
                </div>
                <button type="button" class="btn-submit-modal" style="width: auto; padding: 0.6rem 1.2rem; font-size: 0.82rem; margin: 0;" onclick="openAddProductModal()">
                    + Add New Item to Menu
                </button>
            </div>

            <div class="products-crud-grid">
                <?php foreach ($productsList as $prod): ?>
                    <?php 
                        $stockVal = (int)($prod['stock'] ?? 0);
                        $isStockOut = ($stockVal <= 0);
                        $isStockLow = ($stockVal > 0 && $stockVal <= 5);
                    ?>
                    <div class="product-crud-card" style="<?php echo $isStockOut ? 'border: 1px solid #FECACA; background: #FFFBFB;' : ''; ?>">
                        <div>
                            <div style="position: relative;">
                                <img src="<?php echo htmlspecialchars($prod['image']); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" class="product-crud-thumb">
                                <?php if ($isStockOut): ?>
                                    <span style="position: absolute; top: 8px; right: 8px; background: #DC2626; color: #fff; font-size: 0.7rem; font-weight: 800; padding: 2px 7px; border-radius: 4px; box-shadow: var(--shadow-sm);">
                                        OUT OF STOCK (0)
                                    </span>
                                <?php elseif ($isStockLow): ?>
                                    <span style="position: absolute; top: 8px; right: 8px; background: #D97706; color: #fff; font-size: 0.7rem; font-weight: 800; padding: 2px 7px; border-radius: 4px; box-shadow: var(--shadow-sm);">
                                        LOW STOCK (<?php echo $stockVal; ?>)
                                    </span>
                                <?php else: ?>
                                    <span style="position: absolute; top: 8px; right: 8px; background: #059669; color: #fff; font-size: 0.7rem; font-weight: 800; padding: 2px 7px; border-radius: 4px; box-shadow: var(--shadow-sm);">
                                        <?php echo $stockVal; ?> in stock
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 4px;">
                                <h3 style="font-family: var(--font-serif); font-size: 1.05rem; font-weight: 700; color: var(--color-brown-deep);">
                                    <?php echo htmlspecialchars($prod['name']); ?>
                                </h3>
                                <span style="font-size: 0.95rem; font-weight: 700; color: #059669;">
                                    ₱<?php echo number_format($prod['price'], 2); ?>
                                </span>
                            </div>

                            <div style="display: flex; gap: 6px; align-items: center; margin-bottom: 6px; flex-wrap: wrap;">
                                <span style="font-size: 0.72rem; background: #E5E7EB; padding: 2px 6px; border-radius: 3px; font-weight: 600; text-transform: uppercase;">
                                    <?php echo htmlspecialchars($prod['category']); ?>
                                </span>
                                <span style="font-size: 0.75rem; font-weight: 700; color: <?php echo $isStockOut ? '#DC2626' : ($isStockLow ? '#D97706' : '#059669'); ?>;">
                                    Inventory: <?php echo $stockVal; ?> units
                                </span>
                            </div>

                            <?php 
                                $pAnalytics = $analyticsMap[$prod['id']] ?? null;
                            ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; background: #FFFBEB; padding: 4px 8px; border-radius: 4px; border: 1px solid #FDE68A;">
                                <span style="font-size: 0.76rem; font-weight: 700; color: #D97706;">
                                    ⭐ <?php echo $pAnalytics ? $pAnalytics['rating'] . ' ★' : '4.5 ★'; ?> 
                                    <span style="font-size: 0.7rem; font-weight: 500; color: #78350F;">(<?php echo $pAnalytics ? $pAnalytics['total_sold'] : 0; ?> sold)</span>
                                </span>
                                <?php if ($pAnalytics && !empty($pAnalytics['badge'])): ?>
                                    <span style="font-size: 0.68rem; font-weight: 800; background: <?php echo $pAnalytics['badge_bg']; ?>; color: <?php echo $pAnalytics['badge_color']; ?>; padding: 2px 6px; border-radius: 3px;">
                                        <?php echo $pAnalytics['badge']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p style="font-size: 0.78rem; color: var(--color-text-muted); margin-top: 4px; line-height: 1.4;">
                                <?php echo htmlspecialchars($prod['description']); ?>
                            </p>

                            <!-- Quick Restock Widget -->
                            <form method="POST" action="admin.php" style="margin-top: 10px; background: rgba(0,0,0,0.03); padding: 8px; border-radius: 4px; display: flex; align-items: center; gap: 6px;">
                                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                <input type="hidden" name="admin_action" value="restock_product">
                                <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                <label style="font-size: 0.72rem; font-weight: 700; color: var(--color-brown-deep); white-space: nowrap;">+ Add Stock:</label>
                                <input type="number" name="restock_qty" value="10" min="1" max="500" style="width: 55px; padding: 3px 6px; font-size: 0.75rem; border: 1px solid #ccc; border-radius: 3px;">
                                <button type="submit" class="btn-table-action" style="background: #059669; color: #fff; font-size: 0.72rem; padding: 3px 8px; border: none; font-weight: 700;">
                                    Restock
                                </button>
                            </form>
                        </div>
                        <div style="display: flex; gap: 8px; margin-top: 1rem;">
                            <button type="button" class="btn-table-action" style="flex: 1; background: var(--color-brown-deep); color: #fff;" onclick='openEditProductModal(<?php echo json_encode($prod); ?>)'>
                                ✏️ Edit
                            </button>
                            <form method="POST" action="admin.php" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars(addslashes($prod['name'])); ?>?');" style="flex: 1;">
                                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
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
             TAB 3: BEST-SELLERS & SALES RATINGS LEADERBOARD
             ============================================================================== -->
        <section id="tab-analytics" class="admin-content-card" style="display: <?php echo $activeTab === 'analytics' ? 'block' : 'none'; ?>;">
            <div class="card-title-row">
                <div>
                    <h2 class="card-heading">⭐ Product Sales Ratings & Best-Sellers Leaderboard</h2>
                    <p style="font-size: 0.85rem; color: var(--color-text-muted);">
                        Real-time sales velocity, total units purchased by customers, generated revenue, and customer popularity ratings.
                    </p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn-store-preview" style="background: #059669; color: #fff; border: none; font-weight: 700;" onclick="openRestockModal()">
                        📦 Restock Low Inventory Items
                    </button>
                </div>
            </div>

            <!-- Top 3 Best-Seller Showcase Podium -->
            <h3 style="font-family: var(--font-serif); font-size: 1.15rem; color: var(--color-brown-deep); margin-bottom: 1rem;">
                🏆 Bakery Best-Sellers Showcase
            </h3>
            <div class="podium-grid">
                <?php 
                    $medals = ['🥇', '🥈', '🥉'];
                    $ranks = ['rank-1', 'rank-2', 'rank-3'];
                    $rankTitles = ['#1 Best-Selling Artisan Loaf', '#2 Customer Favorite', '#3 Trending Item'];
                    for ($i = 0; $i < 3; $i++): 
                        if (empty($salesAnalytics[$i])) continue;
                        $topItem = $salesAnalytics[$i];
                ?>
                    <div class="podium-card <?php echo $ranks[$i]; ?>">
                        <div class="podium-medal"><?php echo $medals[$i]; ?></div>
                        <img src="<?php echo htmlspecialchars($topItem['image']); ?>" alt="<?php echo htmlspecialchars($topItem['name']); ?>" class="podium-thumb">
                        <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: #B45309; letter-spacing: 0.05em;">
                            <?php echo $rankTitles[$i]; ?>
                        </span>
                        <h4 style="font-family: var(--font-serif); font-size: 1.2rem; font-weight: 700; color: var(--color-brown-deep); margin: 4px 0;">
                            <?php echo htmlspecialchars($topItem['name']); ?>
                        </h4>
                        <div class="rating-stars-gold" style="margin-bottom: 6px;">
                            <?php echo $topItem['rating_stars']; ?> 
                            <strong style="font-size: 0.88rem; color: #92400E;"><?php echo $topItem['rating']; ?> / 5.0</strong>
                        </div>
                        <div style="display: flex; gap: 14px; font-size: 0.85rem; margin-bottom: 10px;">
                            <div>
                                <span style="color: var(--color-text-muted); font-size: 0.75rem; display: block;">Total Sold</span>
                                <strong style="font-size: 1.1rem; color: var(--color-brown-deep);"><?php echo $topItem['total_sold']; ?> units</strong>
                            </div>
                            <div>
                                <span style="color: var(--color-text-muted); font-size: 0.75rem; display: block;">Revenue</span>
                                <strong style="font-size: 1.1rem; color: #059669;">₱<?php echo number_format($topItem['total_revenue'], 2); ?></strong>
                            </div>
                        </div>
                        <div style="width: 100%; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(0,0,0,0.06); padding-top: 8px;">
                            <span style="font-size: 0.78rem; font-weight: 700; color: <?php echo $topItem['stock'] <= 5 ? '#DC2626' : '#059669'; ?>;">
                                Stock: <?php echo $topItem['stock']; ?> units
                            </span>
                            <button type="button" class="btn-table-action" style="background: #059669; color: #fff; font-size: 0.74rem; padding: 4px 10px;" onclick="openRestockForProduct(<?php echo $topItem['id']; ?>, '<?php echo htmlspecialchars(addslashes($topItem['name'])); ?>', <?php echo $topItem['stock']; ?>)">
                                + Add Stock
                            </button>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Complete Sales Ratings & Units Sold Table -->
            <h3 style="font-family: var(--font-serif); font-size: 1.15rem; color: var(--color-brown-deep); margin: 1.5rem 0 1rem 0;">
                📊 Complete Catalog Sales Rankings & Ratings (<?php echo count($salesAnalytics); ?> Products)
            </h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Rank</th>
                        <th>Product Details</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th style="min-width: 160px;">Units Sold Performance</th>
                        <th>Revenue Generated</th>
                        <th>Customer Rating</th>
                        <th>Current Stock</th>
                        <th style="width: 130px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $maxSoldGlobal = !empty($salesAnalytics[0]['total_sold']) ? $salesAnalytics[0]['total_sold'] : 1;
                        foreach ($salesAnalytics as $idx => $item): 
                            $rankNum = $idx + 1;
                            $pct = ($maxSoldGlobal > 0 && $item['total_sold'] > 0) ? min(100, round(($item['total_sold'] / $maxSoldGlobal) * 100)) : 5;
                    ?>
                        <tr>
                            <td>
                                <strong style="font-size: 1rem; color: <?php echo $rankNum <= 3 ? '#D97706' : 'var(--color-text-muted)'; ?>;">
                                    #<?php echo $rankNum; ?>
                                </strong>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="" style="width: 44px; height: 44px; border-radius: 4px; object-fit: cover; border: 1px solid #E5E7EB;">
                                    <div>
                                        <strong style="color: var(--color-brown-deep); font-size: 0.92rem;">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </strong>
                                        <div style="margin-top: 2px;">
                                            <span style="font-size: 0.68rem; font-weight: 700; background: <?php echo $item['badge_bg']; ?>; color: <?php echo $item['badge_color']; ?>; padding: 2px 6px; border-radius: 3px;">
                                                <?php echo $item['badge']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.75rem; background: #E5E7EB; padding: 2px 7px; border-radius: 3px; font-weight: 600; text-transform: uppercase;">
                                    <?php echo htmlspecialchars($item['category']); ?>
                                </span>
                            </td>
                            <td style="font-weight: 700; color: var(--color-brown-deep);">
                                ₱<?php echo number_format($item['price'], 2); ?>
                            </td>
                            <td>
                                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 700; color: var(--color-brown-deep);">
                                    <span><?php echo $item['total_sold']; ?> sold</span>
                                    <span style="font-size: 0.72rem; color: var(--color-text-muted);"><?php echo $item['order_count']; ?> orders</span>
                                </div>
                                <div class="sales-bar-wrap">
                                    <div class="sales-bar-fill" style="width: <?php echo $pct; ?>%;"></div>
                                </div>
                            </td>
                            <td style="font-weight: 700; color: #059669;">
                                ₱<?php echo number_format($item['total_revenue'], 2); ?>
                            </td>
                            <td>
                                <div class="rating-stars-gold" style="font-size: 0.95rem;">
                                    <?php echo $item['rating_stars']; ?>
                                </div>
                                <span style="font-size: 0.76rem; font-weight: 700; color: #92400E;">
                                    <?php echo $item['rating']; ?> / 5.0
                                </span>
                            </td>
                            <td>
                                <span class="status-badge" style="background: <?php echo $item['stock'] <= 0 ? '#FEE2E2' : ($item['stock'] <= 5 ? '#FEF3C7' : '#D1FAE5'); ?>; color: <?php echo $item['stock'] <= 0 ? '#991B1B' : ($item['stock'] <= 5 ? '#92400E' : '#065F46'); ?>;">
                                    <?php echo $item['stock']; ?> units
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn-table-action" style="background: #059669; color: #fff; border: none;" onclick="openRestockForProduct(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['name'])); ?>', <?php echo $item['stock']; ?>)">
                                    + Add Stock
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <!-- ==============================================================================
             TAB 4: CUSTOMER DIRECTORY
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
                <form method="POST" action="admin.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
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

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="prodStock">Available Stock (Units) *</label>
                            <input type="number" id="prodStock" name="product_stock" class="form-input" placeholder="15" min="0" value="15" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="prodImage">Image Path (Preset / Fallback)</label>
                            <input type="text" id="prodImage" name="product_image" class="form-input" placeholder="assets/breads-e1656042972619.jpg" value="assets/breads-e1656042972619.jpg">
                        </div>
                    </div>

                    <!-- Photo Upload with Live Preview -->
                    <div class="form-group" style="background: #F9FAFB; border: 2px dashed #D1D5DB; padding: 12px; border-radius: 6px;">
                        <label class="form-label" for="prodPhotoFile" style="margin-bottom: 4px; display: block; font-weight: 700; color: var(--color-brown-deep);">
                            📸 Upload Product Photo (JPG, PNG, WEBP)
                        </label>
                        <input type="file" id="prodPhotoFile" name="product_photo" accept="image/jpeg,image/png,image/webp" class="form-input" style="padding: 6px; font-size: 0.82rem; background: #fff;" onchange="previewUploadImage(this)">
                        <div id="previewContainer" style="margin-top: 8px; display: none; text-align: center;">
                            <img id="photoUploadPreview" src="" alt="Selected Photo Preview" style="max-height: 120px; border-radius: 6px; box-shadow: var(--shadow-sm); border: 1px solid #E5E7EB; object-fit: cover;">
                            <div style="font-size: 0.72rem; color: #059669; font-weight: 700; margin-top: 4px;">✓ Photo selected for upload</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prodDesc">Description</label>
                        <textarea id="prodDesc" name="product_desc" class="form-textarea" rows="2" placeholder="Handcrafted with organic wheat flour and natural sourdough culture..."></textarea>
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

    <!-- Dedicated Restock Inventory Modal -->
    <div class="modal-backdrop" id="restockFormModal">
        <div class="modal-window" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title">📦 Add Inventory Stock / Restock Product</h3>
                <button type="button" class="modal-close-btn" onclick="closeRestockModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="admin.php">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <input type="hidden" name="admin_action" value="restock_product">

                    <div class="form-group">
                        <label class="form-label" for="restockProductSelect">Select Product to Restock *</label>
                        <select id="restockProductSelect" name="product_id" class="form-select" required>
                            <?php foreach ($productsList as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['name']); ?> (Current Stock: <?php echo $p['stock']; ?> units)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="restockQtyInput">Quantity to Add (+Units) *</label>
                        <input type="number" id="restockQtyInput" name="restock_qty" class="form-input" min="1" max="1000" value="15" required style="font-size: 1.15rem; font-weight: 700; color: #059669;">
                        <div style="display: flex; gap: 6px; margin-top: 8px;">
                            <button type="button" class="btn-table-action" style="padding: 3px 10px; font-size: 0.74rem;" onclick="setRestockAdd(5)">+5</button>
                            <button type="button" class="btn-table-action" style="padding: 3px 10px; font-size: 0.74rem;" onclick="setRestockAdd(10)">+10</button>
                            <button type="button" class="btn-table-action" style="padding: 3px 10px; font-size: 0.74rem;" onclick="setRestockAdd(20)">+20</button>
                            <button type="button" class="btn-table-action" style="padding: 3px 10px; font-size: 0.74rem;" onclick="setRestockAdd(50)">+50</button>
                            <button type="button" class="btn-table-action" style="padding: 3px 10px; font-size: 0.74rem;" onclick="setRestockAdd(100)">+100</button>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit-modal" style="background: #059669; font-size: 0.95rem; padding: 0.95rem; margin-top: 1rem;">
                        + Replenish Inventory Stock Now →
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

        const productModal = document.getElementById('productFormModal');
        const restockModal = document.getElementById('restockFormModal');

        function openAddProductModal() {
            document.getElementById('productModalHeading').textContent = 'Add New Bakery Item & Photo';
            document.getElementById('prodFormAction').value = 'add_product';
            document.getElementById('prodFormId').value = '';
            document.getElementById('prodName').value = '';
            document.getElementById('prodCategory').value = 'Bread';
            document.getElementById('prodPrice').value = '';
            document.getElementById('prodStock').value = '15';
            document.getElementById('prodImage').value = 'assets/breads-e1656042972619.jpg';
            document.getElementById('prodDesc').value = '';
            document.getElementById('prodFeatured').checked = true;
            document.getElementById('prodPhotoFile').value = '';
            document.getElementById('previewContainer').style.display = 'none';
            document.getElementById('prodSubmitBtn').textContent = 'Add to Database Catalog →';

            productModal.classList.add('active');
        }

        function openEditProductModal(prod) {
            document.getElementById('productModalHeading').textContent = 'Edit Bakery Item #' + prod.id;
            document.getElementById('prodFormAction').value = 'update_product';
            document.getElementById('prodFormId').value = prod.id;
            document.getElementById('prodName').value = prod.name;
            document.getElementById('prodCategory').value = prod.category;
            document.getElementById('prodPrice').value = prod.price;
            document.getElementById('prodStock').value = (prod.stock !== undefined) ? prod.stock : 15;
            document.getElementById('prodImage').value = prod.image;
            document.getElementById('prodDesc').value = prod.description;
            document.getElementById('prodFeatured').checked = (prod.is_featured == 1);
            document.getElementById('prodPhotoFile').value = '';

            const previewImg = document.getElementById('photoUploadPreview');
            const previewWrap = document.getElementById('previewContainer');
            if (prod.image) {
                previewImg.src = prod.image;
                previewWrap.style.display = 'block';
            } else {
                previewWrap.style.display = 'none';
            }

            document.getElementById('prodSubmitBtn').textContent = 'Update Item Details & Photo →';

            productModal.classList.add('active');
        }

        function closeProductModal() {
            productModal.classList.remove('active');
        }

        function openRestockModal() {
            restockModal.classList.add('active');
        }

        function openRestockForProduct(prodId, prodName, currentStock) {
            const select = document.getElementById('restockProductSelect');
            if (select) {
                select.value = prodId;
            }
            openRestockModal();
        }

        function closeRestockModal() {
            restockModal.classList.remove('active');
        }

        function setRestockAdd(qty) {
            const input = document.getElementById('restockQtyInput');
            if (input) input.value = qty;
        }

        function previewUploadImage(input) {
            const previewWrap = document.getElementById('previewContainer');
            const previewImg = document.getElementById('photoUploadPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewWrap.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                previewWrap.style.display = 'none';
            }
        }

        productModal.addEventListener('click', (e) => {
            if (e.target === productModal) closeProductModal();
        });

        restockModal.addEventListener('click', (e) => {
            if (e.target === restockModal) closeRestockModal();
        });
    </script>
</body>
</html>
