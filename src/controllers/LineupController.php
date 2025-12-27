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
        View::render('lineups/index', ['lineups' => $lineups, 'pageTitle' => 'Opstellingen - Trainer Bobby']);
    }

    public function create(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                header('Location: /lineups');
                exit;
            }
            $name = trim($_POST['name'] ?? '');
            $formation = trim($_POST['formation'] ?? '4-3-3');

            if (!empty($name)) {
                $lineupId = $this->lineupModel->create($_SESSION['current_team']['id'], $name, $formation);
                header('Location: /lineups/view?id=' . $lineupId);
                exit;
            }
        }

        View::render('lineups/create', ['pageTitle' => 'Nieuwe Opstelling - Trainer Bobby']);
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

        View::render('lineups/view', ['lineup' => $lineup, 'players' => $players, 'positions' => $positions, 'pageTitle' => $lineup['name'] . ' - Trainer Bobby']);
    }

    public function save(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && isset($_SESSION['current_team'])) {
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');

            if (!Csrf::verifyToken($token)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF Token']);
                exit;
            }

            if ($input && isset($input['id']) && isset($input['positions'])) {
                // Verify ownership
                $lineup = $this->lineupModel->getById((int)$input['id']);
                if ($lineup && $lineup['team_id'] === $_SESSION['current_team']['id']) {
                    $this->lineupModel->savePositions((int)$input['id'], $input['positions']);
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
