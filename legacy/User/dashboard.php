<?php
// Orders and bookings dashboard for users and admins.

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/function.php';

$user = getCurrentUser();

// Must be logged in to view dashboard
if (!$user) {
    header('Location: ../Login/auth.php?redirect=User/dashboard.php&msg=login_required');
    exit;
}

$filterStatus = isset($_GET['status']) && !empty($_GET['status']) ? sanitize_input($_GET['status']) : null;
$searchKeyword = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';

// Export orders to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Customers only see their own orders; admins see all
    $exportUserId = ($user['role'] === 'customer') ? $user['id'] : null;
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

// Handle status updates and deletes (admin only)
$actionMsg = '';
$errorMsg  = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errorMsg = "Security token invalid or expired. Please refresh the page.";
    } elseif ($_POST['action'] === 'change_password') {
        $currentPwd = $_POST['current_password'] ?? '';
        $newPwd     = $_POST['new_password'] ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';

        $pwdResult = changeUserPassword($pdo, $user['id'], $currentPwd, $newPwd, $confirmPwd);
        if ($pwdResult['success']) {
            $actionMsg = $pwdResult['message'];
        } else {
            $errorMsg = $pwdResult['message'];
        }
    } elseif (!isAdmin()) {
        $errorMsg = "Unauthorized action: Only store administrators can modify or delete orders.";
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

// Fetch orders for customer or admin
if ($user['role'] === 'customer') {
    $orders = searchOrders($pdo, $searchKeyword, $filterStatus, $user['id']);
    $pageTitle = "My Bakery Orders";
} else {
    $orders = searchOrders($pdo, $searchKeyword, $filterStatus, null);
    $pageTitle = "All Customer Orders & Reservations";
}

// Calculate summary stats
if ($user['role'] === 'customer') {
    $statOrders = getUserOrders($pdo, $user['id']);
    $statTotalLabel = 'My Total Orders';
    $statPendingLabel = 'Pending Confirmation';
    $statConfirmedLabel = 'Confirmed & Baking';
    $statCompletedLabel = 'Completed / Picked Up';
} else {
    $statOrders = getAllOrders($pdo);
    $statTotalLabel = 'Total Orders in DB';
    $statPendingLabel = 'Pending Confirmation';
    $statConfirmedLabel = 'Confirmed & Baking';
    $statCompletedLabel = 'Completed / Picked Up';
}
$totalCount = count($statOrders);
$pendingCount = count(array_filter($statOrders, fn($o) => $o['status'] === 'Pending'));
$confirmedCount = count(array_filter($statOrders, fn($o) => $o['status'] === 'Confirmed'));
$completedCount = count(array_filter($statOrders, fn($o) => $o['status'] === 'Completed'));

// Helper for customer initials
$nameParts = explode(' ', trim($user['name']));
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Asentista's Bakery & Coffee</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/ASENTISTA FINAL.png">
    <link rel="apple-touch-icon" href="../assets/ASENTISTA FINAL.png">
    <link rel="stylesheet" href="../style.css">
    <style>
        :root {
            --dash-bg: #FAF7F2;
            --dash-card-bg: #FFFFFF;
            --dash-brown: #2B1B15;
            --dash-brown-light: #442C23;
            --dash-gold: #FFAE34;
            --dash-gold-deep: #D97706;
            --dash-border: #E8DFD5;
            --dash-text-muted: #6B5B52;
            --dash-ease: cubic-bezier(0.16, 1, 0.3, 1);
        }

        body {
            background-color: var(--dash-bg);
            background-image: 
                radial-gradient(circle at 10% 15%, rgba(255, 174, 52, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 90% 85%, rgba(217, 119, 6, 0.04) 0%, transparent 40%);
            min-height: 100vh;
            color: var(--dash-brown);
        }

        /* Modern Glass Navbar */
        .dash-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(78, 56, 46, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(43, 27, 21, 0.04);
        }

        .dash-nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--dash-brown);
        }

        .brand-disc {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FFAE34 0%, #D97706 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(255, 174, 52, 0.35);
            overflow: hidden;
            flex-shrink: 0;
            transition: transform 0.2s var(--dash-ease);
        }

        .brand-block:hover .brand-disc {
            transform: scale(1.05) rotate(5deg);
        }

        .brand-disc img {
            width: 26px;
            height: 26px;
            object-fit: contain;
        }

        .brand-words-title {
            font-family: var(--font-serif);
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            line-height: 1.1;
            color: var(--dash-brown);
        }

        .brand-words-sub {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: var(--dash-gold-deep);
            text-transform: uppercase;
        }

        /* Right Actions Bar */
        .dash-nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.5rem 0.85rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--dash-brown);
            text-decoration: none;
            border-radius: 8px;
            background: transparent;
            border: 1px solid transparent;
            transition: all 0.16s var(--dash-ease);
        }

        .nav-link-btn:hover {
            background: rgba(78, 56, 46, 0.06);
            color: var(--dash-brown);
            transform: translateY(-1px);
        }

        .user-profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #F4EFEA;
            padding: 4px 10px 4px 6px;
            border-radius: 30px;
            border: 1px solid rgba(78, 56, 46, 0.1);
        }

        .user-avatar-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--dash-brown);
            color: var(--dash-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .user-info-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .user-info-name {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--dash-brown);
        }

        .user-info-role {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--dash-gold-deep);
        }

        .btn-pwd-change {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.45rem 0.85rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--dash-brown);
            background: #FFFFFF;
            border: 1.5px solid var(--dash-border);
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(43, 27, 21, 0.05);
            transition: all 0.16s var(--dash-ease);
        }

        .btn-pwd-change:hover {
            border-color: var(--dash-gold-deep);
            background: #FFFDF9;
            color: var(--dash-gold-deep);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(217, 119, 6, 0.12);
        }

        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0.45rem 0.85rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: #991B1B;
            background: #FEF2F2;
            border: 1px solid #FECACA;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.16s var(--dash-ease);
        }

        .btn-logout:hover {
            background: #DC2626;
            color: #FFFFFF;
            border-color: #B91C1C;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(220, 38, 38, 0.2);
        }

        /* Dashboard Container */
        .dashboard-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 5rem 1.5rem;
        }

        /* Header Banner Card */
        .dash-hero-card {
            background: #FFFFFF;
            border: 1px solid var(--dash-border);
            border-radius: 16px;
            padding: 2rem 2.2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            box-shadow: 0 6px 24px rgba(43, 27, 21, 0.04);
            position: relative;
            overflow: hidden;
        }

        .dash-hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #FFAE34, #D97706, #2B1B15);
        }

        .dash-title {
            font-family: var(--font-serif);
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--dash-brown);
            margin: 0 0 0.35rem 0;
            letter-spacing: -0.02em;
        }

        .dash-subtitle {
            font-size: 0.9rem;
            color: var(--dash-text-muted);
            margin: 0;
            line-height: 1.5;
        }

        .dash-hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-dash-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.4rem;
            font-size: 0.88rem;
            font-weight: 700;
            color: #FFFFFF;
            background: linear-gradient(180deg, #3A231A 0%, #251610 100%);
            border: 1px solid #1A0F0A;
            border-radius: 10px;
            text-decoration: none;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16), 0 3px 10px rgba(37, 22, 16, 0.22);
            transition: all 0.16s var(--dash-ease);
        }

        .btn-dash-primary:hover {
            background: linear-gradient(180deg, #482C21 0%, #2F1C14 100%);
            transform: translateY(-2px);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.22), 0 6px 18px rgba(37, 22, 16, 0.3);
            color: #FFFFFF;
        }

        .btn-dash-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.25rem;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--dash-brown);
            background: #FFFFFF;
            border: 1.5px solid var(--dash-border);
            border-radius: 10px;
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(43, 27, 21, 0.05);
            transition: all 0.16s var(--dash-ease);
        }

        .btn-dash-secondary:hover {
            border-color: rgba(78, 56, 46, 0.35);
            background: #FAF7F2;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(43, 27, 21, 0.08);
            color: var(--dash-brown);
        }

        /* KPI Stat Cards */
        .dash-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: #FFFFFF;
            border: 1px solid var(--dash-border);
            border-radius: 14px;
            padding: 1.4rem 1.5rem;
            box-shadow: 0 4px 14px rgba(43, 27, 21, 0.03);
            transition: transform 0.2s var(--dash-ease), box-shadow 0.2s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(43, 27, 21, 0.07);
        }

        .kpi-top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .kpi-badge-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .kpi-total .kpi-badge-icon { background: #F3EDE4; }
        .kpi-pending .kpi-badge-icon { background: #FEF3C7; }
        .kpi-confirmed .kpi-badge-icon { background: #DBEAFE; }
        .kpi-completed .kpi-badge-icon { background: #D1FAE5; }

        .kpi-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--dash-brown);
            line-height: 1;
            margin-bottom: 0.4rem;
            letter-spacing: -0.02em;
        }

        .kpi-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--dash-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Filter Pills & Search */
        .dash-controls-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .segmented-tabs {
            display: inline-flex;
            background: #EFE8DD;
            padding: 4px;
            border-radius: 12px;
            border: 1px solid rgba(78, 56, 46, 0.08);
            gap: 4px;
            flex-wrap: wrap;
        }

        .seg-tab-btn {
            padding: 8px 16px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--dash-text-muted);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.16s var(--dash-ease);
        }

        .seg-tab-btn:hover {
            color: var(--dash-brown);
        }

        .seg-tab-btn.active {
            background: #FFFFFF;
            color: var(--dash-brown);
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(43, 27, 21, 0.08);
        }

        .dash-search-box {
            display: flex;
            align-items: center;
            position: relative;
        }

        .dash-search-input {
            padding: 9px 14px 9px 38px;
            font-size: 0.88rem;
            border: 1.5px solid var(--dash-border);
            border-radius: 10px;
            width: 250px;
            background: #FFFFFF;
            color: var(--dash-brown);
            outline: none;
            transition: border-color 0.16s ease, box-shadow 0.16s ease;
        }

        .dash-search-input:focus {
            border-color: var(--dash-gold-deep);
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
        }

        .dash-search-icon {
            position: absolute;
            left: 12px;
            color: #8C7A70;
            pointer-events: none;
            display: flex;
            align-items: center;
        }

        .btn-search-submit {
            margin-left: 6px;
            padding: 9px 15px;
            font-size: 0.82rem;
            font-weight: 700;
            background: var(--dash-brown);
            color: #FFFFFF;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            transition: all 0.16s var(--dash-ease);
        }

        .btn-search-submit:hover {
            background: var(--dash-brown-light);
            transform: translateY(-1px);
        }

        /* Orders Table Card */
        .dash-table-card {
            background: #FFFFFF;
            border: 1px solid var(--dash-border);
            border-radius: 16px;
            box-shadow: 0 8px 26px rgba(43, 27, 21, 0.04);
            overflow: hidden;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .modern-table th {
            background: #2B1B15;
            color: #FFF8F0;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 1.1rem 1.25rem;
            border: none;
        }

        .modern-table td {
            padding: 1.1rem 1.25rem;
            border-bottom: 1px solid #F0EAE1;
            font-size: 0.88rem;
            color: var(--dash-brown);
            vertical-align: middle;
            transition: background-color 0.12s ease;
        }

        .modern-table tbody tr:hover td {
            background-color: #FBF8F3;
        }

        .order-id-tag {
            font-family: var(--font-mono, monospace);
            font-size: 0.82rem;
            font-weight: 700;
            background: #F3ECE2;
            color: #78350F;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        .customer-title-block strong {
            display: block;
            font-size: 0.92rem;
            color: var(--dash-brown);
        }

        .customer-notes-bubble {
            font-size: 0.75rem;
            color: var(--dash-text-muted);
            font-style: italic;
            margin-top: 3px;
            line-height: 1.35;
        }

        .price-text {
            font-weight: 800;
            color: var(--dash-brown);
            font-size: 0.95rem;
        }

        /* Status Pills */
        .pill-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .pill-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        .pill-Pending {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FDE68A;
        }
        .pill-Pending .pill-status-dot {
            background: #F59E0B;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25);
            animation: pulseDot 2s infinite;
        }

        .pill-Confirmed {
            background: #DBEAFE;
            color: #1E40AF;
            border: 1px solid #BFDBFE;
        }
        .pill-Confirmed .pill-status-dot { background: #3B82F6; }

        .pill-Completed {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        .pill-Completed .pill-status-dot { background: #10B981; }

        .pill-Cancelled {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }
        .pill-Cancelled .pill-status-dot { background: #EF4444; }

        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.25); }
        }

        @media (max-width: 960px) {
            .dash-stats-grid { grid-template-columns: 1fr 1fr; }
            .dash-table-card { overflow-x: auto; }
            .dash-nav-container { flex-direction: column; align-items: flex-start; }
            .dash-search-input { width: 100%; }
        }
    </style>
</head>
<body>

    <!-- Modern Luxury Navbar -->
    <nav class="dash-nav">
        <div class="dash-nav-container">
            <a href="../index.php" class="brand-block">
                <div class="brand-disc">
                    <img src="../assets/ASENTISTA FINAL.png" alt="Asentista Bakery">
                </div>
                <div>
                    <div class="brand-words-title">ASENTISTA'S</div>
                    <div class="brand-words-sub">Artisan Bakery & Coffee</div>
                </div>
            </a>

            <div class="dash-nav-actions">
                <a href="../index.php" class="nav-link-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span>Storefront Menu</span>
                </a>

                <a href="../Cart/cart.php" class="nav-link-btn" title="View Shopping Cart">
                    <span>🛒 View Cart</span>
                </a>

                <?php if (isAdmin($pdo)): ?>
                    <a href="../Admin/admin.php" class="nav-link-btn" style="background: #FEF3C7; color: #92400E; border-color: #FDE68A; font-weight: 700;">
                        <span>👑 Admin Console</span>
                    </a>
                <?php endif; ?>

                <?php if ($user): ?>
                    <div class="user-profile-badge">
                        <div class="user-avatar-circle"><?php echo $initials; ?></div>
                        <div class="user-info-text">
                            <span class="user-info-name"><?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?></span>
                            <span class="user-info-role"><?php echo htmlspecialchars($user['role']); ?></span>
                        </div>
                    </div>

                    <button type="button" onclick="openUserChangePwdModal()" class="btn-pwd-change" title="Change Account Password">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Password</span>
                    </button>

                    <a href="../Login/logout.php" class="btn-logout" title="Sign Out">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Logout</span>
                    </a>
                <?php else: ?>
                    <a href="../Login/auth.php" class="btn-dash-primary" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                        <span>Sign In / Register</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="dashboard-content">

        <!-- Hero Card -->
        <div class="dash-hero-card">
            <div>
                <h1 class="dash-title"><?php echo $pageTitle; ?></h1>
                <p class="dash-subtitle">
                    Real-time status tracking of your artisan sourdough breads, pastry table reservations, and oven dispatch queue.
                </p>
            </div>
            <div class="dash-hero-actions">
                <?php if (isAdmin($pdo)): ?>
                    <a href="../Admin/admin.php" class="btn-dash-secondary" style="border-color: #F59E0B; color: #B45309;" title="Open Executive Console">
                        <span>👑 Admin Dispatch Board &rarr;</span>
                    </a>
                <?php endif; ?>
                <a href="dashboard.php?export=csv<?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?><?php echo $searchKeyword ? '&q=' . urlencode($searchKeyword) : ''; ?>" class="btn-dash-secondary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>Export CSV</span>
                </a>
                <a href="../index.php#bread-menu" class="btn-dash-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Place New Order</span>
                </a>
            </div>
        </div>

        <!-- Flash Notices -->
        <?php if (!empty($actionMsg)): ?>
            <div style="background-color: #ECFDF5; color: #065F46; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid #A7F3D0; border-left: 5px solid #10B981; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.2rem;">✅</span>
                <div><?php echo $actionMsg; ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div style="background-color: #FEF2F2; color: #991B1B; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid #FECACA; border-left: 5px solid #DC2626; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.2rem;">⚠️</span>
                <div><?php echo $errorMsg; ?></div>
            </div>
        <?php endif; ?>

        <!-- KPI Stat Cards -->
        <div class="dash-stats-grid">
            <div class="kpi-card kpi-total">
                <div class="kpi-top-row">
                    <span class="kpi-label"><?php echo htmlspecialchars($statTotalLabel); ?></span>
                    <div class="kpi-badge-icon">📦</div>
                </div>
                <div class="kpi-value"><?php echo $totalCount; ?></div>
            </div>

            <div class="kpi-card kpi-pending">
                <div class="kpi-top-row">
                    <span class="kpi-label"><?php echo htmlspecialchars($statPendingLabel); ?></span>
                    <div class="kpi-badge-icon">⏳</div>
                </div>
                <div class="kpi-value"><?php echo $pendingCount; ?></div>
            </div>

            <div class="kpi-card kpi-confirmed">
                <div class="kpi-top-row">
                    <span class="kpi-label"><?php echo htmlspecialchars($statConfirmedLabel); ?></span>
                    <div class="kpi-badge-icon">👨‍🍳</div>
                </div>
                <div class="kpi-value"><?php echo $confirmedCount; ?></div>
            </div>

            <div class="kpi-card kpi-completed">
                <div class="kpi-top-row">
                    <span class="kpi-label"><?php echo htmlspecialchars($statCompletedLabel); ?></span>
                    <div class="kpi-badge-icon">✨</div>
                </div>
                <div class="kpi-value"><?php echo $completedCount; ?></div>
            </div>
        </div>

        <!-- Filter Tabs & Search Controls -->
        <div class="dash-controls-bar">
            <div class="segmented-tabs">
                <a href="dashboard.php<?php echo $searchKeyword ? '?q=' . urlencode($searchKeyword) : ''; ?>" class="seg-tab-btn <?php echo !$filterStatus ? 'active' : ''; ?>">All Orders (<?php echo $totalCount; ?>)</a>
                <a href="dashboard.php?status=Pending<?php echo $searchKeyword ? '&q=' . urlencode($searchKeyword) : ''; ?>" class="seg-tab-btn <?php echo $filterStatus === 'Pending' ? 'active' : ''; ?>">Pending (<?php echo $pendingCount; ?>)</a>
                <a href="dashboard.php?status=Confirmed<?php echo $searchKeyword ? '&q=' . urlencode($searchKeyword) : ''; ?>" class="seg-tab-btn <?php echo $filterStatus === 'Confirmed' ? 'active' : ''; ?>">Confirmed (<?php echo $confirmedCount; ?>)</a>
                <a href="dashboard.php?status=Completed<?php echo $searchKeyword ? '&q=' . urlencode($searchKeyword) : ''; ?>" class="seg-tab-btn <?php echo $filterStatus === 'Completed' ? 'active' : ''; ?>">Completed (<?php echo $completedCount; ?>)</a>
                <a href="dashboard.php?status=Cancelled<?php echo $searchKeyword ? '&q=' . urlencode($searchKeyword) : ''; ?>" class="seg-tab-btn <?php echo $filterStatus === 'Cancelled' ? 'active' : ''; ?>">Cancelled</a>
            </div>

            <!-- Search Form -->
            <form method="GET" action="dashboard.php" class="dash-search-box">
                <?php if ($filterStatus): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                <?php endif; ?>
                <span class="dash-search-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </span>
                <input type="text" name="q" class="dash-search-input" placeholder="Search orders..." value="<?php echo htmlspecialchars($searchKeyword); ?>">
                <button type="submit" class="btn-search-submit">Search</button>
            </form>
        </div>

        <!-- Orders Table Card -->
        <div class="dash-table-card">
            <?php if (empty($orders)): ?>
                <div style="padding: 4.5rem 2rem; text-align: center;">
                    <span style="font-size: 3rem; display: block; margin-bottom: 1rem;">🥖</span>
                    <h3 style="font-family: var(--font-serif); font-size: 1.4rem; color: var(--dash-brown); margin: 0 0 0.5rem 0;">No Orders Found</h3>
                    <p style="color: var(--dash-text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
                        No past orders or reservations match your current filter criteria.
                    </p>
                    <a href="../index.php#bread-menu" class="btn-dash-primary">
                        Browse Fresh Bakery Menu &rarr;
                    </a>
                </div>
            <?php else: ?>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer Info</th>
                            <th>Contact</th>
                            <th>Items & Breakdown</th>
                            <th>Total Price</th>
                            <th>Order Type</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Manage Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $row): ?>
                            <tr>
                                <td>
                                    <span class="order-id-tag">#<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></span>
                                </td>
                                <td>
                                    <div class="customer-title-block">
                                        <strong><?php echo htmlspecialchars($row['customer_name']); ?></strong>
                                        <?php if (!empty($row['special_notes'])): ?>
                                            <div class="customer-notes-bubble">
                                                Note: "<?php echo htmlspecialchars($row['special_notes']); ?>"
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['customer_phone']); ?></td>
                                <td>
                                    <span style="font-weight: 600;"><?php echo htmlspecialchars($row['item_name']); ?></span>
                                </td>
                                <td>
                                    <span class="price-text">₱<?php echo number_format($row['item_price'], 2); ?></span>
                                </td>
                                <td>
                                    <span style="font-weight: 500;">
                                        <?php 
                                        $type = $row['order_type'];
                                        $icon = strpos($type, 'Pickup') !== false ? '🛍️ ' : (strpos($type, 'Dine') !== false ? '🍽️ ' : '🚚 ');
                                        echo $icon . htmlspecialchars($type);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        $pm = $row['payment_method'] ?? 'Cash on Delivery (COD)';
                                        $pmBadgeBg = '#F3F4F6';
                                        $pmBadgeColor = '#374151';
                                        $pmBadgeBorder = '#E5E7EB';
                                        $pmText = '💵 COD';
                                        if (stripos($pm, 'gcash') !== false) {
                                            $pmBadgeBg = '#EFF6FF';
                                            $pmBadgeColor = '#1E40AF';
                                            $pmBadgeBorder = '#BFDBFE';
                                            $pmText = '📱 GCash';
                                        } elseif (stripos($pm, 'bank') !== false) {
                                            $pmBadgeBg = '#ECFDF5';
                                            $pmBadgeColor = '#065F46';
                                            $pmBadgeBorder = '#A7F3D0';
                                            $pmText = '🏦 Bank';
                                        }
                                    ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: 0.74rem; font-weight: 700; background: <?php echo $pmBadgeBg; ?>; color: <?php echo $pmBadgeColor; ?>; border: 1px solid <?php echo $pmBadgeBorder; ?>; white-space: nowrap;">
                                        <?php echo $pmText; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color: var(--dash-text-muted); font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($row['reservation_date']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="pill-status pill-<?php echo htmlspecialchars($row['status']); ?>">
                                        <span class="pill-status-dot"></span>
                                        <span><?php echo htmlspecialchars($row['status']); ?></span>
                                    </span>
                                </td>
                                <td>
                                    <?php if (isAdmin()): ?>
                                        <form method="POST" action="dashboard.php" style="display: flex; align-items: center; gap: 6px; margin: 0;">
                                            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                            <select name="new_status" style="padding: 5px 8px; font-size: 0.78rem; border: 1px solid #D1D5DB; border-radius: 6px; background: #FFF;">
                                                <option value="Pending" <?php echo $row['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Confirmed" <?php echo $row['status'] === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="Completed" <?php echo $row['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="Cancelled" <?php echo $row['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" class="btn-search-submit" style="padding: 5px 10px; font-size: 0.75rem; margin: 0;">Save</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size: 0.78rem; color: #9CA3AF; font-style: italic;">
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

    <!-- Luxury Change Password Modal -->
    <div id="userChangePwdModal" style="display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(20, 12, 9, 0.65); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: #FFFFFF; border-radius: 18px; max-width: 440px; width: 100%; padding: 2.2rem; box-shadow: 0 20px 45px rgba(0,0,0,0.25); border: 1px solid #E8DFD5; box-sizing: border-box; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #FFAE34, #D97706);"></div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        🔑
                    </div>
                    <div>
                        <h3 style="margin: 0; font-family: var(--font-serif); font-size: 1.3rem; color: var(--dash-brown); font-weight: 700;">Change Password</h3>
                        <p style="margin: 0; font-size: 0.78rem; color: var(--dash-text-muted);">Keep your bakery account secure</p>
                    </div>
                </div>
                <button type="button" onclick="closeUserChangePwdModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #8C7A70; line-height: 1;">&times;</button>
            </div>

            <form method="POST" action="dashboard.php">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                <input type="hidden" name="action" value="change_password">

                <div style="margin-bottom: 1.1rem;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 5px; color: var(--dash-brown); text-transform: uppercase; letter-spacing: 0.04em;">Current Password *</label>
                    <input type="password" name="current_password" required placeholder="Enter current password" style="width: 100%; padding: 11px 14px; border: 1.5px solid var(--dash-border); border-radius: 9px; box-sizing: border-box; font-size: 0.9rem; outline: none; background: #FCFAF7;">
                </div>

                <div style="margin-bottom: 1.1rem;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 5px; color: var(--dash-brown); text-transform: uppercase; letter-spacing: 0.04em;">New Password *</label>
                    <input type="password" name="new_password" required minlength="8" placeholder="Min 8 characters (1 number/symbol)" style="width: 100%; padding: 11px 14px; border: 1.5px solid var(--dash-border); border-radius: 9px; box-sizing: border-box; font-size: 0.9rem; outline: none; background: #FCFAF7;">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 5px; color: var(--dash-brown); text-transform: uppercase; letter-spacing: 0.04em;">Confirm New Password *</label>
                    <input type="password" name="confirm_password" required minlength="8" placeholder="Repeat new password" style="width: 100%; padding: 11px 14px; border: 1.5px solid var(--dash-border); border-radius: 9px; box-sizing: border-box; font-size: 0.9rem; outline: none; background: #FCFAF7;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeUserChangePwdModal()" style="padding: 10px 18px; border-radius: 9px; cursor: pointer; border: 1px solid var(--dash-border); background: #F3EDE4; font-weight: 600; font-size: 0.85rem; color: var(--dash-brown);">Cancel</button>
                    <button type="submit" class="btn-dash-primary" style="padding: 10px 20px; font-size: 0.85rem;">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUserChangePwdModal() {
            const m = document.getElementById('userChangePwdModal');
            if (m) m.style.display = 'flex';
        }
        function closeUserChangePwdModal() {
            const m = document.getElementById('userChangePwdModal');
            if (m) m.style.display = 'none';
        }
        const userPwdModal = document.getElementById('userChangePwdModal');
        if (userPwdModal) {
            userPwdModal.addEventListener('click', (e) => {
                if (e.target === userPwdModal) closeUserChangePwdModal();
            });
        }
    </script>
</body>
</html>
