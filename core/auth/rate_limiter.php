<?php
// rate_limiter.php

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
    error_log(sprintf("[RateLimiter] Key=%s Time=%s IP=%s\n", $key, date('c'), $_SERVER['REMOTE_ADDR'] ?? 'unknown'), 3, __DIR__ . '/logs/rate_limiter.log');
    */
}

function clear_failed_attempts(string $key): void
{
    unset($_SESSION['rate'][$key]);
}
