<?php

declare(strict_types=1);

class LineupController extends BaseController {
    private Lineup $lineupModel;
    private Player $playerModel;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->lineupModel = new Lineup($pdo);
        $this->playerModel = new Player($pdo);
    }

    public function index(): void {
        $this->requireAuth();
        if (!isset($_SESSION['current_team'])) {
            $this->redirect('/');
        }
        $lineups = $this->lineupModel->getAllForTeam($_SESSION['current_team']['id']);
        View::render('lineups/index', ['lineups' => $lineups, 'pageTitle' => 'Opstellingen - Trainer Bobby']);
    }

    public function create(): void {
        $this->requireAuth();
        if (!isset($_SESSION['current_team'])) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/lineups');
            
            $name = trim($_POST['name'] ?? '');
            $formation = trim($_POST['formation'] ?? '4-3-3');

            if (!empty($name)) {
                $lineupId = $this->lineupModel->create($_SESSION['current_team']['id'], $name, $formation);
                $this->redirect('/lineups/view?id=' . $lineupId);
            }
        }

        View::render('lineups/create', ['pageTitle' => 'Nieuwe Opstelling - Trainer Bobby']);
    }

    public function view(): void {
        $this->requireAuth();
        if (!isset($_SESSION['current_team'])) {
            $this->redirect('/');
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

        $players = $this->playerModel->getAllForTeam($_SESSION['current_team']['id'], 'name ASC');
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
