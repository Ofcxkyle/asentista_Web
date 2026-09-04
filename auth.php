<?php
/**
 * Asentista Bakery - World-Class Authentication Portal
 * Crafted with Emil Kowalski Design Engineering & Impeccable UI Principles.
 * Full-stack PHP/MySQL session management with CSRF protection, rate limiting, and interactive client UX.
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
    <!-- Favicon & Touch Icon -->
    <link rel="icon" type="image/png" href="assets/ASENTISTA FINAL.png">
    <link rel="apple-touch-icon" href="assets/ASENTISTA FINAL.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,400;1,600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* ==========================================================================
           WORLD-CLASS AUTH PORTAL DESIGN (Emil Kowalski & Impeccable Standards)
           ========================================================================== */
        
        :root {
            --auth-bg-ambient: #FAF7F2;
            --auth-card-bg: #FFFFFF;
            --auth-border-subtle: rgba(78, 56, 46, 0.12);
            --auth-border-focus: #FFAE34;
            --auth-focus-ring: rgba(255, 174, 52, 0.22);
            --auth-text-headline: #2B1B15;
            --auth-text-body: #4E382E;
            --auth-text-muted: #7A6960;
            --auth-brand-gold: #FFAE34;
            --auth-brand-gold-deep: #B45309;
            --auth-radius-card: 18px;
            --auth-radius-input: 10px;
            --auth-ease: cubic-bezier(0.16, 1, 0.3, 1);
        }

        body {
            background-color: var(--auth-bg-ambient);
            margin: 0;
            padding: 0;
            font-family: var(--font-sans);
            color: var(--auth-text-headline);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .auth-viewport {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            background-color: var(--auth-bg-ambient);
            position: relative;
        }

        /* --------------------------------------------------------------------------
           LEFT PANE: Atmospheric Artisan Showcase
           -------------------------------------------------------------------------- */
        .auth-hero-pane {
            position: relative;
            background: linear-gradient(145deg, rgba(25, 15, 12, 0.95) 0%, rgba(43, 27, 21, 0.88) 100%),
                        url('assets/AdobeStock_2042265063.png') center/cover no-repeat;
            color: #FFFFFF;
            padding: 3.5rem 3.5rem 2.8rem 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* Ambient Glow Backdrop */
        .auth-hero-pane::before {
            content: '';
            position: absolute;
            top: -20%;
            left: -20%;
            width: 140%;
            height: 140%;
            background: radial-gradient(circle at 25% 25%, rgba(255, 174, 52, 0.16), transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(180, 83, 9, 0.12), transparent 50%);
            pointer-events: none;
        }

        /* Subtle Bread Crumb Texture Overlay */
        .auth-hero-pane::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
            opacity: 0.6;
        }

        .hero-brand-block {
            position: relative;
            z-index: 2;
        }

        .hero-brand-header {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 8px 18px 8px 10px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            margin-bottom: 2rem;
            transition: transform 0.25s var(--auth-ease), background-color 0.25s ease;
        }

        .hero-brand-header:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.1);
        }

        .brand-logo-disc {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FFAE34 0%, #D97706 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(255, 174, 52, 0.4);
            overflow: hidden;
            flex-shrink: 0;
        }

        .brand-logo-disc img {
            width: 26px;
            height: 26px;
            object-fit: contain;
        }

        .brand-text-col {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }

        .brand-text-name {
            font-family: var(--font-sans);
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.14em;
            color: #FFFFFF;
        }

        .brand-text-sub {
            font-family: var(--font-sans);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            color: var(--auth-brand-gold);
            text-transform: uppercase;
        }

        .hero-title-main {
            font-family: var(--font-serif);
            font-size: 2.85rem;
            line-height: 1.12;
            font-weight: 600;
            color: #FAF7F2;
            margin: 0 0 1rem 0;
            letter-spacing: -0.01em;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
        }

        .hero-title-main span {
            color: var(--auth-brand-gold);
            font-style: italic;
        }

        .hero-subtitle-p {
            font-size: 1rem;
            line-height: 1.65;
            color: #EDE0D4;
            max-width: 480px;
            opacity: 0.9;
            margin-bottom: 2rem;
            font-weight: 400;
        }

        /* Live Trust & Proof Bar */
        .hero-stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 2rem;
            position: relative;
            z-index: 2;
        }

        .hero-stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 12px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            transition: transform 0.2s var(--auth-ease), background-color 0.2s ease;
        }

        .hero-stat-card:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.08);
        }

        .stat-value {
            font-family: var(--font-serif);
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--auth-brand-gold);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-label {
            font-size: 0.72rem;
            font-weight: 500;
            color: #DBC8B6;
            letter-spacing: 0.02em;
        }

        /* Glassmorphism Feature Badges */
        .hero-features-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 2rem;
            position: relative;
            z-index: 2;
        }

        .hero-feature-card {
            display: flex;
            align-items: center;
            gap: 14px;
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.22s var(--auth-ease), background-color 0.2s ease, border-color 0.2s ease;
        }

        .hero-feature-card:hover {
            transform: translateX(6px);
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 174, 52, 0.35);
        }

        .hero-feat-icon-box {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(255, 174, 52, 0.2) 0%, rgba(180, 83, 9, 0.25) 100%);
            border: 1px solid rgba(255, 174, 52, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
        }

        .hero-feat-text {
            display: flex;
            flex-direction: column;
        }

        .hero-feat-title {
            font-family: var(--font-sans);
            font-size: 0.9rem;
            font-weight: 700;
            color: #FAF7F2;
            letter-spacing: 0.02em;
        }

        .hero-feat-desc {
            font-size: 0.76rem;
            color: #DBC8B6;
            opacity: 0.85;
            line-height: 1.4;
            margin-top: 2px;
        }

        /* Founder Quote */
        .hero-founder-quote {
            position: relative;
            z-index: 2;
            background: rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-left: 3px solid var(--auth-brand-gold);
            padding: 14px 18px;
            border-radius: 0 10px 10px 0;
            margin-top: auto;
        }

        .quote-body {
            font-family: var(--font-serif);
            font-style: italic;
            font-size: 0.92rem;
            line-height: 1.55;
            color: #EDE0D4;
        }

        .quote-author {
            font-family: var(--font-sans);
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--auth-brand-gold);
            letter-spacing: 0.05em;
            margin-top: 6px;
            text-transform: uppercase;
        }

        /* --------------------------------------------------------------------------
           RIGHT PANE: Luxury Authentication Concierge
           -------------------------------------------------------------------------- */
        .auth-form-pane {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3.5rem 2.5rem;
            background: linear-gradient(180deg, #FAF7F2 0%, #F5EFE6 100%);
            position: relative;
        }

        /* Ambient Glow behind the card */
        .auth-form-pane::before {
            content: '';
            position: absolute;
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, rgba(255, 174, 52, 0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .auth-card-container {
            width: 100%;
            max-width: 470px;
            position: relative;
            z-index: 2;
        }

        /* Elevated Card with Multi-layered Optical Shadows */
        .auth-luxury-card {
            background-color: var(--auth-card-bg);
            border-radius: var(--auth-radius-card);
            padding: 2.5rem 2.25rem 2.25rem 2.25rem;
            border: 1px solid rgba(78, 56, 46, 0.12);
            box-shadow: 
                0 1px 3px rgba(43, 27, 21, 0.05),
                0 10px 24px -4px rgba(43, 27, 21, 0.09),
                0 24px 48px -12px rgba(43, 27, 21, 0.08);
            transition: box-shadow 0.3s ease, border-color 0.3s ease;
        }

        .auth-luxury-card:hover {
            box-shadow: 
                0 1px 3px rgba(43, 27, 21, 0.05),
                0 14px 32px -4px rgba(43, 27, 21, 0.12),
                0 32px 56px -12px rgba(43, 27, 21, 0.1);
        }

        /* Card Brand Header */
        .card-header-block {
            text-align: center;
            margin-bottom: 1.6rem;
        }

        .card-brand-crest {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, #FAF7F2 0%, #EDE0D4 100%);
            border: 1px solid rgba(78, 56, 46, 0.12);
            box-shadow: 0 4px 12px rgba(43, 27, 21, 0.06);
            margin: 0 auto 0.9rem auto;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.25s var(--auth-ease);
        }

        .card-brand-crest:hover {
            transform: scale(1.06) rotate(3deg);
        }

        .card-brand-crest img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }

        .card-headline {
            font-family: var(--font-serif);
            font-size: 1.85rem;
            font-weight: 700;
            color: var(--auth-text-headline);
            letter-spacing: -0.01em;
            margin: 0 0 0.35rem 0;
        }

        .card-tagline {
            font-size: 0.85rem;
            color: var(--auth-text-muted);
            margin: 0;
            line-height: 1.45;
        }

        /* --------------------------------------------------------------------------
           SEGMENTED PILL CONTROL (Linear / iOS Style Tab Switcher)
           -------------------------------------------------------------------------- */
        .auth-segmented-wrapper {
            position: relative;
            background: #F3EDE4;
            padding: 4px;
            border-radius: 12px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            margin-bottom: 1.4rem;
            border: 1px solid rgba(78, 56, 46, 0.08);
        }

        .auth-segment-btn {
            position: relative;
            z-index: 2;
            padding: 9px 16px;
            font-family: var(--font-sans);
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            color: var(--auth-text-muted);
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            outline: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: color 0.18s ease, transform 0.15s ease;
            user-select: none;
        }

        .auth-segment-btn:active {
            transform: scale(0.97);
        }

        .auth-segment-btn.active {
            color: var(--auth-text-headline);
            background-color: #FFFFFF;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(43, 27, 21, 0.08), 0 1px 2px rgba(43, 27, 21, 0.04);
        }

        .auth-segment-btn:focus-visible {
            box-shadow: 0 0 0 2px var(--auth-border-focus);
        }


        /* --------------------------------------------------------------------------
           ALERTS & NOTICES
           -------------------------------------------------------------------------- */
        .auth-alert {
            padding: 11px 14px;
            border-radius: 10px;
            margin-bottom: 1.3rem;
            font-size: 0.83rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.45;
            animation: slideDownFade 0.25s var(--auth-ease);
        }

        @keyframes slideDownFade {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-alert-warning {
            background-color: #FEF3C7;
            color: #92400E;
            border: 1px solid #FCD34D;
            border-left: 4px solid #F59E0B;
        }

        .auth-alert-error {
            background-color: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
            border-left: 4px solid #DC2626;
        }

        /* --------------------------------------------------------------------------
           FORM FIELDS & LUXURY INPUT COMPONENTS
           -------------------------------------------------------------------------- */
        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .auth-field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .auth-row-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .auth-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--auth-text-headline);
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .auth-label .required-star {
            color: #DC2626;
            margin-left: 2px;
        }

        .auth-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        /* Leading SVG Icon */
        .auth-input-icon {
            position: absolute;
            left: 14px;
            width: 18px;
            height: 18px;
            color: var(--auth-text-muted);
            pointer-events: none;
            transition: color 0.18s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-input-icon svg {
            width: 100%;
            height: 100%;
            stroke-width: 2;
        }

        /* The Input Itself */
        .auth-luxury-input {
            width: 100%;
            font-family: var(--font-sans);
            font-size: 0.88rem;
            color: var(--auth-text-headline);
            background-color: #FAF8F5;
            border: 1px solid rgba(78, 56, 46, 0.18);
            border-radius: var(--auth-radius-input);
            padding: 11px 40px 11px 40px;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        }

        .auth-luxury-input.no-prefix {
            padding-left: 14px;
        }

        .auth-luxury-input.no-suffix {
            padding-right: 14px;
        }

        .auth-luxury-input::placeholder {
            color: #A89B93;
            font-weight: 400;
        }

        .auth-luxury-input:hover {
            border-color: rgba(78, 56, 46, 0.32);
            background-color: #FCFAF7;
        }

        .auth-luxury-input:focus {
            background-color: #FFFFFF;
            border-color: var(--auth-border-focus);
            box-shadow: 0 0 0 3px var(--auth-focus-ring), 0 1px 2px rgba(43, 27, 21, 0.04);
        }

        /* Activate icon color when field is focused */
        .auth-input-container:focus-within .auth-input-icon {
            color: var(--auth-brand-gold-deep);
        }

        /* ==========================================================================
           CRITICAL CHROME AUTOFILL FIX (No ugly blue background!)
           ========================================================================== */
        input.auth-luxury-input:-webkit-autofill,
        input.auth-luxury-input:-webkit-autofill:hover,
        input.auth-luxury-input:-webkit-autofill:focus,
        input.auth-luxury-input:-webkit-autofill:active {
            -webkit-text-fill-color: var(--auth-text-headline) !important;
            -webkit-box-shadow: 0 0 0px 1000px #FAF8F5 inset !important;
            box-shadow: 0 0 0px 1000px #FAF8F5 inset !important;
            border-color: rgba(78, 56, 46, 0.2) !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        input.auth-luxury-input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0px 1000px #FFFFFF inset, 0 0 0 3px var(--auth-focus-ring) !important;
            box-shadow: 0 0 0px 1000px #FFFFFF inset, 0 0 0 3px var(--auth-focus-ring) !important;
            border-color: var(--auth-border-focus) !important;
        }

        /* Password Reveal Button */
        .auth-pwd-toggle-btn {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: var(--auth-text-muted);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            outline: none;
            transition: color 0.18s ease, transform 0.15s ease;
        }

        .auth-pwd-toggle-btn:hover {
            color: var(--auth-text-headline);
            transform: scale(1.08);
        }

        .auth-pwd-toggle-btn:active {
            transform: scale(0.92);
        }

        .auth-pwd-toggle-btn:focus-visible {
            box-shadow: 0 0 0 2px var(--auth-border-focus);
        }

        .auth-pwd-toggle-btn svg {
            width: 18px;
            height: 18px;
            stroke-width: 2;
        }

        /* Password Match Live Feedback Indicator */
        .pwd-match-indicator {
            font-size: 0.72rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 3px;
            transition: opacity 0.2s ease;
        }

        .pwd-match-indicator.match {
            color: #059669;
        }

        .pwd-match-indicator.mismatch {
            color: #DC2626;
        }

        /* --------------------------------------------------------------------------
           PRIMARY SUBMIT BUTTON (Emil Kowalski Tactile Depth)
           -------------------------------------------------------------------------- */
        .auth-submit-btn {
            position: relative;
            width: 100%;
            margin-top: 0.6rem;
            padding: 13px 24px;
            font-family: var(--font-sans);
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #FFFFFF;
            background: linear-gradient(180deg, #3A231A 0%, #251610 100%);
            border: 1px solid #1E120E;
            border-radius: var(--auth-radius-input);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 
                inset 0 1px 0 rgba(255, 255, 255, 0.15),
                0 4px 14px rgba(43, 27, 21, 0.22);
            transition: transform 0.18s var(--auth-ease), box-shadow 0.18s ease, background 0.18s ease;
            outline: none;
            overflow: hidden;
        }

        .auth-submit-btn:hover {
            transform: translateY(-2px);
            background: linear-gradient(180deg, #442A20 0%, #2B1912 100%);
            box-shadow: 
                inset 0 1px 0 rgba(255, 255, 255, 0.22),
                0 8px 22px rgba(43, 27, 21, 0.3);
        }

        .auth-submit-btn:active {
            transform: scale(0.98) translateY(0);
            box-shadow: 
                inset 0 1px 2px rgba(0, 0, 0, 0.4),
                0 2px 6px rgba(43, 27, 21, 0.18);
        }

        .auth-submit-btn:focus-visible {
            box-shadow: 0 0 0 3px var(--auth-focus-ring), 0 0 0 1px var(--auth-border-focus);
        }

        .auth-submit-arrow {
            width: 18px;
            height: 18px;
            stroke-width: 2.2;
            transition: transform 0.2s var(--auth-ease);
        }

        .auth-submit-btn:hover .auth-submit-arrow {
            transform: translateX(4px);
        }

        /* --------------------------------------------------------------------------
           SECONDARY ACTIONS & GUEST BROWSE LINK
           -------------------------------------------------------------------------- */
        .auth-footer-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0 1.2rem 0;
            color: #A89B93;
            font-size: 0.74rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .auth-footer-divider::before,
        .auth-footer-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(78, 56, 46, 0.12);
        }

        .auth-footer-divider span {
            padding: 0 12px;
        }

        .guest-browse-btn {
            width: 100%;
            padding: 10px 18px;
            font-family: var(--font-sans);
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--auth-text-headline);
            background: #F8F5F0;
            border: 1px solid rgba(78, 56, 46, 0.14);
            border-radius: var(--auth-radius-input);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.18s var(--auth-ease), background-color 0.18s ease, border-color 0.18s ease;
        }

        .guest-browse-btn:hover {
            transform: translateY(-1px);
            background: #FFFFFF;
            border-color: rgba(78, 56, 46, 0.28);
            color: var(--auth-brand-gold-deep);
            box-shadow: 0 3px 10px rgba(43, 27, 21, 0.06);
        }

        .guest-browse-btn:active {
            transform: scale(0.98);
        }

        .guest-browse-btn svg {
            width: 16px;
            height: 16px;
            stroke-width: 2;
        }

        /* Security Verification Footer */
        .auth-security-badge {
            margin-top: 1.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.72rem;
            color: var(--auth-text-muted);
            letter-spacing: 0.02em;
        }

        .auth-security-badge svg {
            width: 14px;
            height: 14px;
            color: #059669;
        }

        /* --------------------------------------------------------------------------
           RESPONSIVE BREAKPOINTS
           -------------------------------------------------------------------------- */
        @media (max-width: 1024px) {
            .auth-viewport {
                grid-template-columns: 1fr;
            }
            .auth-hero-pane {
                padding: 3rem 2rem;
            }
            .hero-stats-row {
                grid-template-columns: repeat(3, 1fr);
            }
            .auth-form-pane {
                padding: 2.5rem 1.5rem;
            }
        }

        @media (max-width: 640px) {
            .auth-hero-pane {
                padding: 2rem 1.25rem;
            }
            .hero-title-main {
                font-size: 2.1rem;
            }
            .hero-stats-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .auth-form-pane {
                padding: 1.5rem 1rem;
            }
            .auth-luxury-card {
                padding: 1.8rem 1.25rem;
                border-radius: 14px;
            }
            .auth-row-2col {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="auth-viewport">
        <!-- ==================================================================
             LEFT PANE: High-End Bakery Atmosphere & Brand Heritage
             ================================================================== -->
        <div class="auth-hero-pane">
            <div class="hero-brand-block">
                <!-- Header Pill -->
                <div class="hero-brand-header">
                    <div class="brand-logo-disc">
                        <img src="assets/ASENTISTA FINAL.png" alt="Asentista Crest">
                    </div>
                    <div class="brand-text-col">
                        <span class="brand-text-name">ASENTISTA'S</span>
                        <span class="brand-text-sub">Bakery & Coffee Roasters</span>
                    </div>
                </div>

                <!-- Main Display Headline -->
                <h1 class="hero-title-main">
                    Artisan Sourdough & <span>Freshly Brewed</span> Craft.
                </h1>
                <p class="hero-subtitle-p">
                    Handmade daily in small batches using naturally fermented wild levain, stone-ground flours, and zero artificial preservatives.
                </p>

                <!-- Live Social Proof & Trust Stats -->
                <div class="hero-stats-row">
                    <div class="hero-stat-card">
                        <div class="stat-value">12,000+</div>
                        <div class="stat-label">Loaves Baked Fresh</div>
                    </div>
                    <div class="hero-stat-card">
                        <div class="stat-value">4.9 / 5.0</div>
                        <div class="stat-label">Artisan Reviews</div>
                    </div>
                    <div class="hero-stat-card">
                        <div class="stat-value">18-Hour</div>
                        <div class="stat-label">Slow Fermentation</div>
                    </div>
                </div>

                <!-- Feature Highlights -->
                <div class="hero-features-group">
                    <div class="hero-feature-card">
                        <div class="hero-feat-icon-box">🥖</div>
                        <div class="hero-feat-text">
                            <span class="hero-feat-title">Brick Oven Special Breads</span>
                            <span class="hero-feat-desc">Slow-fermented sourdoughs, crispy baguettes & buttery laminated croissants.</span>
                        </div>
                    </div>

                    <div class="hero-feature-card">
                        <div class="hero-feat-icon-box">☕</div>
                        <div class="hero-feat-text">
                            <span class="hero-feat-title">Handcrafted Beverages</span>
                            <span class="hero-feat-desc">18-hour cold brew, cortados, Americanos & single-origin espresso blends.</span>
                        </div>
                    </div>

                    <div class="hero-feature-card">
                        <div class="hero-feat-icon-box">🌾</div>
                        <div class="hero-feat-text">
                            <span class="hero-feat-title">100% Natural Organic Craft</span>
                            <span class="hero-feat-desc">Wild cultured starter, mountain spring water, and locally sourced grains.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Founder Quote Block -->
            <div class="hero-founder-quote">
                <div class="quote-body">
                    "The smell of good bread baking is indescribable in its evocation of innocence and delight."
                </div>
                <div class="quote-author">— Kyle Asentista, Master Baker & Founder</div>
            </div>
        </div>

        <!-- ==================================================================
             RIGHT PANE: Luxury Authentication Concierge
             ================================================================== -->
        <div class="auth-form-pane">
            <div class="auth-card-container">
                <div class="auth-luxury-card">
                    
                    <!-- Card Crest & Title -->
                    <div class="card-header-block">
                        <div class="card-brand-crest">
                            <img src="assets/ASENTISTA FINAL.png" alt="Asentista Bakery">
                        </div>
                        <h2 class="card-headline" id="authHeadingText">
                            <?php echo $activeTab === 'register' ? 'Create Your Account' : 'Welcome to Asentista\'s'; ?>
                        </h2>
                        <p class="card-tagline" id="authSubtitleText">
                            <?php echo $activeTab === 'register' ? 'Join our bakery circle for orders, table bookings & rewards' : 'Sign in to access fresh orders, bookings & bakery rewards'; ?>
                        </p>
                    </div>

                    <!-- Fluid Segmented Switcher (Tabs) -->
                    <div class="auth-segmented-wrapper" role="tablist">
                        <button type="button" class="auth-segment-btn <?php echo $activeTab === 'login' ? 'active' : ''; ?>" id="tabBtnLogin" onclick="switchAuthTab('login')" role="tab">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                <polyline points="10 17 15 12 10 7"></polyline>
                                <line x1="15" y1="12" x2="3" y2="12"></line>
                            </svg>
                            <span>Sign In</span>
                        </button>
                        <button type="button" class="auth-segment-btn <?php echo $activeTab === 'register' ? 'active' : ''; ?>" id="tabBtnRegister" onclick="switchAuthTab('register')" role="tab">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <line x1="20" y1="8" x2="20" y2="14"></line>
                                <line x1="23" y1="11" x2="17" y2="11"></line>
                            </svg>
                            <span>Create Account</span>
                        </button>
                    </div>

                    <!-- Alert / Context Notices -->
                    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'login_to_order'): ?>
                        <div class="auth-alert auth-alert-warning">
                            <span style="font-size: 1.15rem; flex-shrink: 0;">🔒</span>
                            <div><strong>Account Required to Order:</strong> Please sign in or create an account to finalize and confirm your bakery order!</div>
                        </div>
                    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'admin_required'): ?>
                        <div class="auth-alert auth-alert-error">
                            <span style="font-size: 1.15rem; flex-shrink: 0;">👑</span>
                            <div><strong>Admin Access Required:</strong> You must sign in with an authorized Administrator account to enter the Management Console.</div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errorMsg)): ?>
                        <div class="auth-alert auth-alert-error">
                            <span style="font-size: 1.15rem; flex-shrink: 0;">⚠️</span>
                            <div><?php echo htmlspecialchars($errorMsg); ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- ==============================================================
                         FORM 1: SIGN IN FORM
                         ============================================================== -->
                    <form action="auth.php<?php echo !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST" id="formLogin" class="auth-form" style="display: <?php echo $activeTab === 'login' ? 'flex' : 'none'; ?>;">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="auth_action" value="login">

                        <!-- Email or Username Field -->
                        <div class="auth-field-group">
                            <label class="auth-label" for="loginEmail">
                                <span>Email Address or Username <span class="required-star">*</span></span>
                            </label>
                            <div class="auth-input-container">
                                <span class="auth-input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                </span>
                                <input type="text" id="loginEmail" name="email" class="auth-luxury-input" placeholder="admin@asentista.com or asentista" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="username">
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="auth-field-group">
                            <label class="auth-label" for="loginPassword">
                                <span>Password <span class="required-star">*</span></span>
                            </label>
                            <div class="auth-input-container">
                                <span class="auth-input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                </span>
                                <input type="password" id="loginPassword" name="password" class="auth-luxury-input" placeholder="Enter your password" autocomplete="current-password" required>
                                <button type="button" class="auth-pwd-toggle-btn" onclick="togglePasswordVisibility('loginPassword', this)" title="Toggle password visibility" aria-label="Toggle password visibility">
                                    <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="auth-submit-btn" id="loginSubmitBtn">
                            <span>Sign In & Enter Bakery</span>
                            <svg class="auth-submit-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </button>
                    </form>

                    <!-- ==============================================================
                         FORM 2: CREATE ACCOUNT FORM
                         ============================================================== -->
                    <form action="auth.php<?php echo !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST" id="formRegister" class="auth-form" style="display: <?php echo $activeTab === 'register' ? 'flex' : 'none'; ?>;">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="auth_action" value="register">

                        <!-- Full Name -->
                        <div class="auth-field-group">
                            <label class="auth-label" for="regName">
                                <span>Full Name <span class="required-star">*</span></span>
                            </label>
                            <div class="auth-input-container">
                                <span class="auth-input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </span>
                                <input type="text" id="regName" name="name" class="auth-luxury-input" placeholder="e.g. Maria Santos" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" autocomplete="name">
                            </div>
                        </div>

                        <!-- Email & Phone in 2-Column Row -->
                        <div class="auth-row-2col">
                            <div class="auth-field-group">
                                <label class="auth-label" for="regEmail">
                                    <span>Email <span class="required-star">*</span></span>
                                </label>
                                <div class="auth-input-container">
                                    <span class="auth-input-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                            <polyline points="22,6 12,13 2,6"></polyline>
                                        </svg>
                                    </span>
                                    <input type="email" id="regEmail" name="email" class="auth-luxury-input" placeholder="maria@gmail.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="email">
                                </div>
                            </div>

                            <div class="auth-field-group">
                                <label class="auth-label" for="regPhone">
                                    <span>Phone</span>
                                </label>
                                <div class="auth-input-container">
                                    <span class="auth-input-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                        </svg>
                                    </span>
                                    <input type="tel" id="regPhone" name="phone" class="auth-luxury-input" placeholder="0912 345 6789" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" autocomplete="tel">
                                </div>
                            </div>
                        </div>

                        <!-- Passwords in 2-Column Row -->
                        <div class="auth-row-2col">
                            <div class="auth-field-group">
                                <label class="auth-label" for="regPassword">
                                    <span>Password <span class="required-star">*</span></span>
                                </label>
                                <div class="auth-input-container">
                                    <span class="auth-input-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                    </span>
                                    <input type="password" id="regPassword" name="password" class="auth-luxury-input" placeholder="Create password" autocomplete="new-password" required oninput="validateLivePasswordMatch()">
                                    <button type="button" class="auth-pwd-toggle-btn" onclick="togglePasswordVisibility('regPassword', this)" title="Toggle password visibility">
                                        <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="auth-field-group">
                                <label class="auth-label" for="regConfirm">
                                    <span>Confirm <span class="required-star">*</span></span>
                                </label>
                                <div class="auth-input-container">
                                    <span class="auth-input-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                    </span>
                                    <input type="password" id="regConfirm" name="confirm_password" class="auth-luxury-input" placeholder="Repeat password" autocomplete="new-password" required oninput="validateLivePasswordMatch()">
                                    <button type="button" class="auth-pwd-toggle-btn" onclick="togglePasswordVisibility('regConfirm', this)" title="Toggle password visibility">
                                        <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Live Password Match Status Message -->
                        <div id="pwdMatchMsg" class="pwd-match-indicator" style="display: none;"></div>

                        <!-- Registration Submit Button -->
                        <button type="submit" class="auth-submit-btn" id="regSubmitBtn">
                            <span>Create Account & Enter Bakery</span>
                            <svg class="auth-submit-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="auth-footer-divider">
                        <span>or continue exploring</span>
                    </div>

                    <!-- Guest Access Link -->
                    <a href="auth.php?guest=1" class="guest-browse-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
                        </svg>
                        <span>Browse Bakery Menu as Guest</span>
                    </a>

                    <!-- Security Badge -->
                    <div class="auth-security-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        <span>256-Bit SSL Encrypted & CSRF Protected Session</span>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Client-side Scripting -->
    <script>
        // Tab Switcher with Fluid State Updates
        function switchAuthTab(tab) {
            const formLogin = document.getElementById('formLogin');
            const formRegister = document.getElementById('formRegister');
            const tabBtnLogin = document.getElementById('tabBtnLogin');
            const tabBtnRegister = document.getElementById('tabBtnRegister');
            const headingText = document.getElementById('authHeadingText');
            const subtitleText = document.getElementById('authSubtitleText');

            if (tab === 'register') {
                formLogin.style.display = 'none';
                formRegister.style.display = 'flex';
                tabBtnLogin.classList.remove('active');
                tabBtnRegister.classList.add('active');
                headingText.textContent = "Create Your Account";
                subtitleText.textContent = "Join our bakery circle for orders, table bookings & rewards";
            } else {
                formLogin.style.display = 'flex';
                formRegister.style.display = 'none';
                tabBtnLogin.classList.add('active');
                tabBtnRegister.classList.remove('active');
                headingText.textContent = "Welcome to Asentista's";
                subtitleText.textContent = "Sign in to access fresh orders, bookings & bakery rewards";
            }
        }

        // Interactive Password Visibility Peek Toggle
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            if (isPassword) {
                // Eye-off icon
                btn.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                `;
            } else {
                // Eye icon
                btn.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
            }
        }

        // Live Password Confirmation Feedback
        function validateLivePasswordMatch() {
            const pwd = document.getElementById('regPassword').value;
            const confirm = document.getElementById('regConfirm').value;
            const msgEl = document.getElementById('pwdMatchMsg');

            if (!confirm) {
                msgEl.style.display = 'none';
                return;
            }

            msgEl.style.display = 'flex';
            if (pwd === confirm) {
                msgEl.className = 'pwd-match-indicator match';
                msgEl.innerHTML = `
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Passwords match!</span>
                `;
            } else {
                msgEl.className = 'pwd-match-indicator mismatch';
                msgEl.innerHTML = `
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    <span>Passwords do not match yet</span>
                `;
            }
        }
    </script>
</body>
</html>
