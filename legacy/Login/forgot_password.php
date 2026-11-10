<?php
// Forgot Password - Account Recovery Request Portal

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/function.php';

$errorMsg = '';
$successData = null;

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errorMsg = 'Security validation failed (CSRF token expired or invalid). Please refresh and try again.';
    } else {
        $email = sanitize_input($_POST['email'] ?? '');
        $res = createPasswordReset($pdo, $email);
        
        if ($res['success']) {
            $successData = $res;
        } else {
            $errorMsg = $res['message'];
        }
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
    if (!empty($parts) && in_array(end($parts), ['Login', 'Admin', 'User', 'Cart', 'database'])) {
        array_pop($parts);
    }
    $appBasePath = !empty($parts) ? '/' . implode('/', $parts) . '/' : '/';
    ?>
    <base href="<?php echo htmlspecialchars($appBasePath); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Asentista's Bakery & Coffee</title>
    <link rel="icon" type="image/png" href="assets/ASENTISTA FINAL.png">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --auth-bg-ambient: #FAF7F2;
            --auth-brand-gold: #FFAE34;
            --auth-brand-gold-deep: #D97706;
            --auth-brown-deep: #2B1B15;
            --auth-brown-card: #20130E;
            --auth-text-headline: #2B1B15;
            --auth-text-muted: #6B5B52;
            --auth-border-subtle: #E8DFD5;
            --auth-ease: cubic-bezier(0.16, 1, 0.3, 1);
        }

        body {
            background-color: var(--auth-bg-ambient);
            margin: 0;
            padding: 0;
            font-family: var(--font-sans, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
            color: var(--auth-text-headline);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, #FAF7F2 0%, #F0E8DD 100%);
            padding: 2rem 1rem;
            box-sizing: border-box;
        }

        .recovery-card {
            background: #FFFFFF;
            max-width: 480px;
            width: 100%;
            border-radius: 20px;
            padding: 2.5rem 2.2rem;
            box-shadow: 0 16px 40px rgba(43, 27, 21, 0.08), 0 2px 6px rgba(43, 27, 21, 0.04);
            border: 1px solid var(--auth-border-subtle);
            position: relative;
            overflow: hidden;
        }

        .recovery-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #FFAE34, #D97706, #B45309);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .brand-crest {
            width: 54px;
            height: 54px;
            margin: 0 auto 0.75rem auto;
            border-radius: 50%;
            background: linear-gradient(135deg, #FFAE34 0%, #D97706 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(255, 174, 52, 0.35);
        }

        .brand-crest img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .headline {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--auth-brown-deep);
            margin: 0 0 0.4rem 0;
            letter-spacing: -0.02em;
        }

        .tagline {
            font-size: 0.88rem;
            color: var(--auth-text-muted);
            line-height: 1.5;
            margin: 0;
        }

        .auth-alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.45;
        }

        .auth-alert-error {
            background-color: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
            border-left: 4px solid #DC2626;
        }

        .auth-alert-success {
            background-color: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
            border-left: 4px solid #10B981;
        }

        .auth-field-group {
            margin-bottom: 1.25rem;
        }

        .auth-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--auth-text-headline);
            margin-bottom: 0.45rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .auth-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .auth-input-icon {
            position: absolute;
            left: 14px;
            color: #8C7A70;
            display: flex;
            align-items: center;
            pointer-events: none;
        }

        .auth-input-icon svg {
            width: 18px;
            height: 18px;
            stroke-width: 2;
        }

        .auth-input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            font-size: 0.95rem;
            border: 1.5px solid var(--auth-border-subtle);
            border-radius: 10px;
            outline: none;
            color: var(--auth-brown-deep);
            background: #FCFAF7;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            box-sizing: border-box;
        }

        .auth-input:focus {
            border-color: #D97706;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
        }

        .auth-submit-btn {
            width: 100%;
            padding: 13px 20px;
            font-size: 0.95rem;
            font-weight: 700;
            color: #FFFFFF;
            background: linear-gradient(180deg, #3A231A 0%, #251610 100%);
            border: 1px solid #1E120E;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(43, 27, 21, 0.2);
            transition: transform 0.18s ease, background 0.18s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .auth-submit-btn:hover {
            transform: translateY(-2px);
            background: linear-gradient(180deg, #442A20 0%, #2B1912 100%);
        }

        .auth-submit-btn:active {
            transform: scale(0.98);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--auth-text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 1.5rem;
            transition: color 0.15s ease;
        }

        .back-link:hover {
            color: #B45309;
        }

        /* Testing Helper Box */
        .eval-box {
            background: #FEF3C7;
            border: 1.5px dashed #F59E0B;
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 1.5rem;
            text-align: left;
        }

        .eval-title {
            font-size: 0.82rem;
            font-weight: 800;
            color: #92400E;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .eval-text {
            font-size: 0.82rem;
            color: #78350F;
            line-height: 1.45;
            margin-bottom: 0.85rem;
        }

        .eval-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            background: #D97706;
            color: #FFFFFF;
            padding: 11px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.88rem;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
            transition: background 0.15s ease, transform 0.15s ease;
            box-sizing: border-box;
        }

        .eval-btn:hover {
            background: #B45309;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="recovery-card">
    <div class="brand-header">
        <div class="brand-crest">
            <img src="assets/ASENTISTA FINAL.png" alt="Asentista Bakery">
        </div>
        <h1 class="headline">Reset Your Password</h1>
        <p class="tagline">Enter your registered email address to receive a secure password recovery link.</p>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="auth-alert auth-alert-error">
            <span>⚠️</span>
            <div><?php echo $errorMsg; ?></div>
        </div>
    <?php endif; ?>

    <?php if ($successData): ?>
        <div class="auth-alert auth-alert-success">
            <span>✅</span>
            <div>
                <strong>Recovery Link Generated!</strong><br>
                A secure 1-hour reset token has been initialized for <strong><?php echo htmlspecialchars($successData['user']['email']); ?></strong>.
            </div>
        </div>

        <!-- Academic Evaluation / Local Testing Action Card -->
        <div class="eval-box">
            <div class="eval-title">
                <span>🛡️ Local Testing & Defense Shortcut</span>
            </div>
            <p class="eval-text">
                In a live production environment, a secure message is dispatched via SMTP. For local offline evaluation and instructor defense, you can test the link immediately:
            </p>
            <a href="Login/reset_password.php?token=<?php echo urlencode($successData['raw_token']); ?>" class="eval-btn">
                <span>Proceed to Reset Password Now &rarr;</span>
            </a>
        </div>
        
        <div style="text-align: center; margin-top: 1.25rem;">
            <a href="Login/forgot_password.php" style="font-size: 0.82rem; color: #6B5B52; text-decoration: underline;">Request another link</a>
        </div>
    <?php else: ?>
        <form action="Login/forgot_password.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

            <div class="auth-field-group">
                <label class="auth-label" for="recoveryEmail">Registered Email Address</label>
                <div class="auth-input-container">
                    <span class="auth-input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </span>
                    <input type="email" id="recoveryEmail" name="email" class="auth-input" placeholder="e.g. customer@asentista.com" required autofocus value="<?php echo htmlspecialchars($_POST['email'] ?? $_GET['email'] ?? ''); ?>">
                </div>
            </div>

            <button type="submit" class="auth-submit-btn">
                <span>Send Password Reset Link</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </form>
    <?php endif; ?>

    <div style="text-align: center;">
        <a href="Login/auth.php" class="back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Return to Sign In</span>
        </a>
    </div>
</div>

</body>
</html>
