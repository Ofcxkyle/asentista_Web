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
            $pImage    = $_POST['product_image'] ?? 'assets/breads-e1656042972619.png';
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
                $pActive = isset($_POST['is_active']) ? 1 : 0;
                if (addProduct($pdo, $pName, $pCategory, $pPrice, $pStock, $pDesc, $pImage, $pFeatured, $pActive)) {
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
            $pActive   = isset($_POST['is_active']) ? 1 : 0;

            // Handle optional new photo upload
            if (!empty($_FILES['product_photo']['name'])) {
                $uploadRes = handleProductImageUpload($_FILES['product_photo']);
                if ($uploadRes['success']) {
                    $pImage = $uploadRes['path'];
                } else {
                    $errorMsg = $uploadRes['error'];
                }
            }

            if ($pId > 0 && empty($errorMsg) && updateProduct($pdo, $pId, $pName, $pCategory, $pPrice, $pStock, $pDesc, $pImage, $pFeatured, $pActive)) {
                $actionMsg = "Product <strong>{$pName}</strong> (Stock: {$pStock}) updated successfully!";
                $activeTab = 'products';
            } else {
                if (empty($errorMsg)) $errorMsg = "Failed to update product.";
            }
        } elseif ($action === 'toggle_product_store') {
            $pId = (int)($_POST['product_id'] ?? 0);
            $newStatus = isset($_POST['new_status']) ? (int)$_POST['new_status'] : 0;
            $p = getProductById($pdo, $pId);
            $pName = htmlspecialchars($p['name'] ?? "Item #{$pId}");

            if ($pId > 0 && toggleProductStatus($pdo, $pId, $newStatus)) {
                if ($newStatus === 0) {
                    $actionMsg = "🚫 Product <strong>{$pName}</strong> has been <strong>removed from customer storefront pages</strong>. Customers can no longer view or order it.";
                } else {
                    $actionMsg = "✅ Product <strong>{$pName}</strong> has been <strong>restored to customer storefront pages</strong> and is now visible.";
                }
                $activeTab = 'products';
            } else {
                $errorMsg = "Failed to update product storefront visibility.";
                $activeTab = 'products';
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
            $p = getProductById($pdo, $pId);
            $pName = htmlspecialchars($p['name'] ?? "Item #{$pId}");

            if ($pId > 0 && deleteProduct($pdo, $pId)) {
                $actionMsg = "🗑️ Product <strong>{$pName}</strong> was permanently deleted from the database.";
                $activeTab = 'products';
            } else {
                $errorMsg = "Failed to delete product from database.";
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
$productsList = getAllProducts($pdo, true); // true = include hidden/removed products for admin management
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
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.08);
            color: #F7EFE8;
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 6px 13px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            transition: transform 140ms var(--ease-out-expo), background-color 140ms ease, border-color 140ms ease, color 140ms ease;
        }
        .btn-store-preview:hover {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.35);
            color: #FFFFFF;
            transform: translateY(-1px);
        }
        .btn-admin-logout {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(220, 38, 38, 0.18);
            color: #FECACA;
            border: 1px solid rgba(220, 38, 38, 0.35);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            transition: transform 140ms var(--ease-out-expo), background-color 140ms ease, border-color 140ms ease, color 140ms ease;
        }
        .btn-admin-logout:hover {
            background: #DC2626;
            color: #FFFFFF;
            border-color: #B91C1C;
            transform: translateY(-1px);
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

        /* Tabbed Workspace Segmented Control */
        .admin-tab-nav {
            display: flex;
            gap: 6px;
            margin-bottom: 1.75rem;
            background: rgba(43, 27, 21, 0.05);
            padding: 5px;
            border-radius: 10px;
            border: 1px solid rgba(43, 27, 21, 0.08);
            width: fit-content;
            flex-wrap: wrap;
        }
        .admin-tab-btn {
            padding: 0.65rem 1.25rem;
            font-size: 0.84rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            color: var(--color-brown-soft);
            background: transparent;
            border-radius: 7px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: transform 140ms var(--ease-out-expo), background-color 140ms ease, color 140ms ease, box-shadow 140ms ease;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .admin-tab-btn:hover {
            color: var(--color-brown-deep);
            background-color: rgba(255, 255, 255, 0.6);
        }
        .admin-tab-btn.active {
            background-color: #FFFFFF;
            color: var(--color-brown-deep);
            font-weight: 700;
            border-color: rgba(43, 27, 21, 0.1);
            box-shadow: 0 2px 6px rgba(43, 27, 21, 0.08), 0 1px 2px rgba(43, 27, 21, 0.04);
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 5px 11px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: transform 130ms var(--ease-out-expo), background-color 130ms ease, border-color 130ms ease, box-shadow 130ms ease, color 130ms ease;
        }
        .btn-table-action:hover {
            transform: translateY(-1px);
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
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <line x1="10" y1="14" x2="21" y2="3"></line>
                </svg>
                <span>Live Storefront</span>
            </a>
            <span style="font-size: 0.8rem; opacity: 0.85;">
                Logged in: <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong>
            </span>
            <a href="logout.php" class="btn-admin-logout" title="Sign Out">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Logout</span>
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
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <a href="admin.php?export=csv" class="btn btn-secondary btn-sm" title="Download Operations CSV">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>Export CSV</span>
                </a>
                <button type="button" class="btn btn-emerald btn-sm" onclick="openRestockModal()" title="Quick restock inventory">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                    <span>Restock Stock</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="openAddProductModal()" title="Add product to catalog">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Add New Product</span>
                </button>
                <button type="button" class="btn btn-destructive btn-sm" onclick="openRemoveProductModal()" title="Hide product from customer pages">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                    </svg>
                    <span>Remove Product</span>
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

        <!-- Navigation Tabs Segmented Control -->
        <div class="admin-tab-nav">
            <button type="button" class="admin-tab-btn <?php echo $activeTab === 'orders' ? 'active' : ''; ?>" onclick="switchAdminTab('orders')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Orders Queue</span>
                <span style="background: rgba(43,27,21,0.08); font-size: 0.72rem; padding: 1px 6px; border-radius: 999px;"><?php echo count($ordersList); ?></span>
            </button>
            <button type="button" class="admin-tab-btn <?php echo $activeTab === 'products' ? 'active' : ''; ?>" onclick="switchAdminTab('products')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>Product Catalog</span>
                <span style="background: rgba(43,27,21,0.08); font-size: 0.72rem; padding: 1px 6px; border-radius: 999px;"><?php echo count($productsList); ?></span>
            </button>
            <button type="button" class="admin-tab-btn <?php echo $activeTab === 'analytics' ? 'active' : ''; ?>" onclick="switchAdminTab('analytics')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                <span>Ratings & Best-Sellers</span>
                <span style="background: rgba(43,27,21,0.08); font-size: 0.72rem; padding: 1px 6px; border-radius: 999px;"><?php echo count($salesAnalytics); ?></span>
            </button>
            <button type="button" class="admin-tab-btn <?php echo $activeTab === 'customers' ? 'active' : ''; ?>" onclick="switchAdminTab('customers')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Customers</span>
                <span style="background: rgba(43,27,21,0.08); font-size: 0.72rem; padding: 1px 6px; border-radius: 999px;"><?php echo count($customersList); ?></span>
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
                    <button type="submit" class="btn btn-primary btn-sm">Search</button>
                    <?php if ($orderSearchKw || $orderStatusFilter): ?>
                        <a href="admin.php?tab=orders" class="btn btn-secondary btn-sm">Reset</a>
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
                                    <form method="POST" action="admin.php" style="display: flex; gap: 6px; align-items: center;">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="admin_action" value="update_order_status">
                                        <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                                        <select name="new_status" class="form-select" style="padding: 4px 8px; font-size: 0.75rem; border-radius: 6px;">
                                            <option value="Pending" <?php echo $ord['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Confirmed" <?php echo $ord['status'] === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="Completed" <?php echo $ord['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Cancelled" <?php echo $ord['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm" style="padding: 4px 10px; font-size: 0.74rem;">Save</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="admin.php" onsubmit="return confirm('Delete order #<?php echo $ord['id']; ?> permanently?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="admin_action" value="delete_order">
                                        <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                                        <button type="submit" class="btn btn-destructive btn-sm" style="padding: 5px 9px;" title="Delete order permanently">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
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
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="openAddProductModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Add New Item</span>
                    </button>
                    <button type="button" class="btn btn-destructive btn-sm" onclick="openRemoveProductModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                        </svg>
                        <span>Remove from Store</span>
                    </button>
                </div>
            </div>

            <div class="products-crud-grid">
                <?php foreach ($productsList as $prod): ?>
                    <?php 
                        $stockVal = (int)($prod['stock'] ?? 0);
                        $isStockOut = ($stockVal <= 0);
                        $isStockLow = ($stockVal > 0 && $stockVal <= 5);
                        $isActive = !isset($prod['is_active']) || (int)$prod['is_active'] === 1;
                    ?>
                    <div class="product-crud-card" style="<?php echo !$isActive ? 'border: 1.5px dashed #FECACA; background: #FFFBFB; opacity: 0.88;' : ($isStockOut ? 'border: 1px solid #FECACA; background: #FFFBFB;' : ''); ?>">
                        <div>
                            <div style="position: relative;">
                                <img src="<?php echo htmlspecialchars($prod['image']); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" class="product-crud-thumb">
                                
                                <!-- Storefront Visibility Badge -->
                                <?php if ($isActive): ?>
                                    <span style="position: absolute; top: 8px; left: 8px; background: #059669; color: #fff; font-size: 0.68rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 4px;">
                                        <span>🟢</span> Live in Store
                                    </span>
                                <?php else: ?>
                                    <span style="position: absolute; top: 8px; left: 8px; background: #DC2626; color: #fff; font-size: 0.68rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 4px;">
                                        <span>🚫</span> Hidden from Users
                                    </span>
                                <?php endif; ?>

                                <!-- Stock Badge -->
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

                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 4px; margin-top: 6px;">
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
                            <form method="POST" action="admin.php" style="margin-top: 10px; background: rgba(43,27,21,0.04); padding: 8px 10px; border-radius: 6px; border: 1px solid rgba(43,27,21,0.06); display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                <input type="hidden" name="admin_action" value="restock_product">
                                <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                <label style="font-size: 0.74rem; font-weight: 600; color: var(--color-brown-soft); white-space: nowrap;">+ Add Stock:</label>
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <input type="number" name="restock_qty" value="10" min="1" max="500" style="width: 52px; padding: 3px 6px; font-size: 0.78rem; font-weight: 600; border: 1px solid rgba(43,27,21,0.2); border-radius: 4px; background: #fff;">
                                    <button type="submit" class="btn btn-emerald btn-sm" style="padding: 4px 9px; font-size: 0.72rem;">
                                        Restock
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Product Action Buttons: Edit, Remove from Store, and Delete Permanently -->
                        <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 0.9rem;">
                            <div style="display: flex; gap: 6px;">
                                <button type="button" class="btn btn-secondary btn-sm" style="flex: 1;" onclick='openEditProductModal(<?php echo json_encode($prod); ?>)' title="Edit product details">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 20h9"></path>
                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                    </svg>
                                    <span>Edit</span>
                                </button>

                                <?php if ($isActive): ?>
                                    <form method="POST" action="admin.php" onsubmit="return confirm('Remove \'<?php echo htmlspecialchars(addslashes($prod['name'])); ?>\' from customer storefront pages? Customers will immediately no longer be able to view or order this item.');" style="flex: 1.4;">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="admin_action" value="toggle_product_store">
                                        <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                        <input type="hidden" name="new_status" value="0">
                                        <button type="submit" class="btn btn-destructive btn-sm" style="width: 100%;" title="Hide this product from customer storefront">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                            </svg>
                                            <span>Hide</span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="admin.php" onsubmit="return confirm('Restore \'<?php echo htmlspecialchars(addslashes($prod['name'])); ?>\' to customer storefront pages?');" style="flex: 1.4;">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="admin_action" value="toggle_product_store">
                                        <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                        <input type="hidden" name="new_status" value="1">
                                        <button type="submit" class="btn btn-emerald btn-sm" style="width: 100%;" title="Make this product live on storefront">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            <span>Restore</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Permanent Delete Option -->
                            <form method="POST" action="admin.php" onsubmit="return confirm('Permanently delete \'<?php echo htmlspecialchars(addslashes($prod['name'])); ?>\' from the database catalog? This action cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                <input type="hidden" name="admin_action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" style="width: 100%; font-size: 0.72rem; color: #991B1B;" title="Permanently delete from database">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                    <span>Delete Permanently</span>
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
                            <input type="text" id="prodImage" name="product_image" class="form-input" placeholder="assets/breads-e1656042972619.png" value="assets/breads-e1656042972619.png">
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

                    <div class="form-group" style="display: flex; align-items: center; gap: 8px; background: #F9FAFB; padding: 10px; border-radius: 6px; border: 1px solid #E5E7EB;">
                        <input type="checkbox" id="prodActive" name="is_active" value="1" checked>
                        <label for="prodActive" style="font-size: 0.85rem; font-weight: 700; cursor: pointer; color: var(--color-brown-deep);">
                            🟢 Visible to Customers on Storefront (Uncheck to Remove Product from User Pages)
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 1rem;" id="prodSubmitBtn">
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
                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 10px;" onclick="setRestockAdd(5)">+5</button>
                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 10px;" onclick="setRestockAdd(10)">+10</button>
                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 10px;" onclick="setRestockAdd(20)">+20</button>
                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 10px;" onclick="setRestockAdd(50)">+50</button>
                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 10px;" onclick="setRestockAdd(100)">+100</button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-emerald btn-lg" style="width: 100%; margin-top: 1.25rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <span>Replenish Inventory Stock Now</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Dedicated Remove Product Feature Modal -->
    <div class="modal-backdrop" id="removeProductModal">
        <div class="modal-window" style="max-width: 580px;">
            <div class="modal-header" style="border-bottom: 2px solid #FEE2E2;">
                <div>
                    <h3 class="modal-title" style="color: #991B1B; display: flex; align-items: center; gap: 8px;">
                        <span>🚫</span> Remove Product from Storefront
                    </h3>
                    <p style="font-size: 0.82rem; color: var(--color-text-muted); margin: 3px 0 0 0;">
                        Remove an existing product from customer pages, search, and ordering.
                    </p>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeRemoveProductModal()">&times;</button>
            </div>
            <div class="modal-body" style="padding-top: 1.25rem;">
                <div class="form-group">
                    <label class="form-label" for="removeProductSelect" style="font-weight: 700;">Select Existing Product to Remove *</label>
                    <select id="removeProductSelect" class="form-select" onchange="updateRemoveProductPreview()" style="font-size: 0.95rem; padding: 0.65rem 0.75rem;">
                        <?php 
                        $hiddenCount = 0;
                        foreach ($productsList as $p): 
                            $isAct = !isset($p['is_active']) || (int)$p['is_active'] === 1;
                            if (!$isAct) $hiddenCount++;
                        ?>
                            <option value="<?php echo $p['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                    data-price="<?php echo number_format($p['price'], 2); ?>"
                                    data-stock="<?php echo (int)($p['stock'] ?? 0); ?>"
                                    data-category="<?php echo htmlspecialchars($p['category']); ?>"
                                    data-image="<?php echo htmlspecialchars($p['image']); ?>"
                                    data-active="<?php echo $isAct ? '1' : '0'; ?>">
                                <?php echo $isAct ? '🟢 [Active] ' : '🔴 [Hidden] '; ?><?php echo htmlspecialchars($p['name']); ?> (₱<?php echo number_format($p['price'], 2); ?> - Stock: <?php echo (int)($p['stock'] ?? 0); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Live Item Preview Card -->
                <div id="removeProductPreviewCard" style="display: flex; gap: 14px; align-items: center; background: #FFF5F5; border: 1.5px solid #FECACA; border-radius: 8px; padding: 12px 14px; margin-bottom: 1.25rem;">
                    <img id="removePreviewImg" src="assets/breads-e1656042972619.png" alt="Preview" style="width: 64px; height: 64px; object-fit: cover; border-radius: 6px; border: 1px solid #E5E7EB;">
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <h4 id="removePreviewName" style="margin: 0; font-size: 1rem; color: var(--color-brown-deep); font-weight: 700;">Product Name</h4>
                            <span id="removePreviewCategory" class="order-badge" style="background: #E5E7EB; color: #374151; font-size: 0.7rem; padding: 2px 6px;">Category</span>
                        </div>
                        <div style="font-size: 0.82rem; color: var(--color-text-muted); margin-top: 3px;">
                            Price: <strong id="removePreviewPrice" style="color: var(--color-brown-deep);">₱0.00</strong> | Stock: <strong id="removePreviewStock">0</strong> units
                        </div>
                        <div style="margin-top: 5px;">
                            <span id="removePreviewStatusBadge" style="font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 999px; background: #D1FAE5; color: #065F46;">
                                🟢 Currently Live on Customer Storefront
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Guidance Box -->
                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 6px; padding: 10px 12px; margin-bottom: 1.25rem; font-size: 0.8rem; color: #4B5563; line-height: 1.45;">
                    💡 <strong>Safe Storefront Removal:</strong>
                    <ul style="margin: 4px 0 0 16px; padding: 0;">
                        <li><strong>Remove / Hide from Store (Recommended):</strong> Instantly removes the item from customer view (index page, price tables, live search) and clears it from active shopping carts without breaking order or sales history.</li>
                        <li><strong>Delete Permanently:</strong> Erases the record completely from MySQL.</li>
                    </ul>
                </div>

                <!-- Action Forms -->
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Hide / Restore Form -->
                    <form method="POST" action="admin.php" id="removeToggleForm" onsubmit="return confirmRemoveToggle()">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="admin_action" value="toggle_product_store">
                        <input type="hidden" name="product_id" id="toggleFormProdId" value="">
                        <input type="hidden" name="new_status" id="toggleFormNewStatus" value="0">
                        <button type="submit" id="removeToggleBtn" class="btn btn-destructive btn-md" style="width: 100%;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                            </svg>
                            <span>Remove from Customer Storefront Pages Now</span>
                        </button>
                    </form>

                    <!-- Permanent Delete Form -->
                    <form method="POST" action="admin.php" id="deletePermanentForm" onsubmit="return confirmDeletePermanent()">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="admin_action" value="delete_product">
                        <input type="hidden" name="product_id" id="deleteFormProdId" value="">
                        <button type="submit" class="btn btn-ghost btn-sm" style="width: 100%; color: #991B1B;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            <span>Or Delete Permanently from Database Catalog</span>
                        </button>
                    </form>
                </div>

                <!-- Quick Restore Section if any items are currently hidden -->
                <?php 
                $hiddenItems = array_filter($productsList, function($p) {
                    return isset($p['is_active']) && (int)$p['is_active'] === 0;
                });
                if (!empty($hiddenItems)): 
                ?>
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed #E5E7EB;">
                        <h4 style="font-size: 0.85rem; font-weight: 700; color: #6B7280; margin-bottom: 8px;">
                            Currently Hidden / Removed Products (<?php echo count($hiddenItems); ?>):
                        </h4>
                        <div style="max-height: 140px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px;">
                            <?php foreach ($hiddenItems as $hp): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: #F3F4F6; padding: 6px 10px; border-radius: 6px; font-size: 0.8rem;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($hp['name']); ?></strong> (₱<?php echo number_format($hp['price'], 2); ?>)
                                    </div>
                                    <form method="POST" action="admin.php" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="admin_action" value="toggle_product_store">
                                        <input type="hidden" name="product_id" value="<?php echo $hp['id']; ?>">
                                        <input type="hidden" name="new_status" value="1">
                                        <button type="submit" class="btn btn-emerald btn-sm" style="padding: 3px 8px; font-size: 0.72rem;">
                                            Restore Live
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
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
        const removeProductModal = document.getElementById('removeProductModal');

        function openAddProductModal() {
            document.getElementById('productModalHeading').textContent = 'Add New Bakery Item & Photo';
            document.getElementById('prodFormAction').value = 'add_product';
            document.getElementById('prodFormId').value = '';
            document.getElementById('prodName').value = '';
            document.getElementById('prodCategory').value = 'Bread';
            document.getElementById('prodPrice').value = '';
            document.getElementById('prodStock').value = '15';
            document.getElementById('prodImage').value = 'assets/breads-e1656042972619.png';
            document.getElementById('prodDesc').value = '';
            document.getElementById('prodFeatured').checked = true;
            document.getElementById('prodActive').checked = true;
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
            document.getElementById('prodActive').checked = (prod.is_active === undefined || prod.is_active == 1);
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

        function openRemoveProductModal(targetProdId = null) {
            const select = document.getElementById('removeProductSelect');
            if (select && targetProdId) {
                select.value = targetProdId;
            }
            updateRemoveProductPreview();
            removeProductModal.classList.add('active');
        }

        function closeRemoveProductModal() {
            removeProductModal.classList.remove('active');
        }

        function updateRemoveProductPreview() {
            const select = document.getElementById('removeProductSelect');
            if (!select || !select.selectedOptions || select.selectedOptions.length === 0) return;
            const opt = select.selectedOptions[0];
            const pId = opt.value;
            const name = opt.getAttribute('data-name') || '';
            const price = opt.getAttribute('data-price') || '0.00';
            const stock = opt.getAttribute('data-stock') || '0';
            const category = opt.getAttribute('data-category') || '';
            const image = opt.getAttribute('data-image') || 'assets/breads-e1656042972619.png';
            const isActive = opt.getAttribute('data-active') === '1';

            const toggleId = document.getElementById('toggleFormProdId');
            const deleteId = document.getElementById('deleteFormProdId');
            if (toggleId) toggleId.value = pId;
            if (deleteId) deleteId.value = pId;

            const previewImg = document.getElementById('removePreviewImg');
            const previewName = document.getElementById('removePreviewName');
            const previewCat = document.getElementById('removePreviewCategory');
            const previewPrice = document.getElementById('removePreviewPrice');
            const previewStock = document.getElementById('removePreviewStock');
            if (previewImg) previewImg.src = image;
            if (previewName) previewName.textContent = name;
            if (previewCat) previewCat.textContent = category;
            if (previewPrice) previewPrice.textContent = '₱' + price;
            if (previewStock) previewStock.textContent = stock;

            const badge = document.getElementById('removePreviewStatusBadge');
            const toggleBtn = document.getElementById('removeToggleBtn');
            const newStatusInput = document.getElementById('toggleFormNewStatus');

            if (badge && toggleBtn && newStatusInput) {
                if (isActive) {
                    badge.style.background = '#D1FAE5';
                    badge.style.color = '#065F46';
                    badge.textContent = '🟢 Currently Live on Customer Storefront';
                    toggleBtn.className = 'btn btn-destructive btn-md';
                    toggleBtn.style.width = '100%';
                    toggleBtn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg><span>Remove from Customer Storefront Pages Now</span>';
                    newStatusInput.value = '0';
                } else {
                    badge.style.background = '#FEE2E2';
                    badge.style.color = '#991B1B';
                    badge.textContent = '🔴 Currently Hidden / Removed from Customer Storefront';
                    toggleBtn.className = 'btn btn-emerald btn-md';
                    toggleBtn.style.width = '100%';
                    toggleBtn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Restore to Customer Storefront Pages</span>';
                    newStatusInput.value = '1';
                }
            }
        }

        function confirmRemoveToggle() {
            const select = document.getElementById('removeProductSelect');
            const opt = select ? select.selectedOptions[0] : null;
            const name = opt ? opt.getAttribute('data-name') : 'this product';
            const newStatus = document.getElementById('toggleFormNewStatus').value;
            if (newStatus === '0') {
                return confirm(`Remove "${name}" from customer storefront pages?\nCustomers will no longer see or be able to order this item.`);
            } else {
                return confirm(`Restore "${name}" to customer storefront pages so customers can view and purchase it?`);
            }
        }

        function confirmDeletePermanent() {
            const select = document.getElementById('removeProductSelect');
            const opt = select ? select.selectedOptions[0] : null;
            const name = opt ? opt.getAttribute('data-name') : 'this product';
            return confirm(`⚠️ PERMANENT DELETE WARNING:\nAre you sure you want to permanently delete "${name}" from the database catalog?\n\nThis cannot be undone! If you just want to hide it from customer pages, use "Remove from Customer Storefront" instead.`);
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

        if (removeProductModal) {
            removeProductModal.addEventListener('click', (e) => {
                if (e.target === removeProductModal) closeRemoveProductModal();
            });
        }
    </script>
</body>
</html>
