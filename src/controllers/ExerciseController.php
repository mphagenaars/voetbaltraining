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
        
        $currentTeamId = Session::has('current_team') ? (int)Session::get('current_team')['id'] : null;

        // Get all teams where the user has edit rights (coach or trainer)
        $teamModel = new Team($this->pdo);
        $userTeams = $teamModel->getTeamsForUser(Session::get('user_id'));
        $editableTeamIds = [];
        foreach ($userTeams as $team) {
            if (!empty($team['is_coach']) || !empty($team['is_trainer'])) {
                $editableTeamIds[] = (int)$team['id'];
            }
        }

        View::render('exercises/index', [
            'exercises' => $exercises, 
            'query' => $query, 
            'teamTask' => $teamTask,
            'trainingObjective' => $trainingObjective,
            'footballAction' => $footballAction,
            'currentTeamId' => $currentTeamId,
            'editableTeamIds' => $editableTeamIds,
            'pageTitle' => 'Oefenstof - Trainer Bobby'
        ]);
    }

    public function create(): void {
        $this->requireAuth();
        $this->verifyCsrf();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validator = new Validator($_POST);
            $validator->required('title');

            if ($validator->isValid()) {
                $title = $_POST['title'];
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
                
                $exerciseModel = new Exercise($this->pdo);
                $teamId = Session::get('current_team')['id'] ?? null;
                $exerciseModel->create($teamId, $title, $description, $teamTask, $trainingObjective, $footballAction, $minPlayers, $maxPlayers, $duration, $imagePath, $drawingData, $variation, $fieldType);
                Session::flash('success', 'Oefening aangemaakt.');
                $this->redirect('/exercises');
            }
        }
        View::render('exercises/form', ['pageTitle' => 'Nieuwe Oefening - Trainer Bobby']);
    }

    public function edit(): void {
        if (!Session::has('user_id')) {
            $this->redirect('/');
        }
        
        $exerciseModel = new Exercise($this->pdo);
        $id = (int)($_GET['id'] ?? 0);
        $exercise = $exerciseModel->getById($id);
        
        // Check if exercise exists
        if (!$exercise) {
            $this->redirect('/exercises');
        }

        // Check permissions
        $teamModel = new Team($this->pdo);
        $roles = $teamModel->getMemberRoles((int)$exercise['team_id'], Session::get('user_id'));
        if (!$roles || (!$roles['is_coach'] && !$roles['is_trainer'])) {
             $this->redirect('/exercises');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/exercises');
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
            
            $validator = new Validator($_POST);
            $validator->required('title');

            if ($validator->isValid()) {
                $exerciseModel->update($id, $title, $description, $teamTask, $trainingObjective, $footballAction, $minPlayers, $maxPlayers, $duration, $imagePath, $drawingData, $variation, $fieldType);
                Session::flash('success', 'Oefening bijgewerkt.');
                $this->redirect('/exercises');
            }
        }
        View::render('exercises/form', ['exercise' => $exercise, 'pageTitle' => 'Oefening Bewerken - Trainer Bobby']);
    }

    public function view(): void {
        if (!Session::has('user_id')) {
            $this->redirect('/');
        }
        
        $exerciseModel = new Exercise($this->pdo);
        $id = (int)($_GET['id'] ?? 0);
        $exercise = $exerciseModel->getById($id);
        
        if (!$exercise) {
            $this->redirect('/exercises');
        }

        $currentTeamId = Session::has('current_team') ? (int)Session::get('current_team')['id'] : null;
        $exerciseTeamId = isset($exercise['team_id']) ? (int)$exercise['team_id'] : null;
        
        $canEdit = false;
        if ($exerciseTeamId !== null) {
             $teamModel = new Team($this->pdo);
             $roles = $teamModel->getMemberRoles($exerciseTeamId, Session::get('user_id'));
             if ($roles && ($roles['is_coach'] || $roles['is_trainer'])) {
                 $canEdit = true;
             }
        }
        
        View::render('exercises/view', [
            'exercise' => $exercise, 
            'pageTitle' => $exercise['title'] . ' - Trainer Bobby',
            'canEdit' => $canEdit
        ]);
    }

    public function delete(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && Session::has('user_id')) {
            $this->verifyCsrf('/exercises');
            $exerciseModel = new Exercise($this->pdo);
            $id = (int)($_POST['id'] ?? 0);
            $exercise = $exerciseModel->getById($id);
            
            if ($exercise) {
                $teamModel = new Team($this->pdo);
                $roles = $teamModel->getMemberRoles((int)$exercise['team_id'], Session::get('user_id'));
                if ($roles && ($roles['is_coach'] || $roles['is_trainer'])) {
                    $exerciseModel->delete($id);
                    Session::flash('success', 'Oefening verwijderd.');
                }
            }
        }
        $this->redirect('/exercises');
    }
}
