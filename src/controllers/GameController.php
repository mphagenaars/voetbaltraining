<?php
declare(strict_types=1);

class GameController {
    private Game $gameModel;
    private Player $playerModel;

    public function __construct(private PDO $pdo) {
        $this->gameModel = new Game($pdo);
        $this->playerModel = new Player($pdo);
    }

    public function index(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        $matches = $this->gameModel->getAllForTeam($_SESSION['current_team']['id'], 'date DESC');
        View::render('matches/index', ['matches' => $matches, 'pageTitle' => 'Wedstrijden - Trainer Bobby']);
    }

    public function create(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                header('Location: /matches');
                exit;
            }
            $opponent = trim($_POST['opponent'] ?? '');
            $date = $_POST['date'] ?? date('Y-m-d H:i:s');
            $isHome = isset($_POST['is_home']) ? 1 : 0;
            $formation = trim($_POST['formation'] ?? '4-3-3');

            if (!empty($opponent)) {
                $matchId = $this->gameModel->create($_SESSION['current_team']['id'], $opponent, $date, $isHome, $formation);
                header('Location: /matches/view?id=' . $matchId);
                exit;
            }
        }

        View::render('matches/create', ['pageTitle' => 'Nieuwe Wedstrijd - Trainer Bobby']);
    }

    public function view(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }

        $id = (int)($_GET['id'] ?? 0);
        $match = $this->gameModel->getById($id);

        if (!$match || $match['team_id'] !== $_SESSION['current_team']['id']) {
            header('Location: /matches');
            exit;
        }

        $players = $this->playerModel->getAllForTeam($_SESSION['current_team']['id']);
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
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                header('Location: /matches');
                exit;
            }
             $matchId = (int)$_POST['match_id'];
             $minute = (int)$_POST['minute'];
             $type = $_POST['type'];
             $playerId = !empty($_POST['player_id']) ? (int)$_POST['player_id'] : null;
             $description = $_POST['description'] ?? '';
             
             $match = $this->gameModel->getById($matchId);
             if ($match && $match['team_id'] === $_SESSION['current_team']['id']) {
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
             }
             header('Location: /matches/view?id=' . $matchId);
             exit;
        }
    }

    public function updateScore(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                header('Location: /matches');
                exit;
            }

            $matchId = (int)$_POST['match_id'];
            $scoreHome = (int)$_POST['score_home'];
            $scoreAway = (int)$_POST['score_away'];

            $match = $this->gameModel->getById($matchId);
            if ($match && $match['team_id'] === $_SESSION['current_team']['id']) {
                $this->gameModel->updateScore($matchId, $scoreHome, $scoreAway);
            }
            
            header('Location: /matches/view?id=' . $matchId);
            exit;
        }
    }

    public function updateEvaluation(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
            header('Location: /');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verifyToken($_POST['csrf_token'] ?? '')) {
                header('Location: /matches');
                exit;
            }

            $matchId = (int)$_POST['match_id'];
            $evaluation = $_POST['evaluation'] ?? '';

            $match = $this->gameModel->getById($matchId);
            if ($match && $match['team_id'] === $_SESSION['current_team']['id']) {
                $this->gameModel->updateEvaluation($matchId, $evaluation);
            }
            
            header('Location: /matches/view?id=' . $matchId);
            exit;
        }
    }

    public function saveLineup(): void {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_team'])) {
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

        if (!$match || $match['team_id'] !== $_SESSION['current_team']['id']) {
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
