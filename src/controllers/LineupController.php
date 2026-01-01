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
        if (!Session::has('current_team')) {
            $this->redirect('/');
        }
        $lineups = $this->lineupModel->getAllForTeam(Session::get('current_team')['id']);
        View::render('lineups/index', ['lineups' => $lineups, 'pageTitle' => 'Opstellingen - Trainer Bobby']);
    }

    public function create(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/lineups');
            
            $validator = new Validator($_POST);
            $validator->required('name');

            if ($validator->isValid()) {
                $formation = trim($_POST['formation'] ?? '4-3-3');
                $lineupId = $this->lineupModel->create(Session::get('current_team')['id'], $_POST['name'], $formation);
                Session::flash('success', 'Opstelling aangemaakt.');
                $this->redirect('/lineups/view?id=' . $lineupId);
            }
        }

        View::render('lineups/create', ['pageTitle' => 'Nieuwe Opstelling - Trainer Bobby']);
    }

    public function view(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            $this->redirect('/');
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/lineups');
        }

        $lineup = $this->lineupModel->getById($id);
        if (!$lineup || $lineup['team_id'] !== Session::get('current_team')['id']) {
            $this->redirect('/lineups');
        }

        $players = $this->playerModel->getAllForTeam(Session::get('current_team')['id'], 'name ASC');
        $positions = $this->lineupModel->getPositions($id);

        View::render('lineups/view', ['lineup' => $lineup, 'players' => $players, 'positions' => $positions, 'pageTitle' => $lineup['name'] . ' - Trainer Bobby']);
    }

    public function save(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && Session::has('user_id') && Session::has('current_team')) {
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
                if ($lineup && $lineup['team_id'] === Session::get('current_team')['id']) {
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && Session::has('user_id') && Session::has('current_team')) {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                // Verify ownership
                $lineup = $this->lineupModel->getById($id);
                if ($lineup && $lineup['team_id'] === Session::get('current_team')['id']) {
                    $this->lineupModel->delete($id);
                    Session::flash('success', 'Opstelling verwijderd.');
                }
            }
        }
        $this->redirect('/lineups');
    }
}
