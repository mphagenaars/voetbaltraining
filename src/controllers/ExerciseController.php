<?php
declare(strict_types=1);

class ExerciseController {
    public function __construct(private PDO $pdo) {}

    public function index(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        $exerciseModel = new Exercise($this->pdo);
        $query = $_GET['q'] ?? null;
        $exercises = $exerciseModel->search($_SESSION['current_team']['id'], $query);
        require __DIR__ . '/../views/exercises/index.php';
    }

    public function create(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $variation = $_POST['variation'] ?? null;
            $teamTask = $_POST['team_task'] ?? null;
            $trainingObjective = $_POST['training_objective'] ?? null;
            $footballAction = $_POST['football_action'] ?? null;
            $minPlayers = !empty($_POST['min_players']) ? (int)$_POST['min_players'] : null;
            $maxPlayers = !empty($_POST['max_players']) ? (int)$_POST['max_players'] : null;
            $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
            
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
                $exerciseId = $exerciseModel->create($_SESSION['current_team']['id'], $title, $description, $teamTask, $trainingObjective, $footballAction, $minPlayers, $maxPlayers, $duration, $imagePath, $drawingData, $variation);
                header('Location: /exercises');
                exit;
            }
        }
        require __DIR__ . '/../views/exercises/form.php';
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
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $variation = $_POST['variation'] ?? null;
            $teamTask = $_POST['team_task'] ?? null;
            $trainingObjective = $_POST['training_objective'] ?? null;
            $footballAction = $_POST['football_action'] ?? null;
            $minPlayers = !empty($_POST['min_players']) ? (int)$_POST['min_players'] : null;
            $maxPlayers = !empty($_POST['max_players']) ? (int)$_POST['max_players'] : null;
            $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
            
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
                $exerciseModel->update($id, $title, $description, $teamTask, $trainingObjective, $footballAction, $minPlayers, $maxPlayers, $duration, $imagePath, $drawingData, $variation);
                header('Location: /exercises');
                exit;
            }
        }
        require __DIR__ . '/../views/exercises/form.php';
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
        
        require __DIR__ . '/../views/exercises/view.php';
    }

    public function delete(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
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
