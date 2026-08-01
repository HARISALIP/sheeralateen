<?php
/**
 * Minimal CSRF protection helpers.
 * One token per session; call csrf_token() when rendering a form,
 * csrf_verify() when handling its POST.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $submittedToken): bool
{
    return isset($_SESSION['csrf_token'])
        && $submittedToken !== null
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}
