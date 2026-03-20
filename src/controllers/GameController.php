<?php
declare(strict_types=1);

class GameController extends BaseController {
    private Game $gameModel;
    private Player $playerModel;
    private MatchTactic $matchTacticModel;
    private MatchTacticInputValidator $matchTacticInputValidator;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->gameModel = new Game($pdo);
        $this->playerModel = new Player($pdo);
        $this->matchTacticModel = new MatchTactic($pdo);
        $this->matchTacticInputValidator = new MatchTacticInputValidator();
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

    public function reports(): void {
        $this->requireTeamContext();

        $teamId = (int)Session::get('current_team')['id'];
        $allowedSort = ['name', 'matches', 'absent', 'starts', 'goals', 'keepers'];
        $sort = strtolower((string)($_GET['sort'] ?? 'matches'));
        $dir = strtolower((string)($_GET['dir'] ?? 'desc'));

        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'matches';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $playerStats = $this->gameModel->getPlayerReportStats($teamId, $sort, $dir);
        $summary = $this->gameModel->getReportSummary($teamId);

        View::render('matches/reports', [
            'playerStats' => $playerStats,
            'summary' => $summary,
            'currentSort' => $sort,
            'currentDir' => $dir,
            'pageTitle' => 'Wedstrijd Rapportage - Trainer Bobby'
        ]);
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
        $matchTactics = $this->matchTacticModel->getForMatch($id);

        View::render('matches/view', [
            'match' => $match, 
            'players' => $players, 
            'matchPlayers' => $matchPlayers,
            'events' => $events,
            'matchTactics' => $matchTactics,
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
            'bodyClass' => 'page-match-live',
            'viewportContent' => 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover',
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

    public function saveTactic(): void {
        header('Content-Type: application/json');

        if (!Session::has('user_id') || !Session::has('current_team')) {
            http_response_code(403);
            echo json_encode(['error' => 'Niet geautoriseerd.']);
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Methode niet toegestaan.']);
            exit;
        }

        $data = $this->decodeJsonBody();
        if (!is_array($data)) {
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

        $context = $this->resolveTacticContext($data, false);
        if (!$context) {
            http_response_code(403);
            echo json_encode(['error' => 'Geen toegang tot deze tactiekcontext.']);
            exit;
        }

        try {
            $validated = $this->matchTacticInputValidator->validateForSave($data);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
        $title = $validated['title'];
        $phase = $validated['phase'];
        $minute = $validated['minute'];
        $fieldType = $validated['field_type'];
        $drawingData = $validated['drawing_data'];

        $tacticId = (int)($data['tactic_id'] ?? 0);

        try {
            if (($context['mode'] ?? '') === MatchTactic::CONTEXT_MATCH) {
                $matchId = (int)($context['match_id'] ?? 0);
                if ($tacticId > 0) {
                    $existing = $this->matchTacticModel->getByIdForMatch($tacticId, $matchId);
                    if (!$existing) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Situatie niet gevonden.']);
                        exit;
                    }

                    $this->matchTacticModel->updateTactic(
                        $tacticId,
                        $matchId,
                        $title,
                        $phase,
                        $minute,
                        $fieldType,
                        $drawingData
                    );
                    $savedId = $tacticId;
                    $this->logActivity('update_match_tactic', $matchId, $title);
                } else {
                    $savedId = $this->matchTacticModel->create(
                        $matchId,
                        $title,
                        $phase,
                        $minute,
                        $fieldType,
                        $drawingData,
                        $this->matchTacticModel->getNextSortOrder($matchId),
                        (int)Session::get('user_id')
                    );
                    $this->logActivity('create_match_tactic', $matchId, $title);
                }

                $saved = $this->matchTacticModel->getByIdForMatch($savedId, $matchId);
                echo json_encode([
                    'success' => true,
                    'tactic' => $saved,
                    'tactics' => $this->matchTacticModel->getForMatch($matchId),
                ]);
            } else {
                $teamId = (int)($context['team_id'] ?? 0);
                if ($tacticId > 0) {
                    $existing = $this->matchTacticModel->getByIdForTeam($tacticId, $teamId);
                    if (!$existing) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Situatie niet gevonden.']);
                        exit;
                    }

                    $this->matchTacticModel->updateForTeam(
                        $tacticId,
                        $teamId,
                        $title,
                        $phase,
                        $minute,
                        $fieldType,
                        $drawingData
                    );
                    $savedId = $tacticId;
                    $this->logActivity('update_team_tactic', $teamId, $title);
                } else {
                    $savedId = $this->matchTacticModel->createForTeam(
                        $teamId,
                        $title,
                        $phase,
                        $minute,
                        $fieldType,
                        $drawingData,
                        $this->matchTacticModel->getNextSortOrderForTeamContext($teamId),
                        (int)Session::get('user_id')
                    );
                    $this->logActivity('create_team_tactic', $teamId, $title);
                }

                $saved = $this->matchTacticModel->getByIdForTeam($savedId, $teamId);
                echo json_encode([
                    'success' => true,
                    'tactic' => $saved,
                    'tactics' => $this->matchTacticModel->getForTeam($teamId),
                ]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Opslaan mislukt.']);
        }
    }

    public function deleteTactic(): void {
        header('Content-Type: application/json');

        if (!Session::has('user_id') || !Session::has('current_team')) {
            http_response_code(403);
            echo json_encode(['error' => 'Niet geautoriseerd.']);
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Methode niet toegestaan.']);
            exit;
        }

        $data = $this->decodeJsonBody();
        if (!is_array($data)) {
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

        $tacticId = (int)($data['tactic_id'] ?? 0);
        $context = $this->resolveTacticContext($data, false);
        if (!$context) {
            http_response_code(403);
            echo json_encode(['error' => 'Geen toegang tot deze tactiekcontext.']);
            exit;
        }

        if ($tacticId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Ongeldige situatie-ID.']);
            exit;
        }

        try {
            if (($context['mode'] ?? '') === MatchTactic::CONTEXT_MATCH) {
                $matchId = (int)($context['match_id'] ?? 0);
                $existing = $this->matchTacticModel->getByIdForMatch($tacticId, $matchId);
                if (!$existing) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Situatie niet gevonden.']);
                    exit;
                }

                $this->matchTacticModel->deleteForMatch($tacticId, $matchId);
                $this->logActivity('delete_match_tactic', $matchId, (string)$existing['title']);

                echo json_encode([
                    'success' => true,
                    'tactics' => $this->matchTacticModel->getForMatch($matchId),
                ]);
            } else {
                $teamId = (int)($context['team_id'] ?? 0);
                $existing = $this->matchTacticModel->getByIdForTeam($tacticId, $teamId);
                if (!$existing) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Situatie niet gevonden.']);
                    exit;
                }

                $this->matchTacticModel->deleteForTeam($tacticId, $teamId);
                $this->logActivity('delete_team_tactic', $teamId, (string)$existing['title']);

                echo json_encode([
                    'success' => true,
                    'tactics' => $this->matchTacticModel->getForTeam($teamId),
                ]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Verwijderen mislukt.']);
        }
    }

    public function exportTacticVideo(): void {
        if (!Session::has('user_id') || !Session::has('current_team')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Niet geautoriseerd.']);
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Methode niet toegestaan.']);
            exit;
        }

        $csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::verifyToken(is_string($csrfToken) ? $csrfToken : null)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Ongeldig CSRF-token.']);
            exit;
        }

        $context = $this->resolveTacticContext($_POST, true);
        if (!$context) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Geen toegang tot deze tactiekcontext.']);
            exit;
        }

        $upload = $_FILES['video'] ?? null;
        if (!is_array($upload)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Geen videobestand ontvangen.']);
            exit;
        }

        $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => $this->resolveUploadErrorMessage($uploadError)]);
            exit;
        }

        $uploadedTmpPath = is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : '';
        if ($uploadedTmpPath === '' || !is_uploaded_file($uploadedTmpPath)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Upload van videobestand is ongeldig.']);
            exit;
        }

        $uploadSize = (int)($upload['size'] ?? 0);
        if ($uploadSize <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Het geuploade videobestand is leeg.']);
            exit;
        }
        if ($uploadSize > (100 * 1024 * 1024)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Videobestand is te groot (max 100 MB).']);
            exit;
        }

        $title = is_string($_POST['title'] ?? null) ? trim((string)$_POST['title']) : '';
        $inputExtensionRaw = strtolower(trim((string)($_POST['input_extension'] ?? pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION))));
        $inputExtension = in_array($inputExtensionRaw, ['webm', 'mp4', 'mov'], true) ? $inputExtensionRaw : 'webm';

        $workDir = dirname(__DIR__, 2) . '/data/tmp/tactic-video-export/' . uniqid('exp_', true);
        if (!@mkdir($workDir, 0755, true) && !is_dir($workDir)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Kon tijdelijke exportmap niet aanmaken.']);
            exit;
        }

        $this->registerDirectoryCleanup($workDir);

        $inputPath = $workDir . '/input.' . $inputExtension;
        if (!move_uploaded_file($uploadedTmpPath, $inputPath)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Kon geuploade video niet verwerken.']);
            exit;
        }

        $outputPath = $workDir . '/output.mp4';
        if ($inputExtension === 'mp4') {
            if (!@copy($inputPath, $outputPath)) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Kon MP4-bestand niet voorbereiden voor download.']);
                exit;
            }
        } else {
            $transcoder = new MatchTacticVideoTranscoder();
            $result = $transcoder->transcodeToMp4($inputPath, $outputPath, 180);
            if (!($result['ok'] ?? false)) {
                $detail = trim((string)($result['error'] ?? ''));
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => $detail !== ''
                        ? ('MP4-conversie op server mislukt: ' . $detail)
                        : 'MP4-conversie op server mislukt.',
                ]);
                exit;
            }
        }

        clearstatcache(true, $outputPath);
        $outputSize = is_file($outputPath) ? (int)@filesize($outputPath) : 0;
        if ($outputSize <= 0) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'MP4-conversie leverde geen geldig bestand op.']);
            exit;
        }

        $downloadName = $this->buildTacticVideoFilename($title, 'mp4');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . $outputSize);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        readfile($outputPath);
        exit;
    }

    private function decodeJsonBody(): ?array {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
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

    private function canAccessCurrentTeam(int $teamId): bool {
        if (!Session::has('user_id') || !Session::has('current_team')) {
            return false;
        }

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

    private function resolveTacticContext(array $data, bool $allowImplicitTeam = false): ?array {
        $matchId = (int)($data['match_id'] ?? 0);
        if ($matchId > 0) {
            $match = $this->gameModel->getById($matchId);
            if (!$this->canAccessMatchInCurrentTeam($match)) {
                return null;
            }

            return [
                'mode' => MatchTactic::CONTEXT_MATCH,
                'match_id' => $matchId,
                'team_id' => (int)($match['team_id'] ?? 0),
            ];
        }

        $teamId = (int)($data['team_id'] ?? 0);
        if ($allowImplicitTeam && $teamId <= 0 && Session::has('current_team')) {
            $teamId = (int)(Session::get('current_team')['id'] ?? 0);
        }
        if ($teamId > 0 && $this->canAccessCurrentTeam($teamId)) {
            return [
                'mode' => MatchTactic::CONTEXT_TEAM,
                'match_id' => 0,
                'team_id' => $teamId,
            ];
        }

        return null;
    }

    private function resolveUploadErrorMessage(int $uploadErrorCode): string {
        switch ($uploadErrorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Geuploade video is te groot.';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload van video is onvolledig.';
            case UPLOAD_ERR_NO_FILE:
                return 'Geen videobestand geüpload.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Tijdelijke uploadmap ontbreekt op de server.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Kon videobestand niet wegschrijven op de server.';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload geblokkeerd door server-extensie.';
            default:
                return 'Onbekende uploadfout.';
        }
    }

    private function buildTacticVideoFilename(string $title, string $extension): string {
        $safeTitle = strtolower(trim($title));
        $safeTitle = preg_replace('/[^a-z0-9]+/', '-', $safeTitle) ?? '';
        $safeTitle = trim($safeTitle, '-');
        if ($safeTitle === '') {
            $safeTitle = 'situatie';
        }

        $timestamp = date('Ymd-His');
        return 'tactiek-' . $safeTitle . '-' . $timestamp . '.' . trim($extension);
    }

    private function registerDirectoryCleanup(string $dir): void {
        register_shutdown_function(static function () use ($dir): void {
            if (!is_dir($dir)) {
                return;
            }

            $entries = @scandir($dir);
            if (is_array($entries)) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    @unlink($dir . '/' . $entry);
                }
            }
            @rmdir($dir);
        });
    }
}
