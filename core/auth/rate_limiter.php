<?php

/**
 * rate_limiter.php — Session-based rate limiting for sensitive PixlKey actions
 *
 * PixlKey Project – Beta 0.5.0  
 * Part of a secure PHP platform for managing digital artwork.
 *
 * Provides request throttling using time-based session tracking to mitigate brute-force,
 * abuse, and spam on login, download, and registration endpoints. Sends HTTP 429 responses
 * when thresholds are exceeded. Integrates with configurable limits and optional audit logging.
 *
 * @package    PixlKey
 * @subpackage Core/Auth
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.0-beta
 * @see        /core/auth/login.php, /download_zip.php, /register.php
 */

require_once __DIR__ . '/../config/config.php';

// Default thresholds — override in config.php or .env
defined('LOGIN_ATTEMPT_LIMIT') || define('LOGIN_ATTEMPT_LIMIT', 5);
defined('LOGIN_DECAY_SECONDS') || define('LOGIN_DECAY_SECONDS', 900); // 15 min
defined('DOWNLOAD_ATTEMPT_LIMIT') || define('DOWNLOAD_ATTEMPT_LIMIT', 10);
defined('DOWNLOAD_DECAY_SECONDS') || define('DOWNLOAD_DECAY_SECONDS', 60); // 1 min

/**
 * Optional: Send 429 Too Many Requests with Retry-After header.
 */
function rate_limit_exceeded_response(int $retryAfterSeconds = 60): void
{
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: ' . $retryAfterSeconds);
    echo "Too many requests. Please wait {$retryAfterSeconds} seconds.";
    exit;
}

function too_many_attempts(string $key, int $maxAttempts, int $decaySeconds): bool
{
    $now = time();
    $_SESSION['rate'][$key] = array_filter(
        $_SESSION['rate'][$key] ?? [],
        fn($ts) => ($now - $ts) < $decaySeconds
    );
    return count($_SESSION['rate'][$key]) >= $maxAttempts;
}

function record_failed_attempt(string $key): void
{
    $_SESSION['rate'][$key][] = time();

    // Optional logging for future audit trail (disabled by default)
    /*
    error_log(sprintf("[RateLimiter] Key=%s Time=%s IP=%s\n", $key, date('c'), $_SERVER['REMOTE_ADDR'] ?? 'unknown'), 3, dirname(__DIR__, 2) . '/logs/rate_limiter.log');
    */
}

function clear_failed_attempts(string $key): void
{
    unset($_SESSION['rate'][$key]);
}
