<?php
declare(strict_types=1);

class AuthController {
    public function __construct(private PDO $pdo) {}

    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                $error = "Ongeldige sessie. Probeer het opnieuw.";
            } else {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                
                try {
                    $stmt = $this->pdo->prepare("SELECT id, name, password_hash FROM users WHERE username = :username");
                    $stmt->execute([':username' => $username]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password_hash'])) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        header('Location: /');
                        exit;
                    } else {
                        $error = "Ongeldige gebruikersnaam of wachtwoord.";
                    }
                } catch (Exception $e) {
                    $error = "Er is een fout opgetreden.";
                }
            }
        }
        View::render('login', ['error' => $error ?? null, 'pageTitle' => 'Inloggen - Trainer Bobby']);
    }

    public function register(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                $error = "Ongeldige sessie. Probeer het opnieuw.";
            } else {
                $inviteCode = $_POST['invite_code'] ?? '';
                $name = $_POST['name'] ?? '';
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';

                try {
                    $teamModel = new Team($this->pdo);
                    $userModel = new User($this->pdo);

                    // 1. Check invite code
                    $team = $teamModel->getByInviteCode($inviteCode);
                    if (!$team) {
                        $error = "Ongeldige invite code.";
                    } 
                    // 2. Check of username al bestaat
                    elseif ($userModel->getByUsername($username)) {
                        $error = "Gebruikersnaam is al in gebruik.";
                    } 
                    else {
                        // 3. Maak user aan
                        $userId = $userModel->create($username, $password, $name);
                        
                        // 4. Voeg toe aan team
                        $teamModel->addMember($team['id'], $userId, 'coach');

                        // 5. Login en redirect
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_name'] = $name;
                        $_SESSION['current_team'] = [
                            'id' => $team['id'],
                            'name' => $team['name'],
                            'role' => 'coach',
                            'invite_code' => $team['invite_code']
                        ];
                        
                        header('Location: /');
                        exit;
                    }

                } catch (Exception $e) {
                    $error = "Er is een fout opgetreden: " . $e->getMessage();
                }
            }
        }
        View::render('register', ['error' => $error ?? null, 'pageTitle' => 'Registreren - Trainer Bobby']);
    }

    public function logout(): void {
        session_destroy();
        header('Location: /');
        exit;
    }
}
