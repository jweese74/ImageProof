<?php
/**
 * AuthService.php — Centralized Authentication Service Layer
 *
 * Encapsulates all authentication, login, logout, session, and password verification logic.
 * Ensures secure session management, rate limiting, CSRF token hygiene, and cryptographic best practices.
 *
 * @package    PixlKey
 * @subpackage Core\Auth
 * @author     Jeffrey Weese
 * @license    MIT
 * @version    0.5.1.4-alpha
 */

declare(strict_types=1);

namespace PixlKey\Auth;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session/SessionBootstrap.php';
require_once __DIR__ . '/../security/CsrfToken.php';
require_once __DIR__ . '/../auth/rate_limiter.php';
require_once __DIR__ . '/../dao/UserDAO.php';

use PixlKey\DAO\UserDAO;
use function PixlKey\Security\rotateToken;

class AuthService
{
    private UserDAO $userDAO;

    public function __construct(UserDAO $userDAO)
    {
        $this->userDAO = $userDAO;
        \PixlKey\Session\startSecureSession();
    }

    /**
     * Attempt to log in a user by verifying password.
     * Handles rate limiting, session security, and CSRF rotation.
     * @throws \Exception on rate limit exceeded.
     */
    public function login(string $email, string $password): ?array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateKey = 'login_' . $ip;

        if (too_many_attempts($rateKey, LOGIN_ATTEMPT_LIMIT, LOGIN_DECAY_SECONDS)) {
            rate_limit_exceeded_response(LOGIN_DECAY_SECONDS);
            throw new \Exception("Too many login attempts.");
        }

        $user = $this->userDAO->findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            record_failed_attempt($rateKey);
            return null;
        }

        // Password rehash if algorithm/cost changed
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->userDAO->updatePasswordHash($user['user_id'], $newHash);
        }

        // Secure session handling
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        rotateToken();
        $this->userDAO->updateLastLogin($user['user_id']);
        clear_failed_attempts($rateKey);

        return $user;
    }

    /**
     * Require a valid session/login, redirect to login page if missing.
     */
    public function requireLogin(): void
    {
        if (empty($_SESSION['user_id'])) {
            $dest = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: /public/login.php?next=' . $dest);
            exit;
        }
    }

    /**
     * Returns the current logged-in user as an array, or null if not logged in.
     * Caches result per request.
     */
    public function currentUser(): ?array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $cache = $this->userDAO->findById($_SESSION['user_id']);
        return $cache ?: null;
    }

    /**
     * Log out the current user and destroy session.
     * Starts a new secure session and rotates CSRF token.
     */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        \PixlKey\Session\startSecureSession();
        session_regenerate_id(true);
        rotateToken();
    }

    /**
     * Optional: Issue a JWT for the user (for future API use).
     * This is a stub for later implementation.
     */
    public function issueJwt(array $user): string
    {
        // TODO: Add JWT implementation using secure signing
        return '';
    }

    /**
     * Returns the rate-limit key for the given IP/user.
     */
    public function getRateLimitKey(string $ip = null): string
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return 'login_' . $ip;
    }
}