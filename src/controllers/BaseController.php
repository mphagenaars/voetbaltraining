<?php
declare(strict_types=1);

abstract class BaseController {
    public function __construct(protected PDO $pdo) {}

    protected function requireAuth(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }

    protected function verifyCsrf(?string $redirectPath = null): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                // Redirect back to self or specified path on failure
                $path = $redirectPath ?? $_SERVER['REQUEST_URI'];
                header("Location: $path");
                exit;
            }
        }
    }

    protected function redirect(string $path): void {
        header("Location: $path");
        exit;
    }
}
