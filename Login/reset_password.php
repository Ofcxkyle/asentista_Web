<?php
// Reset Password - Set New Credentials Portal

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/function.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$token = trim($token);

$errorMsg = '';
$tokenValid = false;
$associatedUser = null;

// Validate token
if (!empty($token)) {
    $verify = verifyPasswordResetToken($pdo, $token);
    if ($verify['valid']) {
        $tokenValid = true;
        $associatedUser = $verify['user'];
    } else {
        $errorMsg = $verify['message'];
    }
} else {
    $errorMsg = 'No password reset token was provided. Please request a password reset link first.';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errorMsg = 'Security validation failed (CSRF token expired or invalid). Please refresh and try again.';
    } else {
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $result = resetUserPassword($pdo, $token, $newPassword, $confirmPassword);
        if ($result['success']) {
            header('Location: auth.php?msg=password_reset_success');
            exit;
        } else {
            $errorMsg = $result['message'];
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
    <title>Set New Password - Asentista's Bakery</title>
    <link rel="icon" type="image/png" href="assets/ASENTISTA FINAL.png">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --auth-bg-ambient: #FAF7F2;
            --auth-brand-gold: #FFAE34;
            --auth-brand-gold-deep: #D97706;
            --auth-brown-deep: #2B1B15;
            --auth-text-headline: #2B1B15;
            --auth-text-muted: #6B5B52;
            --auth-border-subtle: #E8DFD5;
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

        .reset-card {
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

        .reset-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #10B981, #059669, #047857);
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
            background: linear-gradient(135deg, #10B981 0%, #047857 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        }

        .brand-crest svg {
            width: 28px;
            height: 28px;
            color: #FFFFFF;
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
            padding: 12px 42px 12px 42px;
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
            border-color: #10B981;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .toggle-pwd-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #8C7A70;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-pwd-btn:hover {
            color: var(--auth-brown-deep);
        }

        .toggle-pwd-btn svg {
            width: 18px;
            height: 18px;
        }

        .pwd-rules-box {
            background: #F8F5F0;
            border: 1px solid var(--auth-border-subtle);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.78rem;
            color: var(--auth-text-muted);
        }

        .pwd-rules-box ul {
            margin: 0.35rem 0 0 1.2rem;
            padding: 0;
        }

        .auth-submit-btn {
            width: 100%;
            padding: 13px 20px;
            font-size: 0.95rem;
            font-weight: 700;
            color: #FFFFFF;
            background: linear-gradient(180deg, #10B981 0%, #059669 100%);
            border: 1px solid #047857;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
            transition: transform 0.18s ease, background 0.18s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .auth-submit-btn:hover {
            transform: translateY(-2px);
            background: linear-gradient(180deg, #059669 0%, #047857 100%);
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
            color: #10B981;
        }
    </style>
</head>
<body>

<div class="reset-card">
    <div class="brand-header">
        <div class="brand-crest">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>
        <h1 class="headline">Set New Password</h1>
        <p class="tagline">
            <?php if ($associatedUser): ?>
                Updating credentials for <strong><?php echo htmlspecialchars($associatedUser['name']); ?></strong> (<?php echo htmlspecialchars($associatedUser['email']); ?>)
            <?php else: ?>
                Create a strong, secure new password for your bakery account.
            <?php endif; ?>
        </p>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="auth-alert auth-alert-error">
            <span>⚠️</span>
            <div><?php echo $errorMsg; ?></div>
        </div>
    <?php endif; ?>

    <?php if ($tokenValid): ?>
        <form action="Login/reset_password.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="auth-field-group">
                <label class="auth-label" for="newPassword">New Password</label>
                <div class="auth-input-container">
                    <span class="auth-input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                    <input type="password" id="newPassword" name="new_password" class="auth-input" placeholder="At least 8 characters" required minlength="8" autofocus>
                    <button type="button" class="toggle-pwd-btn" onclick="togglePasswordVisibility('newPassword', this)" title="Show password" aria-label="Show password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="auth-field-group">
                <label class="auth-label" for="confirmPassword">Confirm New Password</label>
                <div class="auth-input-container">
                    <span class="auth-input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                    <input type="password" id="confirmPassword" name="confirm_password" class="auth-input" placeholder="Repeat your new password" required minlength="8">
                    <button type="button" class="toggle-pwd-btn" onclick="togglePasswordVisibility('confirmPassword', this)" title="Show password" aria-label="Show password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="pwd-rules-box">
                <strong>Password Requirements:</strong>
                <ul>
                    <li>At least 8 characters in length</li>
                    <li>Must include at least 1 number or special character (e.g. !@#$%&*)</li>
                    <li>Both password fields must match exactly</li>
                </ul>
            </div>

            <button type="submit" class="auth-submit-btn">
                <span>Save New Password & Continue</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </button>
        </form>
    <?php else: ?>
        <div style="text-align: center; margin-top: 1.5rem;">
            <a href="Login/forgot_password.php" class="auth-submit-btn" style="text-decoration: none; background: #D97706; border-color: #B45309;">
                Request a Fresh Reset Link
            </a>
        </div>
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

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    btn.style.color = isPassword ? '#10B981' : '#8C7A70';
}
</script>

</body>
</html>
