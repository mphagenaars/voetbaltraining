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
                    $stmt = $this->pdo->prepare("SELECT id, name, password_hash, is_admin FROM users WHERE username = :username");
                    $stmt->execute([':username' => $username]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password_hash'])) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['is_admin'] = (bool)($user['is_admin'] ?? false);

                        // Remember Me Logic
                        if (!empty($_POST['remember_me'])) {
                            $selector = bin2hex(random_bytes(16));
                            $validator = bin2hex(random_bytes(32));
                            $hashedValidator = hash('sha256', $validator);
                            $expiresAt = date('Y-m-d H:i:s', time() + 86400 * 30); // 30 days

                            $userModel = new User($this->pdo);
                            $userModel->createRememberToken((int)$user['id'], $selector, $hashedValidator, $expiresAt);

                            setcookie('remember_me', "$selector:$validator", [
                                'expires' => time() + 86400 * 30,
                                'path' => '/',
                                'httponly' => true,
                                'samesite' => 'Strict',
                                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
                            ]);
                        }

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
                        
                        // 4. Voeg toe aan team (als coach)
                        $teamModel->addMember($team['id'], $userId, true, false);

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
        // Remove remember me token
        if (isset($_COOKIE['remember_me'])) {
            $parts = explode(':', $_COOKIE['remember_me']);
            if (count($parts) === 2) {
                $selector = $parts[0];
                $userModel = new User($this->pdo);
                $userModel->removeTokenBySelector($selector);
            }
            setcookie('remember_me', '', [
                'expires' => time() - 3600, 
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }

        session_destroy();
        header('Location: /');
        exit;
    }
}
