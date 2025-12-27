<?php

declare(strict_types=1);

class TrainingController {
    private Training $trainingModel;
    private Exercise $exerciseModel;

    public function __construct(private PDO $pdo) {
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
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $selectedExercises = $_POST['exercises'] ?? []; // Array of exercise IDs
            $durations = $_POST['durations'] ?? []; // Array of durations keyed by exercise ID (or index)

            if (!empty($title)) {
                $trainingId = $this->trainingModel->create($_SESSION['current_team']['id'], $title, $description);

                // Add exercises
                if (is_array($selectedExercises)) {
                    foreach ($selectedExercises as $index => $exerciseId) {
                        $duration = !empty($durations[$index]) ? (int)$durations[$index] : null;
                        $this->trainingModel->addExercise($trainingId, (int)$exerciseId, $index, $duration);
                    }
                }

                header('Location: /trainings');
                exit;
            }
        }

        // Get all exercises to select from
        $allExercises = $this->exerciseModel->getAllForTeam($_SESSION['current_team']['id']);

        View::render('trainings/form', ['allExercises' => $allExercises, 'pageTitle' => 'Nieuwe Training - Trainer Bobby']);
    }

    public function view(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }

        $id = (int)($_GET['id'] ?? 0);
        $training = $this->trainingModel->getById($id);

        if (!$training || $training['team_id'] !== $_SESSION['current_team']['id']) {
            header('Location: /trainings');
            exit;
        }

        View::render('trainings/view', ['training' => $training, 'pageTitle' => $training['title'] . ' - Trainer Bobby']);
    }

    public function delete(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            $id = (int)($_POST['id'] ?? 0);
            $training = $this->trainingModel->getById($id);

            if ($training && $training['team_id'] === $_SESSION['current_team']['id']) {
                $this->trainingModel->delete($id);
            }
        }
        header('Location: /trainings');
        exit;
    }
}
