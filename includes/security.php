<?php
/**
 * BrightBlaze – Security Helpers
 *
 * Provides:
 *  - Login throttling and temporary lockout
 *  - Failed-login tracking
 *  - Security-event audit logging
 *  - Password policy enforcement
 *  - Sensitive setting encryption/decryption
 *
 * Requires config/config.php and includes/session.php.
 */

/**
 * Configuration constants with safe defaults.
 */
const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_DURATION_MINUTES = 15;
const SESSION_LIFETIME_MINUTES = 120;
const SESSION_IDLE_TIMEOUT_MINUTES = 30;
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_REQUIRE_UPPERCASE = true;
const PASSWORD_REQUIRE_NUMBER = true;
const PASSWORD_REQUIRE_SPECIAL = true;

/**
 * Read effective security settings from environment or settings table.
 */
function security_settings(): array
{
    $settings = [
        'max_login_attempts'     => env_int('MAX_LOGIN_ATTEMPTS', MAX_LOGIN_ATTEMPTS),
        'lockout_duration'       => env_int('LOCKOUT_DURATION_MINUTES', LOCKOUT_DURATION_MINUTES),
        'session_lifetime'       => env_int('SESSION_LIFETIME_MINUTES', SESSION_LIFETIME_MINUTES),
        'session_idle_timeout'   => env_int('SESSION_IDLE_TIMEOUT_MINUTES', SESSION_IDLE_TIMEOUT_MINUTES),
        'password_min_length'    => env_int('PASSWORD_MIN_LENGTH', PASSWORD_MIN_LENGTH),
        'password_require_upper' => env_bool('PASSWORD_REQUIRE_UPPERCASE', PASSWORD_REQUIRE_UPPERCASE),
        'password_require_number'=> env_bool('PASSWORD_REQUIRE_NUMBER', PASSWORD_REQUIRE_NUMBER),
        'password_require_special'=> env_bool('PASSWORD_REQUIRE_SPECIAL', PASSWORD_REQUIRE_SPECIAL),
    ];

    try {
        $dbSettings = get_settings();
        foreach ($settings as $key => $default) {
            $dbKey = $key;
            if (isset($dbSettings[$dbKey]) && $dbSettings[$dbKey] !== '') {
                $val = $dbSettings[$dbKey];
                if (is_bool($default)) {
                    $settings[$key] = env_bool($dbKey, $default);
                } elseif (is_int($default)) {
                    $settings[$key] = env_int($dbKey, $default);
                } else {
                    $settings[$key] = (string) $val;
                }
            }
        }
    } catch (PDOException $e) {
        // Settings table missing: use environment/defaults.
    }

    return $settings;
}

/**
 * Record a failed login attempt for a username.
 */
function record_failed_login(string $username): void
{
    try {
        $stmt = db()->prepare('INSERT INTO login_attempts (username, attempted_at) VALUES (?, NOW())');
        $stmt->execute([$username]);
    } catch (PDOException $e) {
        log_error('Failed to record login attempt: ' . $e->getMessage());
    }
}

/**
 * Count recent failed attempts for a username within the lockout window.
 */
function count_recent_failed_logins(string $username): int
{
    $sec = security_settings();
    $windowMinutes = max(1, $sec['lockout_duration']);
    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$username, $windowMinutes]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        log_error('Failed to count login attempts: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Clear failed attempts for a username after successful login.
 */
function clear_failed_logins(string $username): void
{
    try {
        $stmt = db()->prepare('DELETE FROM login_attempts WHERE username = ?');
        $stmt->execute([$username]);
    } catch (PDOException $e) {
        log_error('Failed to clear login attempts: ' . $e->getMessage());
    }
}

/**
 * Check whether a username is currently locked out.
 */
function is_locked_out(string $username): bool
{
    $sec = security_settings();
    return count_recent_failed_logins($username) >= $sec['max_login_attempts'];
}

/**
 * Log a security event for audit purposes.
 */
function log_security_event(string $type, string $message, array $context = []): void
{
    $context = array_merge($context, [
        'type'      => $type,
        'timestamp' => date('c'),
        'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'cli',
        'user_agent'=> $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);

    if (is_logged_in()) {
        $context['user_id']   = current_user_id();
        $context['username']  = current_user_name();
        $context['role']      = current_role();
    }

    log_error('SECURITY: ' . $message, $context);
}

/**
 * Validate a password against the configured policy.
 *
 * @return array Empty array on success, array of error messages on failure.
 */
function validate_password_policy(string $password): array
{
    $sec = security_settings();
    $errors = [];

    if (strlen($password) < $sec['password_min_length']) {
        $errors[] = "Password must be at least {$sec['password_min_length']} characters long.";
    }
    if ($sec['password_require_upper'] && !preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if ($sec['password_require_number'] && !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if ($sec['password_require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }

    return $errors;
}

/**
 * Encrypt a string using ENCRYPTION_KEY.
 *
 * @return string Base64-encoded ciphertext or empty string on failure.
 */
function encrypt_value(string $plaintext): string
{
    $key = env('ENCRYPTION_KEY', '');
    if ($key === '') {
        return $plaintext;
    }

    $keyBytes = hash('sha256', $key, true);
    $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $keyBytes, OPENSSL_RAW_DATA, $iv);

    if ($cipher === false) {
        log_security_event('encryption', 'Encryption failed for a sensitive value.');
        return $plaintext;
    }

    return base64_encode($iv . $cipher);
}

/**
 * Decrypt a string encrypted with encrypt_value().
 *
 * @return string Decrypted plaintext or original input on failure.
 */
function decrypt_value(string $ciphertext): string
{
    $key = env('ENCRYPTION_KEY', '');
    if ($key === '' || strlen($ciphertext) < 16) {
        return $ciphertext;
    }

    $keyBytes = hash('sha256', $key, true);
    $data = base64_decode($ciphertext, true);
    if ($data === false) {
        return $ciphertext;
    }

    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $ivLen);
    $cipher = substr($data, $ivLen);
    $plaintext = openssl_decrypt($cipher, 'aes-256-cbc', $keyBytes, OPENSSL_RAW_DATA, $iv);

    if ($plaintext === false) {
        log_security_event('encryption', 'Decryption failed for a sensitive value.');
        return $ciphertext;
    }

    return $plaintext;
}
