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
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

session_start();

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
            }
        }
    }
}

// Simpele router
switch ($path) {
    case '/':
        (new DashboardController($db))->index();
        break;
        
    case '/team/create':
        (new TeamController($db))->create();
        break;

    case '/team/select':
        (new TeamController($db))->select();
        break;

    case '/exercises':
        (new ExerciseController($db))->index();
        break;

    case '/exercises/create':
        (new ExerciseController($db))->create();
        break;

    case '/exercises/edit':
        (new ExerciseController($db))->edit();
        break;

    case '/exercises/view':
        (new ExerciseController($db))->view();
        break;

    case '/exercises/delete':
        (new ExerciseController($db))->delete();
        break;

    case '/trainings':
        (new TrainingController($db))->index();
        break;

    case '/trainings/create':
        (new TrainingController($db))->create();
        break;

    case '/trainings/edit':
        (new TrainingController($db))->edit();
        break;

    case '/trainings/view':
        (new TrainingController($db))->view();
        break;

    case '/trainings/delete':
        (new TrainingController($db))->delete();
        break;

    case '/login':
        (new AuthController($db))->login();
        break;

    case '/register':
        (new AuthController($db))->register();
        break;

    // --- PLAYERS ROUTES ---
    case '/players':
        (new PlayerController($db))->index();
        break;

    case '/players/create':
        (new PlayerController($db))->create();
        break;

    case '/players/delete':
        (new PlayerController($db))->delete();
        break;

    case '/players/edit':
        (new PlayerController($db))->edit();
        break;

    case '/players/update':
        (new PlayerController($db))->update();
        break;

    // --- MATCHES ROUTES ---
    case '/matches':
        (new GameController($db))->index();
        break;

    case '/matches/create':
        (new GameController($db))->create();
        break;

    case '/matches/view':
        (new GameController($db))->view();
        break;

    case '/matches/add-event':
        (new GameController($db))->addEvent();
        break;

    case '/matches/update-score':
        (new GameController($db))->updateScore();
        break;

    case '/matches/update-evaluation':
        (new GameController($db))->updateEvaluation();
        break;

    case '/matches/save-lineup':
        (new GameController($db))->saveLineup();
        break;

    case '/logout':
        (new AuthController($db))->logout();
        break;

    // --- ACCOUNT ROUTES ---
    case '/account':
        (new AccountController($db))->index();
        break;

    case '/account/update-profile':
        (new AccountController($db))->updateProfile();
        break;

    case '/account/update-password':
        (new AccountController($db))->updatePassword();
        break;

    // --- ADMIN ROUTES ---
    case '/admin':
        (new AdminController($db))->index();
        break;

    case '/admin/delete-user':
        (new AdminController($db))->deleteUser();
        break;

    case '/admin/toggle-admin':
        (new AdminController($db))->toggleAdmin();
        break;

    case '/admin/user-teams':
        (new AdminController($db))->manageTeams();
        break;

    case '/admin/add-team-member':
        (new AdminController($db))->addTeamMember();
        break;

    case '/admin/update-team-role':
        (new AdminController($db))->updateTeamRole();
        break;

    case '/admin/remove-team-member':
        (new AdminController($db))->removeTeamMember();
        break;

    default:
        http_response_code(404);
        View::render('404', ['pageTitle' => '404 - Niet gevonden']);
        break;
}
