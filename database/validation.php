<?php
// Form validation and sanitization functions

// Clean input text and escape special characters to prevent XSS
function sanitize_input($data) {
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Check that a required field is not empty
function validate_required($value, $fieldName, &$errors) {
    if ($value === '' || $value === null) {
        $errors[] = "{$fieldName} is required.";
        return false;
    }
    return true;
}

// Validate email format
function validate_email($email, &$errors) {
    if (empty($email)) {
        $errors[] = "Email address is required.";
        return false;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
        return false;
    }
    return true;
}

// Validate phone number format (must consist of exactly 11 numbers, no letters)
function validate_phone($phone, &$errors) {
    $phone = trim($phone ?? '');
    if ($phone === '') {
        $errors[] = "Phone number is required.";
        return false;
    }
    if (!preg_match('/^[0-9]{11}$/', $phone)) {
        $errors[] = "Phone number must consist only of 11 digits (numbers only, e.g. 09123456789).";
        return false;
    }
    return true;
}

// Check min and max character length
function validate_length($value, $fieldName, $min, $max, &$errors) {
    $len = strlen($value);
    if ($len < $min) {
        $errors[] = "{$fieldName} must be at least {$min} characters.";
        return false;
    }
    if ($len > $max) {
        $errors[] = "{$fieldName} cannot exceed {$max} characters.";
        return false;
    }
    return true;
}

// CSRF Protection Helpers

// Get or generate CSRF token for the session
function get_csrf_token() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Check if submitted CSRF token matches session
function validate_csrf_token($token = null) {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (empty($token) || !is_string($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

// Login Rate Limiting (Brute Force Defense)

// Check if user or IP is currently locked out from trying to log in
function check_login_attempts($identifier, ?PDO $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = hash('sha256', strtolower(trim($identifier)) . '|' . $clientIp);
    $now = time();

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT attempts, first_attempt, lockout_until FROM `login_throttles` WHERE identifier = :key LIMIT 1");
            $stmt->execute([':key' => $key]);
            $record = $stmt->fetch();

            if ($record) {
                if ($record['lockout_until'] > $now) {
                    return [
                        'allowed' => false,
                        'wait_seconds' => (int)($record['lockout_until'] - $now),
                        'remaining_attempts' => 0
                    ];
                }
                // Reset after 15 minutes have passed
                if ($now - (int)$record['first_attempt'] > 900) {
                    $reset = $pdo->prepare("DELETE FROM `login_throttles` WHERE identifier = :key");
                    $reset->execute([':key' => $key]);
                    return ['allowed' => true, 'wait_seconds' => 0, 'remaining_attempts' => 5];
                }
                return [
                    'allowed' => true,
                    'wait_seconds' => 0,
                    'remaining_attempts' => max(0, 5 - (int)$record['attempts'])
                ];
            }
            return ['allowed' => true, 'wait_seconds' => 0, 'remaining_attempts' => 5];
        } catch (Exception $e) {
            error_log("DB Throttle Check Error: " . $e->getMessage());
        }
    }

    // Fallback using session
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) session_start();
    $sKey = 'login_throttle_' . md5(strtolower(trim($identifier)));
    $attempts = $_SESSION[$sKey] ?? ['count' => 0, 'first_attempt' => $now, 'lockout_until' => 0];
    if (!empty($attempts['lockout_until']) && $attempts['lockout_until'] > $now) {
        return ['allowed' => false, 'wait_seconds' => $attempts['lockout_until'] - $now, 'remaining_attempts' => 0];
    }
    if ($now - $attempts['first_attempt'] > 900) {
        $attempts = ['count' => 0, 'first_attempt' => $now, 'lockout_until' => 0];
        $_SESSION[$sKey] = $attempts;
    }
    return ['allowed' => true, 'wait_seconds' => 0, 'remaining_attempts' => max(0, 5 - $attempts['count'])];
}

// Record a failed login attempt; lockout for 15 minutes after 5 failures
function record_failed_attempt($identifier, ?PDO $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = hash('sha256', strtolower(trim($identifier)) . '|' . $clientIp);
    $now = time();

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id, attempts, first_attempt FROM `login_throttles` WHERE identifier = :key LIMIT 1");
            $stmt->execute([':key' => $key]);
            $row = $stmt->fetch();

            if ($row) {
                if ($now - (int)$row['first_attempt'] > 900) {
                    $upd = $pdo->prepare("UPDATE `login_throttles` SET attempts = 1, first_attempt = :now, lockout_until = 0 WHERE id = :id");
                    $upd->execute([':now' => $now, ':id' => $row['id']]);
                } else {
                    $newAttempts = (int)$row['attempts'] + 1;
                    $lockout = ($newAttempts >= 5) ? ($now + 900) : 0;
                    $upd = $pdo->prepare("UPDATE `login_throttles` SET attempts = :att, lockout_until = :lock WHERE id = :id");
                    $upd->execute([':att' => $newAttempts, ':lock' => $lockout, ':id' => $row['id']]);
                }
            } else {
                $ins = $pdo->prepare("INSERT INTO `login_throttles` (identifier, attempts, first_attempt, lockout_until) VALUES (:key, 1, :now, 0)");
                $ins->execute([':key' => $key, ':now' => $now]);
            }
        } catch (Exception $e) {
            error_log("DB Throttle Record Error: " . $e->getMessage());
        }
    }

    if (session_status() === PHP_SESSION_NONE && !headers_sent()) session_start();
    $sKey = 'login_throttle_' . md5(strtolower(trim($identifier)));
    $attempts = $_SESSION[$sKey] ?? ['count' => 0, 'first_attempt' => $now, 'lockout_until' => 0];
    if ($now - $attempts['first_attempt'] > 900) {
        $attempts = ['count' => 0, 'first_attempt' => $now, 'lockout_until' => 0];
    }
    $attempts['count']++;
    if ($attempts['count'] >= 5) {
        $attempts['lockout_until'] = $now + 900;
    }
    $_SESSION[$sKey] = $attempts;
}

// Clear failed login attempts after user logs in successfully
function reset_login_attempts($identifier, ?PDO $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = hash('sha256', strtolower(trim($identifier)) . '|' . $clientIp);

    if ($pdo) {
        try {
            $del = $pdo->prepare("DELETE FROM `login_throttles` WHERE identifier = :key");
            $del->execute([':key' => $key]);
        } catch (Exception $e) {
            error_log("DB Throttle Reset Error: " . $e->getMessage());
        }
    }

    if (session_status() === PHP_SESSION_NONE) session_start();
    $sKey = 'login_throttle_' . md5(strtolower(trim($identifier)));
    unset($_SESSION[$sKey]);
}
