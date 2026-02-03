<?php

declare(strict_types=1);

class TrainingController extends BaseController {
    private Training $trainingModel;
    private Exercise $exerciseModel;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->trainingModel = new Training($pdo);
        $this->exerciseModel = new Exercise($pdo);
    }

    public function index(): void {
        $this->requireTeamContext();

        [$sort, $filter] = $this->resolveSortFilter('trainings');

        $orderBy = $sort === 'asc' ? 'training_date ASC' : 'training_date DESC';
        
        $trainings = $this->trainingModel->getTrainings(Session::get('current_team')['id'], $orderBy, $filter === 'upcoming');
        
        View::render('trainings/index', [
            'trainings' => $trainings, 
            'pageTitle' => 'Trainingen - Trainer Bobby',
            'currentSort' => $sort,
            'currentFilter' => $filter
        ]);
    }

    public function create(): void {
        $this->requireTeamContext();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/trainings');
            
            $trainingDate = !empty($_POST['training_date']) ? $_POST['training_date'] : date('Y-m-d');
            $title = "Training " . date('d-m-Y', strtotime($trainingDate));
            $description = $_POST['description'] ?? '';
            $selectedExercises = $_POST['exercises'] ?? []; // Array of exercise IDs
            $durations = $_POST['durations'] ?? []; // Array of durations keyed by exercise ID (or index)

            if (!empty($trainingDate)) {
                $trainingId = $this->trainingModel->create(Session::get('current_team')['id'], $title, $description, $trainingDate);

                // Add exercises
                $exercisesData = $this->mapExercisesWithDuration($selectedExercises, $durations);
                $this->trainingModel->updateExercises($trainingId, $exercisesData);

                // Log activity
                $this->logActivity('create_training', $trainingId, $title);

                Session::flash('success', 'Training aangemaakt.');
                $this->redirect('/trainings');
            }
        }

        // Get all exercises to select from
        //$allExercises = $this->exerciseModel->getAllForTeam(Session::get('current_team')['id']);
        $allExercises = $this->exerciseModel->search(null); // Get ALL exercises from database

        View::render('trainings/form', ['allExercises' => $allExercises, 'pageTitle' => 'Nieuwe Training - Trainer Bobby']);
    }

    public function edit(): void {
        $this->requireTeamContext();

        $id = (int)($_GET['id'] ?? 0);
        $training = $this->trainingModel->getById($id);

        if (!$training || $training['team_id'] !== Session::get('current_team')['id']) {
            $this->redirect('/trainings');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/trainings');
            
            $trainingDate = !empty($_POST['training_date']) ? $_POST['training_date'] : date('Y-m-d');
            $title = "Training " . date('d-m-Y', strtotime($trainingDate));
            $description = $_POST['description'] ?? '';
            $selectedExercises = $_POST['exercises'] ?? [];
            $durations = $_POST['durations'] ?? [];

            if (!empty($trainingDate)) {
                $this->trainingModel->update($id, $title, $description, $trainingDate);
                
                $exercisesData = $this->mapExercisesWithDuration($selectedExercises, $durations);
                $this->trainingModel->updateExercises($id, $exercisesData);
                
                // Log activity
                $this->logActivity('edit_training', $id, $title);

                Session::flash('success', 'Training bijgewerkt.');
                $this->redirect('/trainings');
            }
        }

        //$allExercises = $this->exerciseModel->getAllForTeam(Session::get('current_team')['id']);
        $allExercises = $this->exerciseModel->search(null); // Get ALL exercises from database

        View::render('trainings/form', [
            'allExercises' => $allExercises, 
            'training' => $training,
            'pageTitle' => 'Training Bewerken - Trainer Bobby'
        ]);
    }

    public function view(): void {
        $this->requireAuth();
        
        // Handle team context switch via query param
        if (isset($_GET['team_id']) && is_numeric($_GET['team_id'])) {
            $requestedTeamId = (int)$_GET['team_id'];
            $currentTeam = Session::get('current_team');
            
            // Only switch if we are not already in this team's context
            if (!$currentTeam || $currentTeam['id'] !== $requestedTeamId) {
                $teamModel = new Team($this->pdo);
                $teams = $teamModel->getTeamsForUser((int)Session::get('user_id'));
                
                foreach ($teams as $team) {
                    if ($team['id'] === $requestedTeamId) {
                        $role = 'player';
                        if ($team['is_coach']) $role = 'coach';
                        elseif ($team['is_trainer']) $role = 'trainer';

                        Session::set('current_team', [
                            'id' => $team['id'],
                            'name' => $team['name'],
                            'role' => $role,
                            'invite_code' => $team['invite_code'] ?? ''
                        ]);
                        break;
                    }
                }
            }
        }

        if (!Session::has('current_team')) {
            $this->redirect('/');
        }

        $id = (int)($_GET['id'] ?? 0);
        $training = $this->trainingModel->getById($id);

        if (!$training || $training['team_id'] !== Session::get('current_team')['id']) {
            $this->redirect('/trainings');
        }

        // Log activity
        $this->logActivity('view_training', $id, $training['title']);

        View::render('trainings/view', ['training' => $training, 'pageTitle' => $training['title'] . ' - Trainer Bobby']);
    }

    public function delete(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/trainings');
            
            $id = (int)($_POST['id'] ?? 0);
            $training = $this->trainingModel->getById($id);

            if ($training && $training['team_id'] === Session::get('current_team')['id']) {
                $this->trainingModel->delete($id);
                
                // Log activity
                $this->logActivity('delete_training', $id, $training['title']);

                Session::flash('success', 'Training verwijderd.');
            }
        }
        $this->redirect('/trainings');
    }

    private function mapExercisesWithDuration(array $selectedExercises, array $durations): array {
        $exercisesData = [];
        if (is_array($selectedExercises)) {
            foreach ($selectedExercises as $index => $exerciseId) {
                $exercisesData[] = [
                    'id' => (int)$exerciseId,
                    'duration' => !empty($durations[$index]) ? (int)$durations[$index] : null
                ];
            }
        }
        return $exercisesData;
    }
}
