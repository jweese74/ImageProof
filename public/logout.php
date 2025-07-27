<?php

/**
 * logout.php — Securely terminates user sessions and rotates CSRF token post-logout
 *
 * PixlKey Project – Beta 0.5.0
 * Part of a secure PHP platform for managing digital artwork.
 *
 * Handles secure user logout using the centralised AuthService,
 * which clears session data, expires cookies, regenerates session IDs,
 * and rotates CSRF tokens to mitigate fixation and replay attacks.
 * Redirects users to the login screen.
 *
 * @package    PixlKey
 * @subpackage Public
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.1.4-alpha
 * @see        /public/login.php, /core/auth/AuthService.php
 */

require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/auth/AuthService.php';
require_once __DIR__ . '/../core/dao/UserDAO.php';

use PixlKey\Auth\AuthService;
use PixlKey\DAO\UserDAO;

$userDAO = new UserDAO($pdo);
$authService = new AuthService($userDAO);

// Use AuthService to securely log out
$authService->logout();

// Redirect to login
header('Location: /public/login.php');
exit;
