<?php
declare(strict_types=1);

class AccountController {
    public function __construct(private PDO $pdo) {}

    public function index(): void {
        // Check of gebruiker is ingelogd
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

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
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
            header('Location: /account?error=' . urlencode('Ongeldige sessie.'));
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            header('Location: /account?error=' . urlencode('Naam mag niet leeg zijn.'));
            exit;
        }

        try {
            $userModel = new User($this->pdo);
            $userModel->updateName($_SESSION['user_id'], $name);
            
            // Update sessie naam ook
            $_SESSION['user_name'] = $name;

            header('Location: /account?success=' . urlencode('Profiel bijgewerkt.'));
        } catch (Exception $e) {
            header('Location: /account?error=' . urlencode('Er ging iets mis.'));
        }
        exit;
    }

    public function updatePassword(): void {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
            header('Location: /account?error=' . urlencode('Ongeldige sessie.'));
            exit;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            header('Location: /account?error=' . urlencode('Vul alle velden in.'));
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            header('Location: /account?error=' . urlencode('Nieuwe wachtwoorden komen niet overeen.'));
            exit;
        }

        if (strlen($newPassword) < 8) {
            header('Location: /account?error=' . urlencode('Nieuw wachtwoord moet minimaal 8 tekens zijn.'));
            exit;
        }

        try {
            $userModel = new User($this->pdo);
            $user = $userModel->getById($_SESSION['user_id']);

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                header('Location: /account?error=' . urlencode('Huidig wachtwoord is onjuist.'));
                exit;
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $userModel->updatePassword($_SESSION['user_id'], $newHash);

            header('Location: /account?success=' . urlencode('Wachtwoord gewijzigd.'));
        } catch (Exception $e) {
            header('Location: /account?error=' . urlencode('Er ging iets mis.'));
        }
        exit;
    }
}
