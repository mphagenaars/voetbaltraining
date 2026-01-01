<?php

declare(strict_types=1);

class PlayerController extends BaseController {
    private Player $playerModel;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->playerModel = new Player($pdo);
    }

    public function index(): void {
        $this->requireAuth();
        if (!isset($_SESSION['current_team'])) {
            $this->redirect('/');
        }
        $players = $this->playerModel->getAllForTeam($_SESSION['current_team']['id'], 'name ASC');
        View::render('players/index', ['players' => $players, 'pageTitle' => 'Spelers - Trainer Bobby']);
    }

    public function create(): void {
        $this->requireAuth();
        if (!isset($_SESSION['current_team'])) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/players');
            
            $name = trim($_POST['name'] ?? '');
            if (!empty($name)) {
                $this->playerModel->create($_SESSION['current_team']['id'], $name);
            }
            $this->redirect('/players');
        }

        // GET request
        View::render('players/create', ['pageTitle' => 'Nieuwe Speler - Trainer Bobby']);
    }

    public function edit(): void {
        $this->requireAuth();
        if (!isset($_SESSION['current_team'])) {
            $this->redirect('/');
        }

        $id = (int)($_GET['id'] ?? 0);
        $player = $this->playerModel->getById($id);

        if (!$player || $player['team_id'] !== $_SESSION['current_team']['id']) {
            header('Location: /players');
            exit;
        }

        View::render('players/edit', ['player' => $player, 'pageTitle' => 'Speler Bewerken - Trainer Bobby']);
    }

    public function update(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/players');
            
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');

            if ($id > 0 && !empty($name)) {
                // Verify ownership
                $player = $this->playerModel->getById($id);
                if ($player && $player['team_id'] === $_SESSION['current_team']['id']) {
                    $this->playerModel->update($id, $name);
                }
            }
            $this->redirect('/players');
        }
    }

    public function delete(): void {
        $this->requireAuth();
        if (!isset($_SESSION['current_team'])) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/players');
            
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                // TODO: Check if player belongs to current team (Model should probably handle this or we check here)
                // For now, following existing logic which didn't explicitly check ownership in the delete block in index.php (it had a TODO comment)
                // But let's be safe and check it if we can.
                $player = $this->playerModel->getById($id);
                if ($player && $player['team_id'] === $_SESSION['current_team']['id']) {
                    $this->playerModel->delete($id);
                }
            }
        }
        $this->redirect('/players');
    }
}
