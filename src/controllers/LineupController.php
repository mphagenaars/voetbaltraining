<?php

declare(strict_types=1);

class LineupController {
    private Lineup $lineupModel;
    private Player $playerModel;

    public function __construct(private PDO $pdo) {
        $this->lineupModel = new Lineup($pdo);
        $this->playerModel = new Player($pdo);
    }

    public function index(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        $lineups = $this->lineupModel->getAllForTeam($_SESSION['current_team']['id']);
        require __DIR__ . '/../../src/views/lineups/index.php';
    }

    public function create(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $formation = trim($_POST['formation'] ?? '4-3-3');

            if (!empty($name)) {
                $lineupId = $this->lineupModel->create($_SESSION['current_team']['id'], $name, $formation);
                header('Location: /lineups/view?id=' . $lineupId);
                exit;
            }
        }

        require __DIR__ . '/../../src/views/lineups/create.php';
    }

    public function view(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /lineups');
            exit;
        }

        $lineup = $this->lineupModel->getById($id);
        if (!$lineup || $lineup['team_id'] !== $_SESSION['current_team']['id']) {
            header('Location: /lineups');
            exit;
        }

        $players = $this->playerModel->getAllForTeam($_SESSION['current_team']['id']);
        $positions = $this->lineupModel->getPositions($id);

        require __DIR__ . '/../../src/views/lineups/view.php';
    }

    public function save(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            $input = json_decode(file_get_contents('php://input'), true);

            if ($input && isset($input['lineup_id']) && isset($input['positions'])) {
                // Verify ownership
                $lineup = $this->lineupModel->getById((int)$input['lineup_id']);
                if ($lineup && $lineup['team_id'] === $_SESSION['current_team']['id']) {
                    $this->lineupModel->savePositions((int)$input['lineup_id'], $input['positions']);
                    echo json_encode(['success' => true]);
                    exit;
                }
            }
        }
        http_response_code(400);
        echo json_encode(['success' => false]);
        exit;
    }

    public function delete(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                // Verify ownership
                $lineup = $this->lineupModel->getById($id);
                if ($lineup && $lineup['team_id'] === $_SESSION['current_team']['id']) {
                    $this->lineupModel->delete($id);
                }
            }
        }
        header('Location: /lineups');
        exit;
    }
}
