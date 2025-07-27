<?php
/**
 * auth.php — Thin Facade: Authentication, Session, CSRF Helpers
 *
 * Delegates all authentication logic to AuthService.
 * Keeps legacy global helpers for controller compatibility.
 *
 * @package PixlKey
 * @subpackage Core\Auth
 * @author Jeffrey Weese
 * @license MIT
 * @version 0.5.1.4-alpha
 */

declare(strict_types=1);

require_once __DIR__ . '/AuthService.php';
require_once __DIR__ . '/../dao/UserDAO.php';

use PixlKey\Auth\AuthService;
use PixlKey\DAO\UserDAO;

$userDAO = new UserDAO($pdo);
$authService = new AuthService($userDAO);

// --- Legacy global wrappers for compatibility ---
function login_user(string $user_id): void {
    global $userDAO, $authService;
    // Backward compatible login by user_id only (rare).
    $user = $userDAO->findById($user_id);
    if (!$user) throw new \Exception('User not found.');
    $_SESSION['user_id'] = $user_id;
    session_regenerate_id(true);
    \PixlKey\Security\rotateToken();
    $userDAO->updateLastLogin($user_id);
    clear_failed_attempts($authService->getRateLimitKey());
}

function require_login(): void {
    global $authService;
    $authService->requireLogin();
}

function current_user(): ?array {
    global $authService;
    return $authService->currentUser();
}

function authenticate_user(string $email, string $password): ?array {
    global $authService;
    return $authService->login($email, $password);
}