<?php
declare(strict_types=1);

/**
 * Dit is het centrale toegangspunt (front controller) voor de applicatie.
 */

// Bepaal de opgevraagde URI
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; frame-src https://www.youtube.com");
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

// Load helper functions
require_once __DIR__ . '/../src/functions.php';

// Start Session
require_once __DIR__ . '/../src/Session.php';
Session::start();

// Autoloader
spl_autoload_register(function ($class) {
    // Convert namespace separators to directory separators if needed
    $class = str_replace('\\', '/', $class);
    
    // Check src/ directory (e.g. Database)
    if (file_exists(__DIR__ . '/../src/' . $class . '.php')) {
        require_once __DIR__ . '/../src/' . $class . '.php';
        return;
    }
    
    // Check src/models/ directory
    if (file_exists(__DIR__ . '/../src/models/' . $class . '.php')) {
        require_once __DIR__ . '/../src/models/' . $class . '.php';
        return;
    }

    // Check src/controllers/ directory
    if (file_exists(__DIR__ . '/../src/controllers/' . $class . '.php')) {
        require_once __DIR__ . '/../src/controllers/' . $class . '.php';
        return;
    }

    // Check src/services/ directory
    if (file_exists(__DIR__ . '/../src/services/' . $class . '.php')) {
        require_once __DIR__ . '/../src/services/' . $class . '.php';
        return;
    }
});

// Initialize Database Connection
try {
    $db = (new Database())->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Auto-login via Remember Me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $parts = explode(':', $_COOKIE['remember_me']);
    if (count($parts) === 2) {
        $selector = $parts[0];
        $validator = $parts[1];
        
        $userModel = new User($db);
        $token = $userModel->findTokenBySelector($selector);
        
        if ($token && hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
            $user = $userModel->getById((int)$token['user_id']);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['is_admin'] = (bool)($user['is_admin'] ?? false);

                // Restore a valid team context as well, so API calls that require
                // current_team keep working after remember-me auto login.
                if (!isset($_SESSION['current_team'])) {
                    $teamModel = new Team($db);
                    $teams = $teamModel->getTeamsForUser((int)$user['id']);
                    if (!empty($teams)) {
                        $selectedTeam = null;
                        foreach ($teams as $teamCandidate) {
                            if (empty($teamCandidate['is_hidden'])) {
                                $selectedTeam = $teamCandidate;
                                break;
                            }
                        }

                        if (!$selectedTeam) {
                            $selectedTeam = $teams[0];
                        }

                        $role = Team::resolveMemberRole($selectedTeam);
                        $_SESSION['current_team'] = [
                            'id' => (int)($selectedTeam['id'] ?? 0),
                            'name' => (string)($selectedTeam['name'] ?? ''),
                            'role' => $role,
                            'invite_code' => (string)($selectedTeam['invite_code'] ?? '')
                        ];
                    }
                }
            }
        }
    }
}

// Load routes
$routes = require __DIR__ . '/../src/routes.php';

if (isset($routes[$path])) {
    [$controllerName, $method] = $routes[$path];
    (new $controllerName($db))->$method();
} else {
    http_response_code(404);
    View::render('404', ['pageTitle' => '404 - Niet gevonden']);
}
