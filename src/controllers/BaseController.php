<?php
declare(strict_types=1);

abstract class BaseController {
    public function __construct(protected PDO $pdo) {}

    protected function requireAuth(): void {
        if (!Session::has('user_id')) {
            $currentUrl = urlencode($_SERVER['REQUEST_URI']);
            $this->redirect('/login?redirect=' . $currentUrl);
        }
    }

    protected function requireTeamContext(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            $this->redirect('/');
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

    protected function resolveSortFilter(string $prefix, array $allowedSort = ['asc', 'desc'], array $allowedFilter = ['all', 'upcoming']): array {
        $sortKey = $prefix . '_sort';
        $filterKey = $prefix . '_filter';

        // Default values
        $defaultSort = 'asc';
        $defaultFilter = 'all';

        // Sort: GET > Cookie > Session > Default
        $sort = $_GET['sort'] ?? $_COOKIE[$sortKey] ?? Session::get($sortKey, $defaultSort);
        if (!in_array($sort, $allowedSort)) {
            $sort = $defaultSort;
        }
        Session::set($sortKey, $sort);
        setcookie($sortKey, $sort, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);

        // Filter: GET > Cookie > Session > Default
        $filter = $_GET['filter'] ?? $_COOKIE[$filterKey] ?? Session::get($filterKey, $defaultFilter);
        if (!in_array($filter, $allowedFilter)) {
            $filter = $defaultFilter;
        }
        Session::set($filterKey, $filter);
        setcookie($filterKey, $filter, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);

        return [$sort, $filter];
    }

    protected function logActivity(string $action, ?int $entityId = null, ?string $details = null): void {
        $logModel = new ActivityLog($this->pdo);
        $logModel->log(Session::get('user_id'), $action, $entityId, $details);
    }
}
