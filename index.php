<?php
/**
 * Asentista Bakery - Official Web Application
 * Pure PHP implementation matching Figma design specifications with complete interactive & database functionality.
 */

// Include modular database configuration & helper functions
require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/database/function.php';

// Site Entry Rule: If not logged in and not in guest mode, redirect directly to Auth Portal
if (!isLoggedIn() && empty($_SESSION['guest_mode']) && !isset($_GET['guest'])) {
    header('Location: auth.php');
    exit;
}

// Asset path
$assetPath = 'assets/';

// Get currently logged-in user if available
$currentUser = getCurrentUser();

// Get live Cart Summary
$cartSummary = getCartSummary($pdo);
$cartCount = $cartSummary['total_items'];

// Bread Menu Items
$breadMenuItems = [
    [
        'name'  => 'Crunchy Crust',
        'price' => '₱35.00',
        'raw_price' => 35.00,
        'desc'  => 'Golden-baked crust with an airy, soft interior. Perfect for morning dips or artisan sandwiches.',
        'image' => $assetPath . 'bread-with-appetizing-crunchy-crust-top-view-isolated-on-white-e1656042939392.jpg'
    ],
    [
        'name'  => 'Crescent Roll',
        'price' => '₱30.00',
        'raw_price' => 30.00,
        'desc'  => 'Buttery, flaky crescent pastry topped with toasted poppy seeds.',
        'image' => $assetPath . 'top-view-of-crescent-roll-with-poppy-seeds-on-white-background-e1656042946947.jpg'
    ],
    [
        'name'  => 'Round Rye',
        'price' => '₱45.00',
        'raw_price' => 45.00,
        'desc'  => 'Traditional European sourdough rye loaf with rich earthy undertones and dense crumb.',
        'image' => $assetPath . 'traditional-round-rye-bread-e1656042958429.jpg'
    ],
    [
        'name'  => 'Yeast Custard',
        'price' => '₱40.00',
        'raw_price' => 40.00,
        'desc'  => 'Sweet yeast bun filled with silky vanilla custard and spiced caramelized apple.',
        'image' => $assetPath . 'yeast-bun-with-apple-and-custard-filling-e1656042965940.jpg'
    ],
    [
        'name'  => 'Bially Sandwich',
        'price' => '₱50.00',
        'raw_price' => 50.00,
        'desc'  => 'Classic bialy bread roll baked with savory roasted onion and savory seeds.',
        'image' => $assetPath . 'breads-e1656042972619.jpg'
    ],
    [
        'name'  => 'Bun Messes',
        'price' => '₱28.00',
        'raw_price' => 28.00,
        'desc'  => 'Tender, pillowy brioche bun dusted with powdered sugar and natural sweetness.',
        'image' => $assetPath . 'bun-e1656042983426.jpg'
    ],
    [
        'name'  => 'Slice Bread',
        'price' => '₱60.00',
        'raw_price' => 60.00,
        'desc'  => 'Daily sliced sandwich rye loaf made from whole grains and natural levain.',
        'image' => $assetPath . 'rye-bread-slice-on-a-white-background--e1656042993568.jpg'
    ],
    [
        'name'  => 'Bun Roll',
        'price' => '₱25.00',
        'raw_price' => 25.00,
        'desc'  => 'Soft dinner roll with a golden finish, perfect with butter or jam.',
        'image' => $assetPath . 'bun-1-e1656043014357.jpg'
    ]
];

// Price Lists
$breadPrices = [
    ['item' => 'Baguette',  'price' => '₱25.00', 'raw_price' => 25.00, 'img' => $assetPath . 'bread-e1656042861839-pqroqtezjh2g0607d0pphz5ddrx6ppa7b44no9oloo.jpg'],
    ['item' => 'Croissant', 'price' => '₱25.00', 'raw_price' => 25.00, 'img' => $assetPath . 'top-view-of-crescent-roll-with-poppy-seeds-on-white-background-e1656042946947.jpg'],
    ['item' => 'Sourdough', 'price' => '₱25.00', 'raw_price' => 25.00, 'img' => $assetPath . 'assortment-of-artisan-bread-e1656042887278.jpg'],
    ['item' => 'Ciabatta',  'price' => '₱25.00', 'raw_price' => 25.00, 'img' => $assetPath . 'italian-ciabatta-bread-on-black-slate-with-herbs-and-olives--e1656043199744 (1).jpg'],
    ['item' => 'Brioche',   'price' => '₱25.00', 'raw_price' => 25.00, 'img' => $assetPath . 'homemade-pumpkin-bread-e1656042901513.jpg']
];

$beveragePrices = [
    ['item' => 'Americano',  'price' => '₱55.00', 'raw_price' => 55.00, 'img' => $assetPath . 'banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).jpg'],
    ['item' => 'Cold Brew',  'price' => '₱55.00', 'raw_price' => 55.00, 'img' => $assetPath . 'banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).jpg'],
    ['item' => 'Carbonated', 'price' => '₱35.00', 'raw_price' => 35.00, 'img' => $assetPath . 'cheese-platter-with-nuts-honey-and-bread-square-crop-e1656043218344 (1).jpg'],
    ['item' => 'Cortado',    'price' => '₱69.00', 'raw_price' => 69.00, 'img' => $assetPath . 'banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).jpg'],
    ['item' => 'Macchiato',  'price' => '₱69.00', 'raw_price' => 69.00, 'img' => $assetPath . 'banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).jpg']
];

// Instagram Gallery Photos
$instagramPhotos = [
    [
        'thumb' => $assetPath . 'banana-bread-cake-with-banana-chocolate-walnut-traditional-american-cuisine-top-view-e1656043212111 (1).jpg',
        'title' => 'Banana Chocolate Walnut Loaf'
    ],
    [
        'thumb' => $assetPath . 'banana-bread-e1656043181560 (1).jpg',
        'title' => 'Golden Banana Loaf'
    ],
    [
        'thumb' => $assetPath . 'banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).jpg',
        'title' => 'Morning Blueberry Toast & Coffee'
    ],
    [
        'thumb' => $assetPath . 'cheese-platter-with-nuts-honey-and-bread-square-crop-e1656043218344 (1).jpg',
        'title' => 'Artisan Cheese Platter & Bread'
    ],
    [
        'thumb' => $assetPath . 'cheese-rolls-e1656043205913 (1).jpg',
        'title' => 'Warm Baked Cheese Rolls'
    ],
    [
        'thumb' => $assetPath . 'easter-orthodox-sweet-bread-e1656043173915 (1).jpg',
        'title' => 'Easter Sweet Braid Bread'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asentista's Bakery - Special Bread & Artisan Pastries</title>
    <meta name="description" content="Asentista's Bakery - Baked fresh daily with natural organic ingredients. Special artisan bread, pastries, and handcrafted beverages.">
    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php if (isAdmin()): ?>
        <div style="background-color: #2B1B15; color: #EDE0D4; padding: 6px 16px; font-size: 0.8rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #FFAE34; z-index: 1000; position: relative;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1rem;">👑</span>
                <span>Logged in as <strong>Administrator (Kyle Asentista)</strong> — Viewing Customer Storefront</span>
            </div>
            <a href="admin.php" style="background: #FFAE34; color: #2B1B15; font-weight: 700; padding: 4px 12px; border-radius: 3px; text-decoration: none; font-size: 0.76rem;">
                Open Admin Operations Center →
            </a>
        </div>
    <?php endif; ?>

    <!-- ==========================================
         NAVIGATION BAR
         ========================================== -->
    <nav class="site-nav" id="mainNav">
        <div class="container nav-container">
            <!-- Brand Logo (Scroll to Top) -->
            <a href="#hero" class="brand-logo-wrap" aria-label="Asentista's Bakery Home">
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

            <!-- Navigation Links -->
            <div class="nav-menu">
                <a href="#hero" class="nav-link active">HOME</a>
                <a href="#fresh-bread" class="nav-link">ABOUT</a>
                <a href="#bread-menu" class="nav-link">MENUS</a>
                <a href="#price-section" class="nav-link">PRICES</a>
                <a href="dashboard.php" class="nav-link" title="View Orders & Database Portal">ORDERS PORTAL</a>
                <a href="#site-footer" class="nav-link">CONTACT</a>
            </div>

            <!-- Nav Actions (Search, Cart, Auth & Call Badge) -->
            <div class="nav-actions">
                <button type="button" class="search-btn" id="searchTriggerBtn" title="Search Bakery Menu (Press /)" aria-label="Search Menu">
                    <svg class="search-icon-svg" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </button>

                <!-- Shopping Cart Icon Button with Live Counter Badge -->
                <a href="cart.php" class="nav-cart-btn" id="navCartBtn" title="View Shopping Cart">
                    <span style="font-size: 1.15rem;">🛒</span>
                    <span class="cart-count-badge <?php echo $cartCount > 0 ? 'active' : ''; ?>" id="cartCountBadge">
                        <?php echo $cartCount; ?>
                    </span>
                </a>

                <!-- Auth State Button (Login / Register or User Profile) -->
                <?php if ($currentUser): ?>
                    <a href="dashboard.php" class="order-badge" style="background-color: var(--color-brown-mid);" title="Logged in as <?php echo htmlspecialchars($currentUser['name']); ?>">
                        <span style="font-size: 0.9rem;">👤</span>
                        <div>
                            <div class="order-text-top"><?php echo strtoupper($currentUser['role']); ?></div>
                            <div class="order-text-bottom"><?php echo htmlspecialchars(explode(' ', $currentUser['name'])[0]); ?></div>
                        </div>
                    </a>
                    <a href="logout.php" class="search-btn" title="Log Out" aria-label="Log Out" style="font-size: 0.72rem; font-weight: 700;">
                        EXIT
                    </a>
                <?php else: ?>
                    <a href="auth.php" class="order-badge" style="background-color: var(--color-brown-mid); text-decoration: none;" title="Customer & Admin Login">
                        <span style="font-size: 0.9rem;">🔐</span>
                        <div>
                            <div class="order-text-top">ACCOUNT</div>
                            <div class="order-text-bottom">LOGIN / REGISTER</div>
                        </div>
                    </a>
                <?php endif; ?>

                <a href="tel:09940058425" class="order-badge pulse-badge" id="orderBadgeBtn" title="Call or Order Online">
                    <svg class="phone-icon-svg" viewBox="0 0 24 24">
                        <path d="M6.62 10.79a15.053 15.053 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1.02l-2.2 2.2z"/>
                    </svg>
                    <div>
                        <div class="order-text-top">ORDER NOW</div>
                        <div class="order-text-bottom">CALL: 0994 005 8425</div>
                    </div>
                </a>

                <!-- Mobile Hamburger Toggle Button -->
                <button type="button" class="mobile-toggle-btn" id="mobileToggleBtn" aria-label="Toggle Navigation Menu">
                    <span class="hamburger-bar"></span>
                    <span class="hamburger-bar"></span>
                    <span class="hamburger-bar"></span>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Drawer -->
        <div class="mobile-nav-drawer" id="mobileNavDrawer">
            <a href="#hero" class="mobile-nav-link">HOME</a>
            <a href="#fresh-bread" class="mobile-nav-link">ABOUT</a>
            <a href="#bread-menu" class="mobile-nav-link">MENUS</a>
            <a href="#price-section" class="mobile-nav-link">PRICES</a>
            <a href="cart.php" class="mobile-nav-link">🛒 VIEW CART (<?php echo $cartCount; ?>)</a>
            <a href="dashboard.php" class="mobile-nav-link">ORDERS PORTAL</a>
            <?php if ($currentUser): ?>
                <a href="logout.php" class="mobile-nav-link">LOGOUT (<?php echo htmlspecialchars($currentUser['name']); ?>)</a>
            <?php else: ?>
                <a href="auth.php" class="mobile-nav-link">LOGIN / REGISTER</a>
            <?php endif; ?>
            <a href="#site-footer" class="mobile-nav-link">CONTACT</a>
        </div>
    </nav>

    <!-- ==========================================
         HERO SECTION (Special Bread)
         ========================================== -->
    <section class="hero-section" id="hero">
        <div class="container">
            <div class="hero-grid">
                <!-- Left Column: Typography & Natural Organic Badge -->
                <div class="hero-content-col">
                    <h1 class="hero-text-special">SPECIAL</h1>
                    <h1 class="hero-text-bread">BREAD</h1>

                    <!-- Organic Product Card (Clickable for Craft Info) -->
                    <div class="organic-card" id="organicProductCard" title="Click to view Organic Craft Details" role="button" tabindex="0">
                        <img src="<?php echo $assetPath; ?>AdobeStock_326195507.jpeg" alt="Natural Organic Bread" class="organic-thumb-img">
                        <div class="organic-card-info">
                            <h3 class="organic-title">Natural Organic Product</h3>
                            <p class="organic-desc">
                                Baked fresh daily with the finest organic ingredients. No preservatives, no shortcuts — just honest craft in every loaf.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Hero Cutout Bread Basket Image -->
                <div class="hero-image-col">
                    <img src="<?php echo $assetPath; ?>AdobeStock_537867381.png" alt="Assorted artisan breads" class="hero-main-bread">
                </div>
            </div>

            <!-- Downward Arrow Box (Scrolls to About) -->
            <div class="hero-arrow-row">
                <button type="button" class="arrow-indicator-box bounce-arrow" id="heroScrollDownBtn" title="Scroll to Fresh Bread" aria-label="Scroll to Fresh Bread section">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M12 5v14M5 12l7 7 7-7"/>
                    </svg>
                </button>
            </div>
        </div>
    </section>

    <!-- ==========================================
         SERVING FRESH BREAD EVERY DAY SECTION
         ========================================== -->
    <section class="fresh-bread-section" id="fresh-bread">
        <div class="container">
            <div class="fresh-bread-grid">
                <!-- Left Column: Multi-Image Collage -->
                <div class="fresh-image-collage">
                    <img src="<?php echo $assetPath; ?>assortment-of-artisan-bread-e1656042887278.jpg" alt="Artisan Bread Assortment" class="collage-img-1">
                    <img src="<?php echo $assetPath; ?>bread-e1656042861839-pqroqtezjh2g0607d0pphz5ddrx6ppa7b44no9oloo.jpg" alt="Fresh Baked Loaves" class="collage-img-2">
                    <img src="<?php echo $assetPath; ?>homemade-pumpkin-bread-e1656042901513.jpg" alt="Pumpkin Bread" class="collage-img-3">
                </div>

                <!-- Right Column: Description & Booking Action -->
                <div>
                    <h2 class="fresh-heading">Serving Fresh Bread<br>Every Day</h2>
                    <p class="fresh-desc">
                        From crusty sourdough to pillowy brioche, we bake a full selection every morning using time-honored techniques and locally sourced grains. Come in early for the best picks.
                    </p>
                    <p class="fresh-subtitle">Book Anytime or Add to Order</p>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <button type="button" class="btn-book-now shimmer-btn" id="bookNowHeroBtn">
                            BOOK NOW
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </button>
                        <a href="#bread-menu" class="btn-book-now" style="background-color: var(--color-brown-mid); text-decoration: none;">
                            🛒 View Catalog
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         FULL-WIDTH QUOTE BANNER SECTION
         ========================================== -->
    <section class="quote-section" id="quote-section">
        <img src="<?php echo $assetPath; ?>AdobeStock_2042265063.jpeg" alt="Artisan bakery table spread" class="quote-bg-img">
        <div class="quote-overlay"></div>
        <div class="container quote-container">
            <div class="quote-content-wrap">
                <p class="quote-text">
                    "The smell of good bread baking, like the sound of lightly flowing water, is indescribable in its evocation of innocence and delight."
                </p>
                <div>
                    <div class="quote-author">Kyle Asentista</div>
                    <div class="quote-role">CEO & Master Baker</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         CHECKOUT BREAD MENU SECTION
         ========================================== -->
    <section class="bread-menu-section" id="bread-menu">
        <div class="container">
            <h2 class="menu-main-heading">Checkout Bread Menu</h2>
            <div class="bread-grid-layout">
                <?php foreach ($breadMenuItems as $item): ?>
                    <div class="bread-card-item" data-name="<?php echo htmlspecialchars($item['name']); ?>">
                        <div class="bread-img-container" onclick="openDetailByName('<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                            <img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="bread-round-thumb">
                            <span class="bread-price-badge"><?php echo htmlspecialchars($item['price']); ?></span>
                        </div>
                        <span class="bread-title-label" onclick="openDetailByName('<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </span>
                        
                        <!-- Interactive Button Group: Add to Cart + Details -->
                        <div class="bread-card-actions">
                            <button type="button" class="btn-card-add-cart" onclick="quickAddToCart('<?php echo htmlspecialchars(addslashes($item['name'])); ?>', <?php echo $item['raw_price']; ?>, '<?php echo htmlspecialchars(addslashes($item['image'])); ?>')">
                                + Add to Cart
                            </button>
                            <button type="button" class="btn-card-quick-view" onclick="openDetailByName('<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                                Details 👁️
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ==========================================
         PRICE MENU SECTION (Bread & Beverages)
         ========================================== -->
    <section class="price-section" id="price-section">
        <div class="container">
            <div class="price-grid-layout">
                <!-- Bread Price Box -->
                <div class="price-card-box">
                    <h3 class="price-box-title">Bread Selection</h3>
                    <div class="price-item-list">
                        <?php foreach ($breadPrices as $entry): ?>
                            <div class="price-row-entry" title="Click to add <?php echo htmlspecialchars($entry['item']); ?> to cart">
                                <span class="item-name-text">
                                    <span>🥖</span>
                                    <?php echo htmlspecialchars($entry['item']); ?>
                                </span>
                                <div class="item-cost-wrap">
                                    <span class="item-cost-text"><?php echo htmlspecialchars($entry['price']); ?></span>
                                    <button type="button" class="btn-add-price-cart" onclick="quickAddToCart('<?php echo htmlspecialchars(addslashes($entry['item'])); ?>', <?php echo $entry['raw_price']; ?>, '<?php echo htmlspecialchars(addslashes($entry['img'])); ?>')">
                                        + Cart
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Beverages Price Box -->
                <div class="price-card-box">
                    <h3 class="price-box-title">Handcrafted Beverages</h3>
                    <div class="price-item-list">
                        <?php foreach ($beveragePrices as $entry): ?>
                            <div class="price-row-entry" title="Click to add <?php echo htmlspecialchars($entry['item']); ?> to cart">
                                <span class="item-name-text">
                                    <span>☕</span>
                                    <?php echo htmlspecialchars($entry['item']); ?>
                                </span>
                                <div class="item-cost-wrap">
                                    <span class="item-cost-text"><?php echo htmlspecialchars($entry['price']); ?></span>
                                    <button type="button" class="btn-add-price-cart" onclick="quickAddToCart('<?php echo htmlspecialchars(addslashes($entry['item'])); ?>', <?php echo $entry['raw_price']; ?>, '<?php echo htmlspecialchars(addslashes($entry['img'])); ?>')">
                                        + Cart
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         FOOTER SECTION
         ========================================== -->
    <footer class="site-footer" id="site-footer">
        <div class="container">
            <!-- Upward Arrow Box (Scroll to Top) -->
            <div class="footer-top-indicator">
                <button type="button" class="arrow-indicator-box bounce-arrow" id="footerScrollTopBtn" title="Scroll to Top" aria-label="Scroll to top of page">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M12 19V5M5 12l7-7 7 7"/>
                    </svg>
                </button>
            </div>

            <!-- Footer 3-Column Content -->
            <div class="footer-grid-layout">
                <!-- Left Column: Restaurant Info -->
                <div>
                    <h4 class="footer-column-heading">Restaurant</h4>
                    <p class="footer-contact-info">
                        Purok 2, Magatas, Sibulan, Negros Oriental<br>
                        <a href="mailto:asentistasjohnkyle@gmail.com" class="footer-contact-link">asentistasjohnkyle@gmail.com</a><br>
                        Everyday 9 AM – 10 PM<br>
                        <a href="tel:09940058425" class="footer-contact-link">0994 005 8425</a>
                    </p>
                    <div class="footer-social-row">
                        <button type="button" class="social-box-icon" data-platform="Facebook" title="Visit Facebook" aria-label="Facebook">f</button>
                        <button type="button" class="social-box-icon" data-platform="Twitter / X" title="Visit Twitter/X" aria-label="Twitter">𝕏</button>
                        <button type="button" class="social-box-icon" data-platform="LinkedIn" title="Visit LinkedIn" aria-label="LinkedIn">in</button>
                        <button type="button" class="social-box-icon" data-platform="Instagram" title="Visit Instagram" aria-label="Instagram">📷</button>
                    </div>
                </div>

                <!-- Center Column: Logo & Brand Name (Scroll to Top) -->
                <div class="footer-brand-center" title="Back to top" role="button" tabindex="0">
                    <div class="brand-svg-logo">
                        <svg width="44" height="52" viewBox="0 0 44 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <ellipse cx="22" cy="18" rx="12" ry="10" fill="#FFAE34" />
                            <ellipse cx="22" cy="20" rx="8" ry="5" fill="#FFAE34" opacity="0.7" />
                            <rect x="12" y="20" width="20" height="5" rx="1" fill="#C8A882" />
                            <text x="22" y="50" text-anchor="middle" font-family="'Times New Roman', serif" font-size="36" font-weight="bold" fill="#2B1B15">A</text>
                        </svg>
                    </div>
                    <div>
                        <div class="brand-title">ASENTISTA'S</div>
                        <div class="brand-subtitle" style="font-size: 1.1rem; font-weight: 700;">BAKERY</div>
                    </div>
                </div>

                <!-- Right Column: Instagram Photo Grid (Click to open Lightbox) -->
                <div class="footer-insta-right">
                    <h4 class="footer-column-heading">Instagram</h4>
                    <div class="insta-photo-grid">
                        <?php foreach ($instagramPhotos as $idx => $photo): ?>
                            <div class="insta-thumb-wrap" data-index="<?php echo $idx; ?>" title="<?php echo htmlspecialchars($photo['title']); ?>" role="button" tabindex="0">
                                <img src="<?php echo $photo['thumb']; ?>" alt="<?php echo htmlspecialchars($photo['title']); ?>" class="insta-thumb-img">
                                <div class="insta-overlay-icon">🔍</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Bottom Copyright Bar -->
            <div class="footer-bottom-bar">
                <p class="copyright-text">
                    © 2026 Asentista's Bakery. Connected to XAMPP MySQL Database.
                </p>
            </div>
        </div>
    </footer>

    <!-- ==========================================
         INTERACTIVE MODALS & OVERLAYS
         ========================================== -->

    <!-- 1. Search Modal -->
    <div class="modal-backdrop" id="searchModal" role="dialog" aria-modal="true" aria-label="Search Bakery Menu">
        <div class="modal-window search-modal-window">
            <div class="modal-header">
                <h3 class="modal-title">Search Bakery Catalog</h3>
                <button type="button" class="modal-close-btn" aria-label="Close search">&times;</button>
            </div>
            <div class="modal-body">
                <div class="search-input-wrap">
                    <svg class="search-icon-inside" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <input type="text" class="search-input-field" id="searchInputField" placeholder="Search by name (e.g. Sourdough, Baguette, Brew)..." autocomplete="off">
                </div>
                <div class="search-results-list" id="searchResultsList">
                    <!-- Dynamic Search Results -->
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Booking & Custom Order Modal (Connected to order_process.php) -->
    <div class="modal-backdrop" id="bookingModal" role="dialog" aria-modal="true" aria-label="Book Bakery Order">
        <div class="modal-window">
            <div class="modal-header">
                <h3 class="modal-title">Bakery Order & Reservation</h3>
                <button type="button" class="modal-close-btn" aria-label="Close booking">&times;</button>
            </div>
            <div class="modal-body">
                <form id="bakeryBookingForm" action="order_process.php" method="POST">
                    <?php if ($currentUser): ?>
                        <input type="hidden" name="user_id" value="<?php echo $currentUser['id']; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="bookingName">Your Name *</label>
                            <input type="text" id="bookingName" name="customer_name" class="form-input" placeholder="e.g. Maria Santos" required value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="bookingPhone">Phone Number *</label>
                            <input type="tel" id="bookingPhone" name="customer_phone" class="form-input" placeholder="e.g. 0994 005 8425" required value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="bookingItemSelect">Select Bread or Beverage</label>
                        <select id="bookingItemSelect" name="item_name" class="form-select">
                            <!-- Populated dynamically via JS / DB -->
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="bookingDate">Pickup / Reservation Date *</label>
                            <input type="date" id="bookingDate" name="reservation_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="bookingOrderType">Order Preference</label>
                            <select id="bookingOrderType" name="order_type" class="form-select">
                                <option value="In-Store Pickup">In-Store Pickup</option>
                                <option value="Dine-in Table Booking">Dine-in Table Booking</option>
                                <option value="Direct Delivery">Direct Delivery</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="bookingNotes">Special Instructions / Custom Quantity</label>
                        <textarea id="bookingNotes" name="special_notes" class="form-textarea" rows="2" placeholder="e.g. Please slice into sandwich cuts, deliver at 10 AM."></textarea>
                    </div>

                    <button type="submit" class="btn-submit-modal shimmer-btn">
                        Confirm Reservation / Order (Save to DB)
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- 3. Bread Product Detail Modal -->
    <div class="modal-backdrop" id="productModal" role="dialog" aria-modal="true" aria-label="Product Details">
        <div class="modal-window">
            <div class="modal-header">
                <h3 class="modal-title" id="productDetailTitle">Artisan Bread Detail</h3>
                <button type="button" class="modal-close-btn" aria-label="Close details">&times;</button>
            </div>
            <div class="modal-body">
                <div class="product-modal-content">
                    <img src="" alt="Product Image" id="productDetailImg" class="product-modal-img">
                    <div>
                        <div class="product-modal-price" id="productDetailPrice">₱35.00</div>
                        <p class="product-modal-desc" id="productDetailDesc">Freshly handcrafted daily with organic flour and pure sourdough culture.</p>
                        
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <button type="button" class="btn-submit-modal shimmer-btn" id="productModalAddToCartBtn">
                                🛒 Add to Cart Now
                            </button>
                            <button type="button" class="btn-submit-modal" style="background-color: var(--color-brown-mid);" id="productDetailOrderBtn">
                                Direct Book / Custom Reservation →
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Instagram Lightbox Modal -->
    <div class="lightbox-backdrop" id="lightboxModal" role="dialog" aria-modal="true" aria-label="Instagram Photo Preview">
        <div class="lightbox-img-wrap">
            <button type="button" class="lightbox-close-btn" aria-label="Close preview">&times;</button>
            <button type="button" class="lightbox-nav-btn lightbox-prev-btn" id="lightboxPrevBtn" aria-label="Previous photo">&#10094;</button>
            <img src="" alt="Instagram preview" id="lightboxImg" class="lightbox-img">
            <button type="button" class="lightbox-nav-btn lightbox-next-btn" id="lightboxNextBtn" aria-label="Next photo">&#10095;</button>
        </div>
    </div>

    <!-- 5. Toast Notifications Container -->
    <div class="toast-container" id="toastContainer" aria-live="polite"></div>

    <!-- JavaScript Controller -->
    <script>
        window.isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
    </script>
    <script src="script.js" defer></script>
</body>
</html>