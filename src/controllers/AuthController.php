<?php
declare(strict_types=1);

class AuthController extends BaseController {

    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // DEBUGGING LOGIN REDIRECT
            error_log("Login POST request received.");
            error_log("GET params: " . print_r($_GET, true));

            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                $error = "Ongeldige sessie. Probeer het opnieuw.";
            } else {
                $validator = new Validator($_POST);
                $validator->required('username', 'password');

                if (!$validator->isValid()) {
                    $error = $validator->getFirstError();
                } else {
                    $username = $_POST['username'];
                    $password = $_POST['password'];
                    
                    try {
                        $stmt = $this->pdo->prepare("SELECT id, name, password_hash, is_admin FROM users WHERE username = :username COLLATE NOCASE");
                        $stmt->execute([':username' => $username]);
                        $user = $stmt->fetch();
                        
                        if ($user && password_verify($password, $user['password_hash'])) {
                            Session::set('user_id', $user['id']);
                            Session::set('user_name', $user['name']);
                            Session::set('is_admin', (bool)($user['is_admin'] ?? false));

                            // Auto-select team
                            $teamModel = new Team($this->pdo);
                            $teams = $teamModel->getTeamsForUser((int)$user['id']);

                            if (!empty($teams)) {
                                $team = $teams[0];
                                $role = 'player';
                                if ($team['is_coach']) {
                                    $role = 'coach';
                                } elseif ($team['is_trainer']) {
                                    $role = 'trainer';
                                }

                                Session::set('current_team', [
                                    'id' => $team['id'],
                                    'name' => $team['name'],
                                    'role' => $role,
                                    'invite_code' => $team['invite_code']
                                ]);
                            }
                            
                            // Debug logging
                            error_log("Login successful. GET params: " . print_r($_GET, true));

                            // Log login
                            $logModel = new ActivityLog($this->pdo);
                            $logModel->log((int)$user['id'], 'login');

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

                            // Check for redirect param
                            $redirect = $_GET['redirect'] ?? '/';
                            error_log("Raw redirect param: " . $redirect);
                            
                            // Basic security check: ensure it starts with / and not // (to prevent open redirects)
                            if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
                                error_log("Redirect validation failed or default used. Redirecting to /");
                                $redirect = '/';
                            } else {
                                error_log("Redirect validation passed. Redirecting to: " . $redirect);
                            }

                            $this->redirect($redirect);
                        } else {
                            $error = "Ongeldige gebruikersnaam of wachtwoord.";
                        }
                    } catch (Exception $e) {
                        $error = "Er is een fout opgetreden.";
                    }
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
                $validator = new Validator($_POST);
                $validator->required('invite_code', 'name', 'username', 'password')
                          ->min('password', 8);

                if (!$validator->isValid()) {
                    $error = $validator->getFirstError();
                } else {
                    $inviteCode = $_POST['invite_code'];
                    $name = $_POST['name'];
                    $username = $_POST['username'];
                    $password = $_POST['password'];

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
                            Session::set('user_id', $userId);
                            Session::set('user_name', $name);
                            Session::set('current_team', [
                                'id' => $team['id'],
                                'name' => $team['name'],
                                'role' => 'coach',
                                'invite_code' => $team['invite_code']
                            ]);
                            
                            $this->redirect('/');
                        }

                    } catch (Exception $e) {
                        $error = "Er is een fout opgetreden: " . $e->getMessage();
                    }
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

        Session::destroy();
        $this->redirect('/');
    }
}
