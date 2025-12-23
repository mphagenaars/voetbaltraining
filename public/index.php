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
        // Als ingelogd, toon dashboard (nog te maken), anders home
        if (isset($_SESSION['user_id'])) {
            echo "Welkom " . htmlspecialchars($_SESSION['user_name']) . "! <a href='/logout'>Uitloggen</a>";
            // require __DIR__ . '/../src/views/dashboard.php'; // Later toevoegen
        } else {
            require __DIR__ . '/../src/views/home.php';
        }
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
