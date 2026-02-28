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
        $this->requireTeamContext();

        [$sort, $filter] = $this->resolveSortFilter('matches');

        $orderBy = $sort === 'asc' ? 'date ASC' : 'date DESC';
        $matches = $this->gameModel->getMatches(Session::get('current_team')['id'], $orderBy, $filter === 'upcoming');
        
        View::render('matches/index', [
            'matches' => $matches, 
            'pageTitle' => 'Wedstrijden - Trainer Bobby',
            'currentSort' => $sort,
            'currentFilter' => $filter
        ]);
    }

    public function create(): void {
        $this->requireTeamContext();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/matches');
            
            $validator = new Validator($_POST);
            $validator->required('opponent')->required('date');

            if ($validator->isValid()) {
                $isHome = (int)($_POST['is_home'] ?? 1);
                $formation = trim($_POST['formation'] ?? '11-vs-11');
                
                $matchId = $this->gameModel->create(Session::get('current_team')['id'], $_POST['opponent'], $_POST['date'], $isHome, $formation);
                
                // Log activity
                $this->logActivity('create_match', $matchId, $_POST['opponent']);

                Session::flash('success', 'Wedstrijd aangemaakt.');
                $this->redirect('/matches/view?id=' . $matchId);
            }
        }

        View::render('matches/create', ['pageTitle' => 'Nieuwe Wedstrijd - Trainer Bobby']);
    }

    public function delete(): void {
        $this->requireAuth();
        $isAdmin = (bool)Session::get('is_admin');
        
        // Only admins can delete matches
        if (!$isAdmin) {
            http_response_code(403);
            die('Geen toegang');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/matches');
            
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $match = $this->gameModel->getById($id);
                if ($match) {
                    $teamModel = new Team($this->pdo);
                    if (!$teamModel->canManageTeam((int)$match['team_id'], (int)Session::get('user_id'), $isAdmin)) {
                        http_response_code(403);
                        die('Geen toegang');
                    }

                    $this->gameModel->delete($id);
                    
                    // Log activity
                    $this->logActivity('delete_match', $id, $match['opponent']);

                    Session::flash('success', 'Wedstrijd verwijderd.');
                }
            }
        }
        
        $this->redirect('/matches');
    }

    public function view(): void {
        $this->requireTeamContext();

        $id = (int)($_GET['id'] ?? 0);
        $match = $this->gameModel->getById($id);

        if (!$this->canAccessMatchInCurrentTeam($match)) {
            $this->redirect('/matches');
        }

        // Log activity
        $this->logActivity('view_match', $id, $match['opponent']);

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

    public function live(): void {
        $this->requireTeamContext();

        $id = (int)($_GET['id'] ?? 0);
        $match = $this->gameModel->getById($id);

        if (!$this->canAccessMatchInCurrentTeam($match)) {
            $this->redirect('/matches');
        }

        $players = $this->playerModel->getAllForTeam(Session::get('current_team')['id'], 'name ASC');
        $matchPlayers = $this->gameModel->getPlayers($id);
        $events = $this->gameModel->getEvents($id);
        $timerState = $this->gameModel->getTimerState($id);

        View::render('matches/live', [
            'match' => $match, 
            'players' => $players, 
            'matchPlayers' => $matchPlayers,
            'events' => $events,
            'timerState' => $timerState,
            'pageTitle' => 'Live Wedstrijd - Trainer Bobby'
        ]);
    }

    public function timerAction(): void {
        if (!Session::has('user_id') || !Session::has('current_team')) {
            http_response_code(403);
            exit;
        }
        
        $json = json_decode(file_get_contents('php://input'), true);
        $matchId = (int)($json['match_id'] ?? 0);
        $action = $json['action'] ?? ''; // start, stop
        
        $match = $this->gameModel->getById($matchId);
        if (!$this->canAccessMatchInCurrentTeam($match)) {
            http_response_code(403);
            exit;
        }

        $timerState = $this->gameModel->getTimerState($matchId);
        $minute = (int)$timerState['total_minutes'];
        $currentPeriod = $timerState['current_period'];
        
        if ($action === 'start') {
            // New period starts if not playing
            $addPeriod = 0;
            if (!$timerState['is_playing']) {
                 $addPeriod = 1;
            }
            // Use currentPeriod + addPeriod. If it was 0, it becomes 1. If it was 1 and stopped, it becomes 2?
            // Wait, if I stop period 1, currentPeriod remains 1 in my logic?
            // "end_period" logic in GameModel: currentPeriod = $period.
            // If I stop, isPlaying becomes false. currentPeriod remains what it was.
            // So if I start again, I should increment period.
            // UNLESS it's a resume? The requirement said "periodes afzonderlijk starten/stoppen" (quarters).
            // So Start -> Period 1. Stop. Start -> Period 2.
            $nextPeriod = $currentPeriod + $addPeriod;
            if ($timerState['is_playing']) {
                $nextPeriod = $currentPeriod; // Already playing
            }

            $this->gameModel->addEvent($matchId, $minute, 'whistle', null, 'start_period', $nextPeriod);
        } elseif ($action === 'stop') {
             $this->gameModel->addEvent($matchId, $minute, 'whistle', null, 'end_period', $currentPeriod);
        }

        $newState = $this->gameModel->getTimerState($matchId);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'timerState' => $newState]);
    }

    public function addEvent(): void {
        $this->requireAuth();
        if (!Session::has('current_team')) {
            if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
                http_response_code(403); 
                echo json_encode(['error' => 'Auth required']);
                exit;
            }
            $this->redirect('/');
        }
        
        $input = $_POST;
        $isJson = false;

        if (($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json') {
            $input = json_decode(file_get_contents('php://input'), true);
            $isJson = true;
        } else {
            $this->verifyCsrf('/matches');
        }
        
        $matchId = (int)($input['match_id'] ?? 0);
        $type = $input['type'] ?? '';
        
        if ($matchId && $type) {
             $match = $this->gameModel->getById($matchId);
             if ($this->canAccessMatchInCurrentTeam($match)) {
                 
                 $minute = isset($input['minute']) && $input['minute'] !== '' ? (int)$input['minute'] : null;
                 $period = (int)($input['period'] ?? 1);

                 if ($minute === null) {
                     $state = $this->gameModel->getTimerState($matchId);
                     $minute = (int)$state['total_minutes'];
                     // If we are live, use the period from the timer state
                     // If playing, use current_period. If stopped, maybe use current_period (e.g. goal after whistle?)
                     $period = $state['current_period'] > 0 ? $state['current_period'] : 1;
                 }

                 // Sanitation: Ensure playerId is a positive integer or null
                 $rawPlayerId = $input['player_id'] ?? '';
                 $playerId = (is_numeric($rawPlayerId) && (int)$rawPlayerId > 0) ? (int)$rawPlayerId : null;

                 $description = $input['description'] ?? '';
                 
                 $this->gameModel->addEvent($matchId, $minute, $type, $playerId, $description, $period);
                 
                 if ($type === 'goal') {
                     if ($playerId) {
                         if ($match['is_home']) $match['score_home']++; else $match['score_away']++;
                     } else {
                         if ($match['is_home']) $match['score_away']++; else $match['score_home']++;
                     }
                     $this->gameModel->updateScore($matchId, (int)$match['score_home'], (int)$match['score_away']);
                 } elseif ($type === 'goal_unknown') {
                     // Goal for us, but no specific player (Overig)
                     // Treat as own team goal
                     if ($match['is_home']) $match['score_home']++; else $match['score_away']++;
                     $this->gameModel->updateScore($matchId, (int)$match['score_home'], (int)$match['score_away']);
                 }

                 if ($isJson) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true, 
                        'events' => $this->gameModel->getEvents($matchId),
                        'score_home' => $match['score_home'],
                        'score_away' => $match['score_away']
                    ]);
                    return;
                 }
                 
                 Session::flash('success', 'Gebeurtenis toegevoegd.');
             }
        }
        
        if ($isJson) {
            http_response_code(400); 
            echo json_encode(['error' => 'Invalid request']);
            exit;
        }
        $this->redirect('/matches/view?id=' . $matchId);
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
            if ($this->canAccessMatchInCurrentTeam($match)) {
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
            if ($this->canAccessMatchInCurrentTeam($match)) {
                $this->gameModel->updateScore($matchId, $scoreHome, $scoreAway);
                $this->gameModel->updateEvaluation($matchId, $evaluation);
                Session::flash('success', 'Wedstrijd details bijgewerkt.');
            }
            
            $this->redirect('/matches/view?id=' . $matchId);
        }
    }

    public function saveLineup(): void {
        header('Content-Type: application/json');

        if (!Session::has('user_id') || !Session::has('current_team')) {
            http_response_code(403);
            echo json_encode(['error' => 'Niet geautoriseerd.']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !isset($data['match_id']) || !isset($data['players']) || !is_array($data['players'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Ongeldige requestdata.']);
            exit;
        }

        $csrfToken = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::verifyToken(is_string($csrfToken) ? $csrfToken : null)) {
            http_response_code(403);
            echo json_encode(['error' => 'Ongeldig CSRF-token.']);
            exit;
        }

        $matchId = (int)$data['match_id'];
        $match = $this->gameModel->getById($matchId);

        if (!$this->canAccessMatchInCurrentTeam($match)) {
            http_response_code(403);
            echo json_encode(['error' => 'Geen toegang tot deze wedstrijd.']);
            exit;
        }

        $players = [];
        $playerIds = [];
        foreach ($data['players'] as $player) {
            if (!is_array($player) || !isset($player['player_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Ongeldige spelerdata.']);
                exit;
            }

            $playerId = (int)$player['player_id'];
            if ($playerId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Ongeldige speler-ID.']);
                exit;
            }

            $playerIds[] = $playerId;
            $players[] = [
                'player_id' => $playerId,
                'x' => isset($player['x']) ? (float)$player['x'] : 0,
                'y' => isset($player['y']) ? (float)$player['y'] : 0,
                'is_substitute' => !empty($player['is_substitute']) ? 1 : 0,
                'is_keeper' => !empty($player['is_keeper']) ? 1 : 0,
                'is_absent' => !empty($player['is_absent']) ? 1 : 0,
            ];
        }

        if (!$this->gameModel->allPlayersBelongToTeam($playerIds, (int)$match['team_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Een of meer spelers horen niet bij dit team.']);
            exit;
        }

        try {
            $this->gameModel->savePlayers($matchId, $players);
            
            // Log activity
            $logModel = new ActivityLog($this->pdo);
            $logModel->log(Session::get('user_id'), 'update_match_lineup', $matchId, $match['opponent']);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function canAccessMatchInCurrentTeam(?array $match): bool {
        if (!$match || !Session::has('user_id') || !Session::has('current_team')) {
            return false;
        }

        $teamId = (int)($match['team_id'] ?? 0);
        $currentTeamId = (int)(Session::get('current_team')['id'] ?? 0);
        if ($teamId <= 0 || $teamId !== $currentTeamId) {
            return false;
        }

        $teamModel = new Team($this->pdo);
        return $teamModel->canAccessTeam(
            $teamId,
            (int)Session::get('user_id'),
            (bool)Session::get('is_admin')
        );
    }
}
