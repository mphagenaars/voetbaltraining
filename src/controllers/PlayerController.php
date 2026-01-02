<?php

declare(strict_types=1);

class PlayerController extends BaseController {
    private Player $playerModel;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->playerModel = new Player($pdo);
    }

    public function index(): void {
        $this->requireTeamContext();
        $players = $this->playerModel->getAllForTeam(Session::get('current_team')['id'], 'name ASC');
        View::render('players/index', ['players' => $players, 'pageTitle' => 'Spelers - Trainer Bobby']);
    }

    public function create(): void {
        $this->requireTeamContext();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/players');
            
            $validator = new Validator($_POST);
            $validator->required('name');

            if ($validator->isValid()) {
                $this->playerModel->create(Session::get('current_team')['id'], $_POST['name']);
                
                // Log activity
                $this->logActivity('create_player', null, $_POST['name']);

                Session::flash('success', 'Speler toegevoegd.');
                $this->redirect('/players');
            }
        }

        // GET request
        View::render('players/create', ['pageTitle' => 'Nieuwe Speler - Trainer Bobby']);
    }

    public function edit(): void {
        $this->requireTeamContext();

        $id = (int)($_GET['id'] ?? 0);
        $player = $this->playerModel->getById($id);

        if (!$player || $player['team_id'] !== Session::get('current_team')['id']) {
            $this->redirect('/players');
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
                if ($player && $player['team_id'] === Session::get('current_team')['id']) {
                    $this->playerModel->update($id, $name);
                    Session::flash('success', 'Speler bijgewerkt.');
                }
            }
            $this->redirect('/players');
        }
    }

    public function delete(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/players');
            
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $player = $this->playerModel->getById($id);
                if ($player && $player['team_id'] === Session::get('current_team')['id']) {
                    $this->playerModel->delete($id);
                    
                    // Log activity
                    $logModel = new ActivityLog($this->pdo);
                    $logModel->log(Session::get('user_id'), 'delete_player', $id, $player['name']);

                    Session::flash('success', 'Speler verwijderd.');
                }
            }
        }
        $this->redirect('/players');
    }
}
