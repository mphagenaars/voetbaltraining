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
        $userTeams = $teamModel->getTeamsForUser((int)Session::get('user_id'));
        $editableTeamIds = [];
        foreach ($userTeams as $team) {
            if (Team::hasStaffPrivileges($team)) {
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
                $payload = $this->getExercisePayload($_POST);
                $imagePath = null;

                if (!empty($_POST['drawing_image'])) {
                    $imagePath = $this->handleDrawingImage($_POST['drawing_image']);
                }
                
                $exerciseModel = new Exercise($this->pdo);
                
                // Fix: Veilig ophalen van team ID. Als er geen team geselecteerd is (null), 
                // wordt de oefening aangemaakt zonder team (globaal/persoonlijk).
                $teamData = Session::get('current_team');
                $teamId = isset($teamData['id']) ? (int)$teamData['id'] : null;

                $userId = Session::get('user_id');
                $createdBy = ($userId && (int)$userId > 0) ? (int)$userId : null;

                $exerciseId = $exerciseModel->create(
                    $teamId, 
                    $payload['title'], 
                    $payload['description'], 
                    $payload['team_task'], 
                    $payload['training_objective'], 
                    $payload['football_action'], 
                    $payload['min_players'], 
                    $payload['max_players'], 
                    $payload['duration'], 
                    $imagePath, 
                    $payload['drawing_data'], 
                    $payload['variation'], 
                    $payload['field_type'],
                    $createdBy,
                    $payload['source'],
                    $payload['coach_instructions']
                );
                
                // Log activity
                $this->logActivity('create_exercise', $exerciseId, $payload['title']);

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
        $createdBy = isset($exercise['created_by']) ? (int)$exercise['created_by'] : null;
        if (!$this->canEditExercise($createdBy, (int)Session::get('user_id'))) {
             $this->redirect('/exercises');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/exercises');
            
            $validator = new Validator($_POST);
            $validator->required('title');

            if ($validator->isValid()) {
                $payload = $this->getExercisePayload($_POST);
                $imagePath = null;

                if (!empty($_POST['drawing_image'])) {
                    $imagePath = $this->handleDrawingImage($_POST['drawing_image']);
                }
                
                $exerciseModel->update(
                    $id, 
                    $payload['title'], 
                    $payload['description'], 
                    $payload['team_task'], 
                    $payload['training_objective'], 
                    $payload['football_action'], 
                    $payload['min_players'], 
                    $payload['max_players'], 
                    $payload['duration'], 
                    $imagePath, 
                    $payload['drawing_data'], 
                    $payload['variation'], 
                    $payload['field_type'],
                    $payload['source'],
                    $payload['coach_instructions']
                );
                
                // Log activity
                $this->logActivity('edit_exercise', $id, $payload['title']);

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

        // Log view
        $this->logActivity('view_exercise', $id, $exercise['title']);

        $createdBy = isset($exercise['created_by']) ? (int)$exercise['created_by'] : null;
        
        $canEdit = $this->canEditExercise($createdBy, (int)Session::get('user_id'));

        // Fetch Feedback
        $commentModel = new Comment($this->pdo);
        $comments = $commentModel->getForExercise($id);
        
        $reactionModel = new Reaction($this->pdo);
        $reactionCounts = $reactionModel->getCounts($id);
        // Ensure user is logged in for userReaction check, though view expects logged in user based on strict mode? 
        // Actually view() has `if (!Session::has('user_id')) { $this->redirect('/'); }` at top.
        $userReaction = $reactionModel->getUserReaction($id, (int)Session::get('user_id'));

        $teamModel = new Team($this->pdo);
        $trainingModel = new Training($this->pdo);
        $userTeams = $teamModel->getTeamsForUser((int)Session::get('user_id'));
        $userTeamIds = [];
        foreach ($userTeams as $team) {
            $userTeamIds[] = (int)$team['id'];
        }

        $requestedFromTrainingId = (int)($_GET['from_training'] ?? 0);
        $fromTrainingId = 0;
        $backUrl = '/exercises';
        if ($requestedFromTrainingId > 0) {
            $fromTrainingTeamId = $trainingModel->getTeamId($requestedFromTrainingId);
            if ($fromTrainingTeamId !== null) {
                if ((bool)Session::get('is_admin') || in_array($fromTrainingTeamId, $userTeamIds, true)) {
                    if ($trainingModel->hasExercise($requestedFromTrainingId, $id)) {
                        $fromTrainingId = $requestedFromTrainingId;
                        $backUrl = '/trainings/view?id=' . $fromTrainingId . '&team_id=' . $fromTrainingTeamId;
                    }
                }
            }
        }
        
        $selectableTrainings = [];
        $canAddToTraining = (bool)Session::get('is_admin');

        foreach ($userTeams as $team) {
            // Check if user is coach or trainer
            $isStaff = Team::hasStaffPrivileges($team);
            
            if ($isStaff || $canAddToTraining) {
                if ($isStaff) $canAddToTraining = true;

                // Fetch upcoming trainings for this team
                $upcoming = $trainingModel->getTrainings((int)$team['id'], 'training_date ASC', true);
                if (!empty($upcoming)) {
                    $selectableTrainings[$team['name']] = $upcoming;
                }
            }
        }

        View::render('exercises/view', [
            'exercise' => $exercise, 
            'pageTitle' => $exercise['title'] . ' - Trainer Bobby',
            'canEdit' => $canEdit,
            'comments' => $comments,
            'reactionCounts' => $reactionCounts,
            'userReaction' => $userReaction,
            'selectableTrainings' => $selectableTrainings,
            'canAddToTraining' => $canAddToTraining,
            'backUrl' => $backUrl,
            'fromTrainingId' => $fromTrainingId
        ]);
    }

    public function delete(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && Session::has('user_id')) {
            $this->verifyCsrf('/exercises');
            $exerciseModel = new Exercise($this->pdo);
            $id = (int)($_POST['id'] ?? 0);
            $exercise = $exerciseModel->getById($id);
            
            if ($exercise) {
                $createdBy = isset($exercise['created_by']) ? (int)$exercise['created_by'] : null;
                if ($this->canEditExercise($createdBy, (int)Session::get('user_id'))) {
                    $exerciseModel->delete($id);
                    
                    // Log activity
                    $this->logActivity('delete_exercise', $id, $exercise['title']);

                    Session::flash('success', 'Oefening verwijderd.');
                }
            }
        }
        $this->redirect('/exercises');
    }

    private function getExercisePayload(array $postData): array {
        return [
            'title' => $postData['title'] ?? '',
            'description' => $postData['description'] ?? '',
            'variation' => $postData['variation'] ?? null,
            'source' => $postData['source'] ?? null,
            'coach_instructions' => $postData['coach_instructions'] ?? null,
            'team_task' => $postData['team_task'] ?? null,
            'training_objective' => (isset($postData['training_objective']) && ($json = json_encode($postData['training_objective'])) !== false) ? $json : null,
            'football_action' => (isset($postData['football_action']) && ($json = json_encode($postData['football_action'])) !== false) ? $json : null,
            'min_players' => !empty($postData['min_players']) ? (int)$postData['min_players'] : null,
            'max_players' => !empty($postData['max_players']) ? (int)$postData['max_players'] : null,
            'duration' => !empty($postData['duration']) ? (int)$postData['duration'] : null,
            'field_type' => $postData['field_type'] ?? 'portrait',
            'drawing_data' => $postData['drawing_data'] ?? null,
        ];
    }

    private function handleDrawingImage(string $drawingImage): ?string {
        if (preg_match('/^data:image\/(\w+);base64,/', $drawingImage, $type)) {
            $data = substr($drawingImage, strpos($drawingImage, ',') + 1);
            $type = strtolower($type[1]); 
            if (in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
                $data = base64_decode($data);
                if ($data !== false) {
                    $filename = uniqid('drawing_') . '.' . $type;
                    $uploadDir = __DIR__ . '/../../public/uploads/';
                    if (file_put_contents($uploadDir . $filename, $data)) {
                        return $filename;
                    }
                }
            }
        }
        return null;
    }

    private function canEditExercise(?int $createdBy, int $userId): bool {
        // Admins mogen altijd alles bewerken
        if (Session::get('is_admin')) {
            return true;
        }

        // Alleen de maker mag bewerken
        if ($createdBy !== null && $createdBy === $userId) {
            return true;
        }

        return false;
    }

    public function storeComment(): void {
        $this->requireAuth();
        $this->verifyCsrf();
        
        $exerciseId = (int)($_POST['exercise_id'] ?? 0);
        $fromTrainingId = (int)($_POST['from_training'] ?? 0);
        $commentText = trim($_POST['comment'] ?? '');
        
        if ($exerciseId > 0 && !empty($commentText)) {
            $commentModel = new Comment($this->pdo);
            $commentModel->create($exerciseId, (int)Session::get('user_id'), $commentText);
            Session::flash('success', 'Reactie geplaatst!');
        } else {
             Session::flash('error', 'Ongeldige reactie.');
        }

        $this->redirect($this->buildExerciseViewUrl($exerciseId, $fromTrainingId));
    }

    public function toggleReaction(): void {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             $this->verifyCsrf();
             
             $exerciseId = (int)($_POST['exercise_id'] ?? 0);
             $fromTrainingId = (int)($_POST['from_training'] ?? 0);
             $type = $_POST['type'] ?? '';
             
             if ($exerciseId > 0 && in_array($type, ['rock', 'middle_finger'])) {
                 $reactionModel = new Reaction($this->pdo);
                 $reactionModel->toggle($exerciseId, (int)Session::get('user_id'), $type);
             }
             
             $this->redirect($this->buildExerciseViewUrl($exerciseId, $fromTrainingId));
        }

        $exerciseId = (int)($_GET['id'] ?? 0);
        $fromTrainingId = (int)($_GET['from_training'] ?? 0);
        if ($exerciseId > 0) {
            $this->redirect($this->buildExerciseViewUrl($exerciseId, $fromTrainingId));
        }

        $this->redirect('/exercises');
    }

    public function addToTraining(): void {
        $this->requireAuth();
        $this->verifyCsrf();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $exerciseId = (int)$_POST['exercise_id'];
            $trainingId = (int)$_POST['training_id'];
            $fromTrainingId = (int)($_POST['from_training'] ?? 0);
            $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
            
            if ($exerciseId && $trainingId) {
                $exerciseModel = new Exercise($this->pdo);
                $trainingModel = new Training($this->pdo);
                $teamModel = new Team($this->pdo);

                $exercise = $exerciseModel->getById($exerciseId);
                if (!$exercise) {
                    Session::flash('error', 'Oefening niet gevonden.');
                    $this->redirect('/exercises');
                }

                $training = $trainingModel->getById($trainingId);
                if (!$training) {
                    Session::flash('error', 'Training niet gevonden.');
                    $this->redirect($this->buildExerciseViewUrl($exerciseId, $fromTrainingId));
                }

                $canEditTraining = $teamModel->canManageTeam(
                    (int)$training['team_id'],
                    (int)Session::get('user_id'),
                    (bool)Session::get('is_admin')
                );

                if (!$canEditTraining) {
                    Session::flash('error', 'Je hebt geen rechten om oefeningen aan deze training toe te voegen.');
                    $this->redirect($this->buildExerciseViewUrl($exerciseId, $fromTrainingId));
                }
                
                try {
                    $trainingModel->addExerciseAtEnd($trainingId, $exerciseId, $duration);
                } catch (PDOException $e) {
                    Session::flash('error', 'Oefening kon niet worden toegevoegd. Probeer het opnieuw.');
                    $this->redirect($this->buildExerciseViewUrl($exerciseId, $fromTrainingId));
                }
                
                Session::flash('success', 'Oefening toegevoegd aan training!');
                $this->redirect($this->buildExerciseViewUrl($exerciseId, $fromTrainingId));
            }

            Session::flash('error', 'Ongeldige oefening of training.');
        }
        $this->redirect('/exercises');
    }

    private function buildExerciseViewUrl(int $exerciseId, int $fromTrainingId = 0): string {
        $url = '/exercises/view?id=' . $exerciseId;
        if ($fromTrainingId > 0) {
            $url .= '&from_training=' . $fromTrainingId;
        }
        return $url;
    }
}
