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

    // --- LINEUPS ROUTES ---
    case '/lineups':
        (new LineupController($db))->index();
        break;

    case '/lineups/create':
        (new LineupController($db))->create();
        break;

    case '/lineups/view':
        (new LineupController($db))->view();
        break;

    case '/lineups/save':
        (new LineupController($db))->save();
        break;

    case '/lineups/delete':
        (new LineupController($db))->delete();
        break;

    case '/logout':
        (new AuthController($db))->logout();
        break;

    default:
        http_response_code(404);
        View::render('404', ['pageTitle' => '404 - Niet gevonden']);
        break;
}
