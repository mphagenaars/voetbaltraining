<?php
declare(strict_types=1);

class AccountController extends BaseController {

    public function index(): void {
        // Check of gebruiker is ingelogd
        $this->requireAuth();

        $userId = $_SESSION['user_id'];
        $userModel = new User($this->pdo);
        $teamModel = new Team($this->pdo);

        $user = $userModel->getById($userId);
        $teams = $teamModel->getTeamsForUser($userId);

        $success = $_GET['success'] ?? null;
        $error = $_GET['error'] ?? null;

        View::render('account/index', [
            'user' => $user,
            'teams' => $teams,
            'success' => $success,
            'error' => $error,
            'pageTitle' => 'Mijn Account - Trainer Bobby'
        ]);
    }

    public function updateProfile(): void {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/account');
        }

        $this->verifyCsrf('/account?error=' . urlencode('Ongeldige sessie.'));

        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            $this->redirect('/account?error=' . urlencode('Naam mag niet leeg zijn.'));
        }

        try {
            $userModel = new User($this->pdo);
            $userModel->updateName($_SESSION['user_id'], $name);
            
            // Update sessie naam ook
            $_SESSION['user_name'] = $name;

            $this->redirect('/account?success=' . urlencode('Profiel bijgewerkt.'));
        } catch (Exception $e) {
            $this->redirect('/account?error=' . urlencode('Er ging iets mis.'));
        }
    }

    public function updatePassword(): void {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/account');
        }

        $this->verifyCsrf('/account?error=' . urlencode('Ongeldige sessie.'));

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $this->redirect('/account?error=' . urlencode('Vul alle velden in.'));
        }

        if ($newPassword !== $confirmPassword) {
            $this->redirect('/account?error=' . urlencode('Nieuwe wachtwoorden komen niet overeen.'));
        }

        if (strlen($newPassword) < 8) {
            $this->redirect('/account?error=' . urlencode('Nieuw wachtwoord moet minimaal 8 tekens zijn.'));
        }

        try {
            $userModel = new User($this->pdo);
            $user = $userModel->getById($_SESSION['user_id']);

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                $this->redirect('/account?error=' . urlencode('Huidig wachtwoord is onjuist.'));
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $userModel->updatePassword($_SESSION['user_id'], $newHash);

            $this->redirect('/account?success=' . urlencode('Wachtwoord gewijzigd.'));
        } catch (Exception $e) {
            $this->redirect('/account?error=' . urlencode('Er ging iets mis.'));
        }
    }
}
