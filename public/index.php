<?php
declare(strict_types=1);

/**
 * Dit is het centrale toegangspunt (front controller) voor de applicatie.
 */

// Bepaal de opgevraagde URI
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

session_start();

// Simpele router
switch ($path) {
    case '/':
        // Als ingelogd, toon dashboard
        if (isset($_SESSION['user_id'])) {
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Team.php';
            
            $db = (new Database())->getConnection();
            $teamModel = new Team($db);
            
            $teams = $teamModel->getTeamsForUser($_SESSION['user_id']);
            
            require __DIR__ . '/../src/views/dashboard.php';
        } else {
            require __DIR__ . '/../src/views/home.php';
        }
        break;
        
    case '/team/create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Team.php';
            
            $name = $_POST['name'] ?? '';
            if (!empty($name)) {
                $db = (new Database())->getConnection();
                $teamModel = new Team($db);
                $teamModel->create($name, $_SESSION['user_id']);
            }
            header('Location: /');
            exit;
        }
        header('Location: /');
        exit;
        break;

    case '/team/select':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            $teamId = (int)($_POST['team_id'] ?? 0);
            
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Team.php';
            
            $db = (new Database())->getConnection();
            $teamModel = new Team($db);
            
            // Verifieer dat de user lid is van dit team
            if ($teamModel->isMember($teamId, $_SESSION['user_id'])) {
                $_SESSION['current_team'] = [
                    'id' => $teamId,
                    'name' => $_POST['team_name'],
                    'role' => $_POST['team_role'],
                    'invite_code' => $_POST['team_invite_code']
                ];
            }
        }
        header('Location: /');
        exit;
        break;

    case '/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once __DIR__ . '/../src/Database.php';
            
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            try {
                $db = (new Database())->getConnection();
                $stmt = $db->prepare("SELECT id, name, password_hash FROM users WHERE username = :username");
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
        require __DIR__ . '/../src/views/login.php';
        break;

    case '/logout':
        session_destroy();
        header('Location: /');
        exit;

    default:
        http_response_code(404);
        require __DIR__ . '/../src/views/404.php';
        break;
}
