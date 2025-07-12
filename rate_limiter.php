<?php
// rate_limiter.php

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
}

function clear_failed_attempts(string $key): void
{
    unset($_SESSION['rate'][$key]);
}
