<?php
/**
 * SessionBootstrap.php — Centralised secure session start
 *
 * Provides a convenience function to initialise PixlKey sessions
 * with strict cookie flags and HTTPS enforcement, avoiding
 * repetition across entry scripts.
 *
 * @package    PixlKey
 * @subpackage Core\Session
 * @author     Jeffrey Weese
 * @version    0.5.1.3-alpha
 * @license    MIT
 */

declare(strict_types=1);

namespace PixlKey\Session;

require_once __DIR__ . '/../security/CsrfToken.php';
use function PixlKey\Security\rotateToken;

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return; // Already started
    }

    // Global cookie/session security flags
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');

    // Start the session with enforced flags
    session_start([
        'cookie_samesite' => 'Strict',
        'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'cookie_httponly' => true,
    ]);

    // Ensure CSRF token is initialised for all sessions
    if (empty($_SESSION['csrf_token'])) {
        rotateToken();
    }
}