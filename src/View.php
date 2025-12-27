<?php

declare(strict_types=1);

class View {
    public static function render(string $viewPath, array $data = []): void {
        // Extract data to variables
        extract($data);

        // Default page title if not set
        if (!isset($pageTitle)) {
            $pageTitle = 'Trainer Bobby';
        }

        // Define paths
        $viewFile = __DIR__ . '/views/' . $viewPath . '.php';
        $headerFile = __DIR__ . '/views/layout/header.php';
        $footerFile = __DIR__ . '/views/layout/footer.php';

        // Check if view exists
        if (!file_exists($viewFile)) {
            throw new RuntimeException("View file not found: $viewFile");
        }

        // Include files
        require $headerFile;
        require $viewFile;
        require $footerFile;
    }
}
