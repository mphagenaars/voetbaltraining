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

    case '/exercises':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/models/Exercise.php';
        require_once __DIR__ . '/../src/models/Tag.php';
        
        $db = (new Database())->getConnection();
        $exerciseModel = new Exercise($db);
        $tagModel = new Tag($db);
        
        $query = $_GET['q'] ?? null;
        $tagFilter = !empty($_GET['tag']) ? (int)$_GET['tag'] : null;
        
        $exercises = $exerciseModel->search($_SESSION['current_team']['id'], $query, $tagFilter);
        $allTags = $tagModel->getAllForTeam($_SESSION['current_team']['id']);
        
        // Attach tags to exercises
        foreach ($exercises as &$exercise) {
            $exercise['tags'] = $tagModel->getTagsForExercise($exercise['id']);
        }
        unset($exercise); // Break reference
        
        require __DIR__ . '/../src/views/exercises/index.php';
        break;

    case '/exercises/create':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Exercise.php';
            require_once __DIR__ . '/../src/models/Tag.php';
            
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $players = !empty($_POST['players']) ? (int)$_POST['players'] : null;
            $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
            $requirements = $_POST['requirements'] ?? '';
            $tagsInput = $_POST['tags'] ?? '';
            
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (in_array($_FILES['image']['type'], $allowedTypes)) {
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid('exercise_') . '.' . $ext;
                    $uploadDir = __DIR__ . '/uploads/';
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                        $imagePath = $filename;
                    }
                }
            }
            
            if (!empty($title)) {
                $db = (new Database())->getConnection();
                $exerciseModel = new Exercise($db);
                $tagModel = new Tag($db);
                
                $exerciseId = $exerciseModel->create($_SESSION['current_team']['id'], $title, $description, $players, $duration, $requirements, $imagePath);
                
                // Process tags
                if (!empty($tagsInput)) {
                    $tags = explode(',', $tagsInput);
                    foreach ($tags as $tagName) {
                        $tagName = trim($tagName);
                        if (!empty($tagName)) {
                            $tagId = $tagModel->create($_SESSION['current_team']['id'], $tagName);
                            $tagModel->addTagToExercise($exerciseId, $tagId);
                        }
                    }
                }
                
                header('Location: /exercises');
                exit;
            }
        }
        require __DIR__ . '/../src/views/exercises/form.php';
        break;

    case '/exercises/edit':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/models/Exercise.php';
        require_once __DIR__ . '/../src/models/Tag.php';
        
        $db = (new Database())->getConnection();
        $exerciseModel = new Exercise($db);
        $tagModel = new Tag($db);
        
        $id = (int)($_GET['id'] ?? 0);
        $exercise = $exerciseModel->getById($id);
        
        // Check if exercise exists and belongs to current team
        if (!$exercise || $exercise['team_id'] !== $_SESSION['current_team']['id']) {
            header('Location: /exercises');
            exit;
        }
        
        // Get current tags
        $currentTags = $tagModel->getTagsForExercise($id);
        $exercise['tags'] = $currentTags;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $players = !empty($_POST['players']) ? (int)$_POST['players'] : null;
            $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
            $requirements = $_POST['requirements'] ?? '';
            $tagsInput = $_POST['tags'] ?? '';
            
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (in_array($_FILES['image']['type'], $allowedTypes)) {
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid('exercise_') . '.' . $ext;
                    $uploadDir = __DIR__ . '/uploads/';
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                        $imagePath = $filename;
                    }
                }
            }
            
            if (!empty($title)) {
                $exerciseModel->update($id, $title, $description, $players, $duration, $requirements, $imagePath);
                
                // Update tags: remove all and re-add
                $tagModel->removeAllTagsFromExercise($id);
                
                if (!empty($tagsInput)) {
                    $tags = explode(',', $tagsInput);
                    foreach ($tags as $tagName) {
                        $tagName = trim($tagName);
                        if (!empty($tagName)) {
                            $tagId = $tagModel->create($_SESSION['current_team']['id'], $tagName);
                            $tagModel->addTagToExercise($id, $tagId);
                        }
                    }
                }
                
                header('Location: /exercises');
                exit;
            }
        }
        require __DIR__ . '/../src/views/exercises/form.php';
        break;

    case '/exercises/delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Exercise.php';
            
            $db = (new Database())->getConnection();
            $exerciseModel = new Exercise($db);
            
            $id = (int)($_POST['id'] ?? 0);
            $exercise = $exerciseModel->getById($id);
            
            if ($exercise && $exercise['team_id'] === $_SESSION['current_team']['id']) {
                $exerciseModel->delete($id);
            }
        }
        header('Location: /exercises');
        exit;
        break;

    case '/trainings':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/models/Training.php';
        
        $db = (new Database())->getConnection();
        $trainingModel = new Training($db);
        
        $trainings = $trainingModel->getAllForTeam($_SESSION['current_team']['id']);
        
        require __DIR__ . '/../src/views/trainings/index.php';
        break;

    case '/trainings/create':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/models/Exercise.php';
        require_once __DIR__ . '/../src/models/Training.php';
        
        $db = (new Database())->getConnection();
        $exerciseModel = new Exercise($db);
        $trainingModel = new Training($db);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $selectedExercises = $_POST['exercises'] ?? []; // Array of exercise IDs
            $durations = $_POST['durations'] ?? []; // Array of durations keyed by exercise ID (or index)
            
            if (!empty($title)) {
                $trainingId = $trainingModel->create($_SESSION['current_team']['id'], $title, $description);
                
                // Add exercises
                if (is_array($selectedExercises)) {
                    foreach ($selectedExercises as $index => $exerciseId) {
                        $duration = !empty($durations[$index]) ? (int)$durations[$index] : null;
                        $trainingModel->addExercise($trainingId, (int)$exerciseId, $index, $duration);
                    }
                }
                
                header('Location: /trainings');
                exit;
            }
        }
        
        // Get all exercises to select from
        $allExercises = $exerciseModel->getAllForTeam($_SESSION['current_team']['id']);
        
        require __DIR__ . '/../src/views/trainings/form.php';
        break;

    case '/trainings/view':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/models/Training.php';
        
        $db = (new Database())->getConnection();
        $trainingModel = new Training($db);
        
        $id = (int)($_GET['id'] ?? 0);
        $training = $trainingModel->getById($id);
        
        if (!$training || $training['team_id'] !== $_SESSION['current_team']['id']) {
            header('Location: /trainings');
            exit;
        }
        
        require __DIR__ . '/../src/views/trainings/view.php';
        break;

    case '/trainings/delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Training.php';
            
            $db = (new Database())->getConnection();
            $trainingModel = new Training($db);
            
            $id = (int)($_POST['id'] ?? 0);
            $training = $trainingModel->getById($id);
            
            if ($training && $training['team_id'] === $_SESSION['current_team']['id']) {
                $trainingModel->delete($id);
            }
        }
        header('Location: /trainings');
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

    case '/register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Team.php';
            require_once __DIR__ . '/../src/models/User.php';

            $inviteCode = $_POST['invite_code'] ?? '';
            $name = $_POST['name'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            try {
                $db = (new Database())->getConnection();
                $teamModel = new Team($db);
                $userModel = new User($db);

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
        require __DIR__ . '/../src/views/register.php';
        break;

    // --- PLAYERS ROUTES ---
    case '/players':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/models/Player.php';
        
        $db = (new Database())->getConnection();
        $playerModel = new Player($db);
        
        $players = $playerModel->getAllForTeam($_SESSION['current_team']['id']);
        
        require __DIR__ . '/../src/views/players/index.php';
        break;

    case '/players/create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Player.php';
            
            $name = trim($_POST['name'] ?? '');
            if (!empty($name)) {
                $db = (new Database())->getConnection();
                $playerModel = new Player($db);
                $playerModel->create($_SESSION['current_team']['id'], $name);
            }
        }
        header('Location: /players');
        exit;
        break;

    case '/players/delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Player.php';
            
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db = (new Database())->getConnection();
                $playerModel = new Player($db);
                // TODO: Check if player belongs to current team
                $playerModel->delete($id);
            }
        }
        header('Location: /players');
        exit;
        break;

    case '/players/edit':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /players');
            exit;
        }
        
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/models/Player.php';
        
        $db = (new Database())->getConnection();
        $playerModel = new Player($db);
        $player = $playerModel->getById($id);
        
        // Verify ownership (team check)
        if (!$player || $player['team_id'] !== $_SESSION['current_team']['id']) {
            header('Location: /players');
            exit;
        }
        
        require __DIR__ . '/../src/views/players/edit.php';
        break;

    case '/players/update':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Player.php';
            
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            
            if ($id > 0 && !empty($name)) {
                $db = (new Database())->getConnection();
                $playerModel = new Player($db);
                
                // Verify ownership
                $player = $playerModel->getById($id);
                if ($player && $player['team_id'] === $_SESSION['current_team']['id']) {
                    $playerModel->update($id, $name);
                }
            }
        }
        header('Location: /players');
        exit;
        break;

    // --- LINEUPS ROUTES ---
    case '/lineups':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/models/Lineup.php';
        
        $db = (new Database())->getConnection();
        $lineupModel = new Lineup($db);
        
        $lineups = $lineupModel->getAllForTeam($_SESSION['current_team']['id']);
        
        require __DIR__ . '/../src/views/lineups/index.php';
        break;

    case '/lineups/create':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Lineup.php';
            
            $name = trim($_POST['name'] ?? '');
            $formation = trim($_POST['formation'] ?? '4-3-3');
            
            if (!empty($name)) {
                $db = (new Database())->getConnection();
                $lineupModel = new Lineup($db);
                $lineupId = $lineupModel->create($_SESSION['current_team']['id'], $name, $formation);
                header('Location: /lineups/view?id=' . $lineupId);
                exit;
            }
        }
        
        require __DIR__ . '/../src/views/lineups/create.php';
        break;

    case '/lineups/view':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /lineups');
            exit;
        }
        
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/models/Lineup.php';
        require_once __DIR__ . '/../src/models/Player.php';
        
        $db = (new Database())->getConnection();
        $lineupModel = new Lineup($db);
        $playerModel = new Player($db);
        
        $lineup = $lineupModel->getById($id);
        if (!$lineup || $lineup['team_id'] !== $_SESSION['current_team']['id']) {
            header('Location: /lineups');
            exit;
        }
        
        $players = $playerModel->getAllForTeam($_SESSION['current_team']['id']);
        $positions = $lineupModel->getPositions($id);
        
        require __DIR__ . '/../src/views/lineups/view.php';
        break;

    case '/lineups/save':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if ($input && isset($input['lineup_id']) && isset($input['positions'])) {
                require_once __DIR__ . '/../src/Database.php';
                require_once __DIR__ . '/../src/models/Lineup.php';
                
                $db = (new Database())->getConnection();
                $lineupModel = new Lineup($db);
                
                // Verify ownership
                $lineup = $lineupModel->getById((int)$input['lineup_id']);
                if ($lineup && $lineup['team_id'] === $_SESSION['current_team']['id']) {
                    $lineupModel->savePositions((int)$input['lineup_id'], $input['positions']);
                    echo json_encode(['success' => true]);
                    exit;
                }
            }
        }
        http_response_code(400);
        echo json_encode(['success' => false]);
        exit;
        break;

    case '/lineups/delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            require_once __DIR__ . '/../src/Database.php';
            require_once __DIR__ . '/../src/models/Lineup.php';
            
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db = (new Database())->getConnection();
                $lineupModel = new Lineup($db);
                // Verify ownership
                $lineup = $lineupModel->getById($id);
                if ($lineup && $lineup['team_id'] === $_SESSION['current_team']['id']) {
                    $lineupModel->delete($id);
                }
            }
        }
        header('Location: /lineups');
        exit;
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
