<?php
/**
 * CSRF protection helpers.
 * Loaded automatically via includes/session.php.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden input to embed in every POST form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate the token on POST requests. Call at the top of every POST handler.
 */
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        set_flash('danger', 'Invalid or expired form token. Please try again.');
        header('Location: ' . base_url('index.php'));
        exit;
    }
}
