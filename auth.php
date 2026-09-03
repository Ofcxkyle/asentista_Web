<?php
/**
 * Asentista Bakery - Immersive Authentication Portal
 * Pure PHP session-based authentication connected to MySQL database.
 */

require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/database/function.php';

$errorMsg = '';
$successMsg = '';
$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'register' : 'login';

// If already logged in, redirect based on role
if (isLoggedIn() && !isset($_GET['action'])) {
    if (isAdmin()) {
        header('Location: admin.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

// Handle Guest Mode Skip
if (isset($_GET['guest'])) {
    $_SESSION['guest_mode'] = true;
    header('Location: index.php');
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errorMsg = 'Security validation failed (CSRF token expired or invalid). Please refresh the page.';
    } else {
        $action = $_POST['auth_action'] ?? 'login';

        if ($action === 'register') {
            $activeTab = 'register';
            $name     = sanitize_input($_POST['name'] ?? '');
            $email    = sanitize_input($_POST['email'] ?? '');
            $phone    = sanitize_input($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['confirm_password'] ?? '';

            if ($password !== $confirm) {
                $errorMsg = 'Passwords do not match. Please re-enter.';
            } else {
                $regResult = registerUser($pdo, $name, $email, $phone, $password);
                if ($regResult['success']) {
                    $redirectUrl = !empty($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
                    header("Location: {$redirectUrl}");
                    exit;
                } else {
                    $errorMsg = $regResult['message'];
                }
            }
        } elseif ($action === 'login') {
            $activeTab = 'login';
            $email    = sanitize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            $loginResult = loginUser($pdo, $email, $password);
            if ($loginResult['success']) {
                $userRole = $loginResult['user']['role'] ?? 'customer';
                
                // Custom redirect if provided, otherwise route admins to admin.php and customers to index.php
                if (!empty($_GET['redirect'])) {
                    $redirectUrl = $_GET['redirect'];
                } else {
                    $redirectUrl = ($userRole === 'admin') ? 'admin.php' : 'index.php';
                }
                
                header("Location: {$redirectUrl}");
                exit;
            } else {
                $errorMsg = $loginResult['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In & Register - Asentista's Bakery</title>
    <!-- Website Favicon / Main Logo -->
    <link rel="icon" type="image/png" href="assets/ASENTISTA FINAL.png">
    <link rel="apple-touch-icon" href="assets/ASENTISTA FINAL.png">
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-viewport {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            background-color: var(--color-cream);
        }
        /* Left Brand Showcase Panel */
        .auth-hero-pane {
            position: relative;
            background: linear-gradient(135deg, rgba(30, 18, 14, 0.94) 0%, rgba(43, 27, 21, 0.88) 100%),
                        url('assets/AdobeStock_2042265063.jpeg') center/cover no-repeat;
            color: var(--color-white);
            padding: 4rem 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }
        .auth-hero-pane::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 30%, rgba(255, 174, 52, 0.15), transparent 45%);
            pointer-events: none;
        }
        .hero-brand-block {
            position: relative;
            z-index: 2;
        }
        .hero-badges-list {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
            margin: 2.5rem 0;
        }
        .hero-badge-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            padding: 0.9rem 1.25rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            transition: transform 0.25s ease, background 0.25s ease;
        }
        .hero-badge-item:hover {
            transform: translateX(6px);
            background: rgba(255, 255, 255, 0.14);
        }
        .hero-badge-icon {
            font-size: 1.8rem;
            background: rgba(255, 174, 52, 0.2);
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            flex-shrink: 0;
        }
        .hero-badge-title {
            font-family: var(--font-serif);
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--color-yellow-hover);
        }
        .hero-badge-desc {
            font-size: 0.78rem;
            color: var(--color-cream-light);
            opacity: 0.85;
            margin-top: 2px;
        }
        .hero-quote-box {
            position: relative;
            z-index: 2;
            border-left: 3px solid var(--color-yellow);
            padding-left: 1.2rem;
            font-style: italic;
            font-family: var(--font-serif);
            font-size: 1rem;
            color: var(--color-cream);
            line-height: 1.6;
        }

        /* Right Form Panel */
        .auth-form-pane {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
            background-color: var(--color-cream-light);
        }
        .auth-form-card {
            background-color: var(--color-white);
            width: 100%;
            max-width: 460px;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 20px 45px rgba(43, 27, 21, 0.15);
            border: 1px solid rgba(43, 27, 21, 0.1);
        }
        .form-top-title {
            font-family: var(--font-serif);
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--color-brown-deep);
            margin-bottom: 0.3rem;
            text-align: center;
        }
        .form-top-subtitle {
            font-size: 0.82rem;
            color: var(--color-text-muted);
            text-align: center;
            margin-bottom: 1.8rem;
        }
        .auth-tab-switch {
            display: flex;
            background: var(--color-cream-light);
            padding: 4px;
            border-radius: 6px;
            margin-bottom: 1.8rem;
            border: 1px solid rgba(43, 27, 21, 0.1);
        }
        .tab-switch-btn {
            flex: 1;
            padding: 0.7rem;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: var(--color-text-muted);
            border-radius: 4px;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .tab-switch-btn.active {
            background: var(--color-brown-deep);
            color: var(--color-white);
            box-shadow: 0 2px 8px rgba(43, 27, 21, 0.2);
        }
        .guest-link-bar {
            text-align: center;
            margin-top: 1.8rem;
            padding-top: 1.2rem;
            border-top: 1px solid rgba(43, 27, 21, 0.1);
            font-size: 0.85rem;
        }
        .guest-link-bar a {
            color: var(--color-brown-deep);
            font-weight: 700;
            text-decoration: underline;
        }

        @media (max-width: 992px) {
            .auth-viewport {
                grid-template-columns: 1fr;
            }
            .auth-hero-pane {
                padding: 2.5rem 1.5rem;
            }
            .auth-form-pane {
                padding: 2rem 1rem;
            }
        }
    </style>
</head>
<body>

    <div class="auth-viewport">
        <!-- Left: Bakery & Drinks Atmosphere Panel -->
        <div class="auth-hero-pane">
            <div class="hero-brand-block">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 1rem;">
                    <div class="brand-svg-logo">
                        <img src="assets/ASENTISTA FINAL.png" alt="Asentista's Bakery Logo" class="brand-logo-img" style="height: 54px;">
                    </div>
                    <div>
                        <div class="brand-title" style="color: var(--color-white); font-size: 1.3rem;">ASENTISTA'S</div>
                        <div class="brand-subtitle" style="color: var(--color-yellow); font-size: 0.95rem; font-weight: 700;">BAKERY & COFFEE</div>
                    </div>
                </div>
                <h1 style="font-family: var(--font-serif); font-size: 2.5rem; line-height: 1.15; color: var(--color-cream-light); margin-top: 1rem;">
                    Artisan Sourdough & Freshly Brewed Coffee
                </h1>
            </div>

            <!-- Features Highlights -->
            <div class="hero-badges-list">
                <div class="hero-badge-item">
                    <div class="hero-badge-icon">🥖</div>
                    <div>
                        <div class="hero-badge-title">Brick Oven Special Breads</div>
                        <div class="hero-badge-desc">Slow-fermented sourdoughs, crispy baguettes & buttery croissants.</div>
                    </div>
                </div>
                <div class="hero-badge-item">
                    <div class="hero-badge-icon">☕</div>
                    <div>
                        <div class="hero-badge-title">Handcrafted Beverages</div>
                        <div class="hero-badge-desc">18-hour cold brew, cortados, Americanos & signature blends.</div>
                    </div>
                </div>
                <div class="hero-badge-item">
                    <div class="hero-badge-icon">🌾</div>
                    <div>
                        <div class="hero-badge-title">100% Natural Organic Craft</div>
                        <div class="hero-badge-desc">Zero preservatives, natural levain, and daily mountain spring purity.</div>
                    </div>
                </div>
            </div>

            <!-- Quote Block -->
            <div class="hero-quote-box">
                "The smell of good bread baking is indescribable in its evocation of innocence and delight."
                <div style="font-size: 0.8rem; font-weight: 600; color: var(--color-yellow); margin-top: 4px;">— Kyle Asentista, Founder</div>
            </div>
        </div>

        <!-- Right: Login & Registration Portal -->
        <div class="auth-form-pane">
            <div class="auth-form-card">
                <h2 class="form-top-title">Welcome to Asentista's</h2>
                <p class="form-top-subtitle">Sign in to access fresh orders, bookings & bakery rewards</p>

                <!-- Tabs -->
                <div class="auth-tab-switch">
                    <button type="button" class="tab-switch-btn <?php echo $activeTab === 'login' ? 'active' : ''; ?>" id="tabLogin" onclick="switchTab('login')">
                        SIGN IN
                    </button>
                    <button type="button" class="tab-switch-btn <?php echo $activeTab === 'register' ? 'active' : ''; ?>" id="tabRegister" onclick="switchTab('register')">
                        CREATE ACCOUNT
                    </button>
                </div>

                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'login_to_order'): ?>
                    <div style="background-color: #FEF3C7; color: #92400E; padding: 0.9rem 1.1rem; border-radius: 4px; margin-bottom: 1.2rem; font-size: 0.85rem; border-left: 4px solid #F59E0B; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.2rem;">🔒</span>
                        <div><strong>Account Required to Order:</strong> Please sign in or create an account to place and confirm your bakery order!</div>
                    </div>
                <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'admin_required'): ?>
                    <div style="background-color: #FEE2E2; color: #991B1B; padding: 0.9rem 1.1rem; border-radius: 4px; margin-bottom: 1.2rem; font-size: 0.85rem; border-left: 4px solid #DC2626; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.2rem;">👑</span>
                        <div><strong>Admin Access Required:</strong> You must sign in with an authorized Administrator account to enter the Admin Console.</div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                    <div style="background-color: #FEE2E2; color: #991B1B; padding: 0.8rem 1rem; border-radius: 4px; margin-bottom: 1.2rem; font-size: 0.85rem; border-left: 4px solid #DC2626;">
                        <?php echo $errorMsg; ?>
                    </div>
                <?php endif; ?>

                <!-- 1. LOGIN FORM -->
                <form action="auth.php<?php echo !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST" id="formLogin" style="display: <?php echo $activeTab === 'login' ? 'block' : 'none'; ?>;">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <input type="hidden" name="auth_action" value="login">

                    <div class="form-group">
                        <label class="form-label" for="loginEmail">Email Address *</label>
                        <input type="email" id="loginEmail" name="email" class="form-input" placeholder="e.g. maria@gmail.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="loginPassword">Password *</label>
                        <input type="password" id="loginPassword" name="password" class="form-input" placeholder="••••••••" autocomplete="current-password" required>
                    </div>

                    <button type="submit" class="btn-submit-modal" style="margin-top: 1.2rem; font-size: 0.9rem; padding: 0.95rem;">
                        Sign In & Enter Bakery →
                    </button>
                </form>

                <!-- 2. REGISTER FORM -->
                <form action="auth.php<?php echo !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST" id="formRegister" style="display: <?php echo $activeTab === 'register' ? 'block' : 'none'; ?>;">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <input type="hidden" name="auth_action" value="register">

                    <div class="form-group">
                        <label class="form-label" for="regName">Full Name *</label>
                        <input type="text" id="regName" name="name" class="form-input" placeholder="e.g. Maria Santos" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="regEmail">Email *</label>
                            <input type="email" id="regEmail" name="email" class="form-input" placeholder="maria@gmail.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regPhone">Phone</label>
                            <input type="tel" id="regPhone" name="phone" class="form-input" placeholder="0912 345 6789" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="regPassword">Password *</label>
                            <input type="password" id="regPassword" name="password" class="form-input" placeholder="Create your password" autocomplete="new-password" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regConfirm">Confirm *</label>
                            <input type="password" id="regConfirm" name="confirm_password" class="form-input" placeholder="Repeat password" autocomplete="new-password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit-modal" style="margin-top: 1.2rem; font-size: 0.9rem; padding: 0.95rem;">
                        Create Account & Proceed →
                    </button>
                </form>

                <!-- Guest Mode link -->
                <div class="guest-link-bar">
                    Just browsing? <a href="auth.php?guest=1">Continue as Guest (Browse Menu) →</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const formLogin = document.getElementById('formLogin');
            const formRegister = document.getElementById('formRegister');
            const tabLogin = document.getElementById('tabLogin');
            const tabRegister = document.getElementById('tabRegister');

            if (tab === 'register') {
                formLogin.style.display = 'none';
                formRegister.style.display = 'block';
                tabLogin.classList.remove('active');
                tabRegister.classList.add('active');
            } else {
                formLogin.style.display = 'block';
                formRegister.style.display = 'none';
                tabLogin.classList.add('active');
                tabRegister.classList.remove('active');
            }
        }
    </script>
</body>
</html>
