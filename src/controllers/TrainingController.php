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
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }

        $trainings = $this->trainingModel->getAllForTeam($_SESSION['current_team']['id']);
        View::render('trainings/index', ['trainings' => $trainings, 'pageTitle' => 'Trainingen - Trainer Bobby']);
    }

    public function create(): void {
        $this->requireAuth();
        if (!isset($_SESSION['current_team'])) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/trainings');
            
            $trainingDate = !empty($_POST['training_date']) ? $_POST['training_date'] : date('Y-m-d');
            $title = "Training " . date('d-m-Y', strtotime($trainingDate));
            $description = $_POST['description'] ?? '';
            $selectedExercises = $_POST['exercises'] ?? []; // Array of exercise IDs
            $durations = $_POST['durations'] ?? []; // Array of durations keyed by exercise ID (or index)

            if (!empty($trainingDate)) {
                $trainingId = $this->trainingModel->create($_SESSION['current_team']['id'], $title, $description, $trainingDate);

                // Add exercises
                if (is_array($selectedExercises)) {
                    $exercisesData = [];
                    foreach ($selectedExercises as $index => $exerciseId) {
                        $exercisesData[] = [
                            'id' => (int)$exerciseId,
                            'duration' => !empty($durations[$index]) ? (int)$durations[$index] : null
                        ];
                    }
                    $this->trainingModel->updateExercises($trainingId, $exercisesData);
                }

                $this->redirect('/trainings');
            }
        }

        // Get all exercises to select from
        $allExercises = $this->exerciseModel->getAllForTeam($_SESSION['current_team']['id']);

        View::render('trainings/form', ['allExercises' => $allExercises, 'pageTitle' => 'Nieuwe Training - Trainer Bobby']);
    }

    public function edit(): void {
        $this->requireAuth();
        if (!isset($_SESSION['current_team'])) {
            $this->redirect('/');
        }

        $id = (int)($_GET['id'] ?? 0);
        $training = $this->trainingModel->getById($id);

        if (!$training || $training['team_id'] !== $_SESSION['current_team']['id']) {
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
                
                $exercisesData = [];
                if (is_array($selectedExercises)) {
                    foreach ($selectedExercises as $index => $exerciseId) {
                        $exercisesData[] = [
                            'id' => (int)$exerciseId,
                            'duration' => !empty($durations[$index]) ? (int)$durations[$index] : null
                        ];
                    }
                }
                $this->trainingModel->updateExercises($id, $exercisesData);
                
                $this->redirect('/trainings');
            }
        }

        $allExercises = $this->exerciseModel->getAllForTeam($_SESSION['current_team']['id']);

        View::render('trainings/form', [
            'allExercises' => $allExercises, 
            'training' => $training,
            'pageTitle' => 'Training Bewerken - Trainer Bobby'
        ]);
    }

    public function view(): void {
        $this->requireAuth();
        if (!isset($_SESSION['current_team'])) {
            $this->redirect('/');
        }

        $id = (int)($_GET['id'] ?? 0);
        $training = $this->trainingModel->getById($id);

        if (!$training || $training['team_id'] !== $_SESSION['current_team']['id']) {
            $this->redirect('/trainings');
        }

        View::render('trainings/view', ['training' => $training, 'pageTitle' => $training['title'] . ' - Trainer Bobby']);
    }

    public function delete(): void {
        $this->requireAuth();
        if (!isset($_SESSION['current_team'])) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/trainings');
            
            $id = (int)($_POST['id'] ?? 0);
            $training = $this->trainingModel->getById($id);

            if ($training && $training['team_id'] === $_SESSION['current_team']['id']) {
                $this->trainingModel->delete($id);
            }
        }
        $this->redirect('/trainings');
    }
}
