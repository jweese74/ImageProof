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
 * @license    MIT
 */

declare(strict_types=1);

namespace PixlKey\Session;

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
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
    ]);
}