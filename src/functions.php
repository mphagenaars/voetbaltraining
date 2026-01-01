<?php
declare(strict_types=1);

/**
 * Escapes a string for safe output in HTML.
 * Alias for htmlspecialchars with safe defaults.
 */
function e(string|null $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Debug helper
 */
function dd(mixed ...$vars): void {
    foreach ($vars as $var) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
    }
    die();
}
