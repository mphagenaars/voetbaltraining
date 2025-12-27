<?php
declare(strict_types=1);

class DashboardController {
    public function __construct(private PDO $pdo) {}

    public function index(): void {
        if (isset($_SESSION['user_id'])) {
            $teamModel = new Team($this->pdo);
            $teams = $teamModel->getTeamsForUser($_SESSION['user_id']);
            View::render('dashboard', ['teams' => $teams, 'pageTitle' => 'Dashboard - Trainer Bobby']);
        } else {
            View::render('home', ['pageTitle' => 'Home - Trainer Bobby']);
        }
    }
}
