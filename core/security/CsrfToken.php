<?php

/**
 * CsrfToken.php — Centralized CSRF token utilities
 *
 * Provides CSRF generation, validation, and rotation.
 * Designed for re-use across upload, watermark, license, and API routes.
 *
 * @package    PixlKey
 * @subpackage Core\Security
 * @author     Jeffrey Weese
 * @license    MIT
 * @version    0.5.1.4-alpha
 */

declare(strict_types=1);

namespace PixlKey\Security;

use function PixlKey\Session\startSecureSession;
use PixlKey\Auth\AuthService;
use PixlKey\DAO\UserDAO;

// Ensure a secure session is started before working with CSRF tokens
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (function_exists('PixlKey\Session\startSecureSession')) {
        startSecureSession();
    } else {
        session_start();
    }
}

// Instantiate AuthService if not already available (lazy load for token rotation)
if (!isset($GLOBALS['authService'])) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../dao/UserDAO.php';
    require_once __DIR__ . '/../auth/AuthService.php';
    $GLOBALS['authService'] = new AuthService(new UserDAO($pdo));
}


if (!function_exists(__NAMESPACE__ . '\generateToken')) {
    /**
     * Generate or return the current CSRF token.
     *
     * @return string
     */
    function generateToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            // Also notify AuthService if loaded (future hook for JWT/session sync)
            if (isset($GLOBALS['authService']) && method_exists($GLOBALS['authService'], 'onCsrfRotate')) {
                $GLOBALS['authService']->onCsrfRotate($_SESSION['csrf_token']);
            }
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists(__NAMESPACE__ . '\validateToken')) {
    /**
     * Validate the submitted CSRF token against the session token.
     * Aborts with 403 on failure.
     *
     * @return void
     */
    function validateToken(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return;
        }
        $expected = $_SESSION['csrf_token'] ?? '';
        $received = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? '';

        if (!hash_equals($expected, $received)) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
    }
}

if (!function_exists(__NAMESPACE__ . '\rotateToken')) {
    /**
     * Rotate the CSRF token.
     *
     * @return void
     */
    function rotateToken(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
