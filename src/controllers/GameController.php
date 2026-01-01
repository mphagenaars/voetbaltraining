<?php
declare(strict_types=1);

class GameController extends BaseController {
    private Game $gameModel;
    private Player $playerModel;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->gameModel = new Game($pdo);
        $this->playerModel = new Player($pdo);
    }

    public function index(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            $this->redirect('/');
        }

        $sort = $_GET['sort'] ?? 'desc';
        if (!in_array($sort, ['asc', 'desc'])) {
            $sort = 'desc';
        }

        $orderBy = $sort === 'asc' ? 'date ASC' : 'date DESC';
        $matches = $this->gameModel->getAllForTeam(Session::get('current_team')['id'], $orderBy);
        
        View::render('matches/index', [
            'matches' => $matches, 
            'pageTitle' => 'Wedstrijden - Trainer Bobby',
            'currentSort' => $sort
        ]);
    }

    public function create(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/matches');
            
            $validator = new Validator($_POST);
            $validator->required('opponent')->required('date');

            if ($validator->isValid()) {
                $isHome = (int)($_POST['is_home'] ?? 1);
                $formation = trim($_POST['formation'] ?? '4-3-3');
                
                $matchId = $this->gameModel->create(Session::get('current_team')['id'], $_POST['opponent'], $_POST['date'], $isHome, $formation);
                Session::flash('success', 'Wedstrijd aangemaakt.');
                $this->redirect('/matches/view?id=' . $matchId);
            }
        }

        View::render('matches/create', ['pageTitle' => 'Nieuwe Wedstrijd - Trainer Bobby']);
    }

    public function delete(): void {
        $this->requireAuth();
        
        // Only admins can delete matches
        if (!Session::get('is_admin')) {
            http_response_code(403);
            die('Geen toegang');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/matches');
            
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $this->gameModel->delete($id);
                Session::flash('success', 'Wedstrijd verwijderd.');
            }
        }
        
        $this->redirect('/matches');
    }

    public function view(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            $this->redirect('/');
        }

        $id = (int)($_GET['id'] ?? 0);
        $match = $this->gameModel->getById($id);

        if (!$match || $match['team_id'] !== Session::get('current_team')['id']) {
            $this->redirect('/matches');
        }

        $players = $this->playerModel->getAllForTeam(Session::get('current_team')['id'], 'name ASC');
        $matchPlayers = $this->gameModel->getPlayers($id);
        $events = $this->gameModel->getEvents($id);

        View::render('matches/view', [
            'match' => $match, 
            'players' => $players, 
            'matchPlayers' => $matchPlayers,
            'events' => $events,
            'pageTitle' => 'Wedstrijd - Trainer Bobby'
        ]);
    }

    public function addEvent(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            $this->redirect('/');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             $this->verifyCsrf('/matches');

             $validator = new Validator($_POST);
             $validator->required('match_id')->required('minute')->required('type');

             if ($validator->isValid()) {
                 $matchId = (int)$_POST['match_id'];
                 $minute = (int)$_POST['minute'];
                 $type = $_POST['type'];
                 $playerId = !empty($_POST['player_id']) ? (int)$_POST['player_id'] : null;
                 $description = $_POST['description'] ?? '';
                 
                 $match = $this->gameModel->getById($matchId);
                 if ($match && $match['team_id'] === Session::get('current_team')['id']) {
                     $this->gameModel->addEvent($matchId, $minute, $type, $playerId, $description);
                     
                     if ($type === 'goal') {
                         if ($playerId) {
                             // Our goal
                             if ($match['is_home']) $match['score_home']++; else $match['score_away']++;
                         } else {
                             // Opponent goal
                             if ($match['is_home']) $match['score_away']++; else $match['score_home']++;
                         }
                         $this->gameModel->updateScore($matchId, (int)$match['score_home'], (int)$match['score_away']);
                     }
                     Session::flash('success', 'Gebeurtenis toegevoegd.');
                 }
                 $this->redirect('/matches/view?id=' . $matchId);
             }
        }
        $this->redirect('/matches');
    }

    public function updateScore(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/matches');

            $matchId = (int)$_POST['match_id'];
            $scoreHome = (int)$_POST['score_home'];
            $scoreAway = (int)$_POST['score_away'];

            $match = $this->gameModel->getById($matchId);
            if ($match && $match['team_id'] === Session::get('current_team')['id']) {
                $this->gameModel->updateScore($matchId, $scoreHome, $scoreAway);
                Session::flash('success', 'Score bijgewerkt.');
            }
            
            $this->redirect('/matches/view?id=' . $matchId);
        }
    }

    public function updateDetails(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/matches');

            $matchId = (int)$_POST['match_id'];
            $scoreHome = (int)$_POST['score_home'];
            $scoreAway = (int)$_POST['score_away'];
            $evaluation = $_POST['evaluation'] ?? '';

            $match = $this->gameModel->getById($matchId);
            if ($match && $match['team_id'] === Session::get('current_team')['id']) {
                $this->gameModel->updateScore($matchId, $scoreHome, $scoreAway);
                $this->gameModel->updateEvaluation($matchId, $evaluation);
                Session::flash('success', 'Wedstrijd details bijgewerkt.');
            }
            
            $this->redirect('/matches/view?id=' . $matchId);
        }
    }

    public function saveLineup(): void {
        if (!Session::has('user_id') || !Session::has('current_team')) {
            http_response_code(403);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['match_id']) || !isset($data['players'])) {
            http_response_code(400);
            exit;
        }

        $matchId = (int)$data['match_id'];
        $match = $this->gameModel->getById($matchId);

        if (!$match || $match['team_id'] !== Session::get('current_team')['id']) {
            http_response_code(403);
            exit;
        }

        try {
            $this->gameModel->savePlayers($matchId, $data['players']);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
