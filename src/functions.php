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

/**
 * Format a source URL with an appropriate icon
 */
function formatSourceLink(?string $url): string {
    if (empty($url)) {
        return '';
    }

    $icon = '🌐'; // Default web icon
    $displayText = 'Bekijk bron';
    
    // Check for YouTube
    if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
        $icon = '📺'; // TV/Video icon for YouTube
        $displayText = 'Bekijk video';
    }

    // Try to be smart if it's not a URL but just text
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return '<span class="source-text">📝 ' . e($url) . '</span>';
    }

    return sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer" class="source-link" title="Open bron">%s %s</a>',
        e($url),
        $icon,
        $displayText
    );
}
