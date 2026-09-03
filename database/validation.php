<?php
/**
 * Asentista Bakery - Form Validation & Sanitization Module
 * Based on Week 7: PHP Forms, Validation & Database CRUD
 */

/**
 * Sanitize text input by trimming and escaping special HTML characters.
 * Prevents Cross-Site Scripting (XSS).
 *
 * @param string|null $data
 * @return string
 */
function sanitize_input($data) {
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate required field.
 *
 * @param string $value
 * @param string $fieldName
 * @param array &$errors
 * @return bool
 */
function validate_required($value, $fieldName, &$errors) {
    if ($value === '' || $value === null) {
        $errors[] = "{$fieldName} is required.";
        return false;
    }
    return true;
}

/**
 * Validate email using filter_var.
 *
 * @param string $email
 * @param array &$errors
 * @return bool
 */
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

/**
 * Validate phone number.
 *
 * @param string $phone
 * @param array &$errors
 * @return bool
 */
function validate_phone($phone, &$errors) {
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
        return false;
    }
    // Allow digits, spaces, plus, dashes, parentheses
    if (!preg_match('/^[0-9\-\+\s\(\)]{7,25}$/', $phone)) {
        $errors[] = "Please provide a valid phone number.";
        return false;
    }
    return true;
}

/**
 * Validate length of input string.
 *
 * @param string $value
 * @param string $fieldName
 * @param int $min
 * @param int $max
 * @param array &$errors
 * @return bool
 */
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

// ==============================================================================
// CSRF (CROSS-SITE REQUEST FORGERY) PROTECTION
// ==============================================================================

/**
 * Get current CSRF token or generate a new cryptographically secure token.
 *
 * @return string
 */
function get_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate submitted CSRF token against session token using timing-safe comparison.
 * Checks $_POST['csrf_token'], $_GET['csrf_token'], or HTTP X-CSRF-Token header.
 *
 * @param string|null $token
 * @return bool
 */
function validate_csrf_token($token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    if ($token === null) {
        // Look in POST, GET, or HTTP request headers
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (empty($token) || !is_string($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

// ==============================================================================
// BRUTE FORCE & RATE LIMITING DEFENSE
// ==============================================================================

/**
 * Check if the client or email is temporarily locked out from login attempts.
 * Uses persistent database table `login_throttles` keyed by IP + identifier hash.
 * Max 5 attempts within a 15-minute rolling window.
 *
 * @param string $identifier Usually email or IP
 * @param PDO|null $pdo
 * @return array ['allowed' => bool, 'wait_seconds' => int, 'remaining_attempts' => int]
 */
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
                // Rolling 15-minute window expiration
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

    // Session fallback if PDO is not initialized
    if (session_status() === PHP_SESSION_NONE) session_start();
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

/**
 * Record a failed login attempt. If attempts exceed 5, lock out for 15 minutes.
 *
 * @param string $identifier
 * @param PDO|null $pdo
 */
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

    if (session_status() === PHP_SESSION_NONE) session_start();
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

/**
 * Reset failed login attempts upon successful authentication.
 *
 * @param string $identifier
 * @param PDO|null $pdo
 */
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

