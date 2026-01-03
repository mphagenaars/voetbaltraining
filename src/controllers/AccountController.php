<?php
declare(strict_types=1);

class AccountController extends BaseController {

    public function index(): void {
        // Check of gebruiker is ingelogd
        $this->requireAuth();

        $userId = Session::get('user_id');
        $userModel = new User($this->pdo);
        $teamModel = new Team($this->pdo);

        $user = $userModel->getById($userId);
        $teams = $teamModel->getTeamsForUser($userId);

        View::render('account/index', [
            'user' => $user,
            'teams' => $teams,
            'pageTitle' => 'Mijn Account - Trainer Bobby'
        ]);
    }

    public function teams(): void {
        $this->requireAuth();

        $userId = Session::get('user_id');
        $teamModel = new Team($this->pdo);
        $teams = $teamModel->getTeamsForUser($userId);

        View::render('account/teams', [
            'teams' => $teams,
            'pageTitle' => 'Mijn Teams - Trainer Bobby'
        ]);
    }

    public function updateProfile(): void {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/account');
        }

        $this->verifyCsrf('/account');

        $validator = new Validator($_POST);
        $validator->required('name');
        
        if (!$validator->isValid()) {
            Session::flash('error', 'Naam mag niet leeg zijn.');
            $this->redirect('/account');
        }

        try {
            $userModel = new User($this->pdo);
            $userModel->updateName(Session::get('user_id'), $_POST['name']);
            
            // Update sessie naam ook
            Session::set('user_name', $_POST['name']);

            Session::flash('success', 'Profiel bijgewerkt.');
            $this->redirect('/account');
        } catch (Exception $e) {
            Session::flash('error', 'Er ging iets mis.');
            $this->redirect('/account');
        }
    }

    public function updatePassword(): void {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/account');
        }

        $this->verifyCsrf('/account');

        $validator = new Validator($_POST);
        $validator->required('current_password')
                  ->required('new_password')
                  ->required('confirm_password')
                  ->min('new_password', 8);

        if (!$validator->isValid()) {
            Session::flash('error', 'Controleer de invoer. Wachtwoord moet minimaal 8 tekens zijn.');
            $this->redirect('/account');
        }

        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if ($newPassword !== $confirmPassword) {
            Session::flash('error', 'Nieuwe wachtwoorden komen niet overeen.');
            $this->redirect('/account');
        }

        try {
            $userModel = new User($this->pdo);
            $user = $userModel->getById(Session::get('user_id'));

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                Session::flash('error', 'Huidig wachtwoord is onjuist.');
                $this->redirect('/account');
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $userModel->updatePassword(Session::get('user_id'), $newHash);

            Session::flash('success', 'Wachtwoord gewijzigd.');
            $this->redirect('/account');
        } catch (Exception $e) {
            Session::flash('error', 'Er ging iets mis.');
            $this->redirect('/account');
        }
    }

    public function toggleTeamVisibility(): void {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/account/teams');
        }

        $teamId = (int)($_POST['team_id'] ?? 0);
        $isHidden = isset($_POST['is_hidden']) && $_POST['is_hidden'] == '1';

        if ($teamId <= 0) {
            $this->redirect('/account/teams');
        }

        $teamModel = new Team($this->pdo);
        
        // Verify membership
        if (!$teamModel->isMember($teamId, Session::get('user_id'))) {
            Session::flash('error', 'Je bent geen lid van dit team.');
            $this->redirect('/account/teams');
        }

        $teamModel->setTeamVisibility($teamId, Session::get('user_id'), $isHidden);
        
        $message = $isHidden ? 'Team verborgen op dashboard.' : 'Team weer zichtbaar op dashboard.';
        Session::flash('success', $message);
        $this->redirect('/account/teams');
    }
}
