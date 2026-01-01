<?php
declare(strict_types=1);

class DashboardController extends BaseController {

    public function index(): void {
        if (Session::has('user_id')) {
            $teamModel = new Team($this->pdo);
            $teams = $teamModel->getTeamsForUser(Session::get('user_id'));
            View::render('dashboard', ['teams' => $teams, 'pageTitle' => 'Dashboard - Trainer Bobby']);
        } else {
            View::render('home', ['pageTitle' => 'Home - Trainer Bobby']);
        }
    }
}
