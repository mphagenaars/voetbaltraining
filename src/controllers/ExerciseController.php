<?php
declare(strict_types=1);

class ExerciseController extends BaseController {
    public function index(): void {
        $this->requireAuth();
        
        $exerciseModel = new Exercise($this->pdo);
        $query = $_GET['q'] ?? null;
        $teamTask = $_GET['team_task'] ?? null;
        $trainingObjective = $_GET['training_objective'] ?? null;
        $footballAction = $_GET['football_action'] ?? null;

        if ($teamTask === '') $teamTask = null;
        if ($trainingObjective === '') $trainingObjective = null;
        if ($footballAction === '') $footballAction = null;

        // Pass null for teamId to search all exercises
        $exercises = $exerciseModel->search(null, $query, $teamTask, $trainingObjective, $footballAction);
        View::render('exercises/index', [
            'exercises' => $exercises, 
            'query' => $query, 
            'teamTask' => $teamTask,
            'trainingObjective' => $trainingObjective,
            'footballAction' => $footballAction,
            'pageTitle' => 'Oefenstof - Trainer Bobby'
        ]);
    }

    public function create(): void {
        $this->requireAuth();
        $this->verifyCsrf();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $variation = $_POST['variation'] ?? null;
            $teamTask = $_POST['team_task'] ?? null;
            $trainingObjective = isset($_POST['training_objective']) ? json_encode($_POST['training_objective']) : null;
            $footballAction = isset($_POST['football_action']) ? json_encode($_POST['football_action']) : null;
            $minPlayers = !empty($_POST['min_players']) ? (int)$_POST['min_players'] : null;
            $maxPlayers = !empty($_POST['max_players']) ? (int)$_POST['max_players'] : null;
            $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
            $fieldType = $_POST['field_type'] ?? 'portrait';
            
            $imagePath = null;

            $drawingData = $_POST['drawing_data'] ?? null;
            $drawingImage = $_POST['drawing_image'] ?? null;

            if ($imagePath === null && !empty($drawingImage)) {
                if (preg_match('/^data:image\/(\w+);base64,/', $drawingImage, $type)) {
                    $data = substr($drawingImage, strpos($drawingImage, ',') + 1);
                    $type = strtolower($type[1]); 
                    if (in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $data = base64_decode($data);
                        if ($data !== false) {
                            $filename = uniqid('drawing_') . '.' . $type;
                            $uploadDir = __DIR__ . '/../../public/uploads/';
                            if (file_put_contents($uploadDir . $filename, $data)) {
                                $imagePath = $filename;
                            }
                        }
                    }
                }
            }
            
            if (!empty($title)) {
                $exerciseModel = new Exercise($this->pdo);
                $teamId = $_SESSION['current_team']['id'] ?? null;
                $exerciseId = $exerciseModel->create($teamId, $title, $description, $teamTask, $trainingObjective, $footballAction, $minPlayers, $maxPlayers, $duration, $imagePath, $drawingData, $variation, $fieldType);
                header('Location: /exercises');
                exit;
            }
        }
        View::render('exercises/form', ['pageTitle' => 'Nieuwe Oefening - Trainer Bobby']);
    }

    public function edit(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        
        $exerciseModel = new Exercise($this->pdo);
        $id = (int)($_GET['id'] ?? 0);
        $exercise = $exerciseModel->getById($id);
        
        // Check if exercise exists and belongs to current team
        if (!$exercise || $exercise['team_id'] !== $_SESSION['current_team']['id']) {
            header('Location: /exercises');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                header('Location: /exercises');
                exit;
            }
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $variation = $_POST['variation'] ?? null;
            $teamTask = $_POST['team_task'] ?? null;
            $trainingObjective = isset($_POST['training_objective']) ? json_encode($_POST['training_objective']) : null;
            $footballAction = isset($_POST['football_action']) ? json_encode($_POST['football_action']) : null;
            $minPlayers = !empty($_POST['min_players']) ? (int)$_POST['min_players'] : null;
            $maxPlayers = !empty($_POST['max_players']) ? (int)$_POST['max_players'] : null;
            $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
            $fieldType = $_POST['field_type'] ?? 'portrait';
            
            $imagePath = null;

            $drawingData = $_POST['drawing_data'] ?? null;
            $drawingImage = $_POST['drawing_image'] ?? null;

            if ($imagePath === null && !empty($drawingImage)) {
                if (preg_match('/^data:image\/(\w+);base64,/', $drawingImage, $type)) {
                    $data = substr($drawingImage, strpos($drawingImage, ',') + 1);
                    $type = strtolower($type[1]); 
                    if (in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $data = base64_decode($data);
                        if ($data !== false) {
                            $filename = uniqid('drawing_') . '.' . $type;
                            $uploadDir = __DIR__ . '/../../public/uploads/';
                            if (file_put_contents($uploadDir . $filename, $data)) {
                                $imagePath = $filename;
                            }
                        }
                    }
                }
            }
            
            if (!empty($title)) {
                $exerciseModel->update($id, $title, $description, $teamTask, $trainingObjective, $footballAction, $minPlayers, $maxPlayers, $duration, $imagePath, $drawingData, $variation, $fieldType);
                header('Location: /exercises');
                exit;
            }
        }
        View::render('exercises/form', ['exercise' => $exercise, 'pageTitle' => 'Oefening Bewerken - Trainer Bobby']);
    }

    public function view(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        
        $exerciseModel = new Exercise($this->pdo);
        $id = (int)($_GET['id'] ?? 0);
        $exercise = $exerciseModel->getById($id);
        
        // Check if exercise exists and belongs to current team
        if (!$exercise || $exercise['team_id'] !== $_SESSION['current_team']['id']) {
            header('Location: /exercises');
            exit;
        }
        
        View::render('exercises/view', ['exercise' => $exercise, 'pageTitle' => $exercise['title'] . ' - Trainer Bobby']);
    }

    public function delete(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                header('Location: /exercises');
                exit;
            }
            $exerciseModel = new Exercise($this->pdo);
            $id = (int)($_POST['id'] ?? 0);
            $exercise = $exerciseModel->getById($id);
            
            if ($exercise && $exercise['team_id'] === $_SESSION['current_team']['id']) {
                $exerciseModel->delete($id);
            }
        }
        header('Location: /exercises');
        exit;
    }
}
