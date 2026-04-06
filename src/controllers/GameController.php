<?php
declare(strict_types=1);

class GameController extends BaseController {
    private Game $gameModel;
    private Player $playerModel;
    private MatchTactic $matchTacticModel;
    private MatchTacticInputValidator $matchTacticInputValidator;
    private MatchLiveStateService $matchLiveStateService;
    private MatchSubstitutionService $matchSubstitutionService;

    private FormationTemplate $formationTemplateModel;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->gameModel = new Game($pdo);
        $this->playerModel = new Player($pdo);
        $this->matchTacticModel = new MatchTactic($pdo);
        $this->matchTacticInputValidator = new MatchTacticInputValidator();
        $this->matchLiveStateService = new MatchLiveStateService($pdo, $this->gameModel);
        $this->matchSubstitutionService = new MatchSubstitutionService($pdo, $this->gameModel, $this->matchLiveStateService);
        $this->formationTemplateModel = new FormationTemplate($pdo);
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

        $teamId = (int)Session::get('current_team')['id'];
        $formData = [
            'opponent' => trim((string)($_POST['opponent'] ?? '')),
            'date' => trim((string)($_POST['date'] ?? date('Y-m-d\TH:i'))),
            'is_home' => (string)($_POST['is_home'] ?? '1'),
            'formation' => trim((string)($_POST['formation'] ?? '11-vs-11')),
            'formation_template_id' => trim((string)($_POST['formation_template_id'] ?? '')),
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/matches/create');

            $validator = new Validator($_POST);
            $validator->required('opponent')->required('date');

            if ($validator->isValid()) {
                $isHome = (int)($formData['is_home'] ?? '1');
                $formation = $formData['formation'] !== '' ? $formData['formation'] : '11-vs-11';
                $opponent = $formData['opponent'];
                $date = $formData['date'];

                // Resolve formation_template_id and auto-derive format
                $templateId = $this->resolveFormationTemplateId($formData['formation_template_id'], $teamId);
                if ($templateId !== null) {
                    $template = $this->formationTemplateModel->getByIdForTeam($templateId, $teamId);
                    if ($template) {
                        $formation = $template['format'];
                    } else {
                        $templateId = null;
                    }
                }

                $matchId = $this->gameModel->create(
                    $teamId,
                    $opponent,
                    $date,
                    $isHome,
                    $formation,
                    $templateId
                );

                // Log activity
                $this->logActivity('create_match', $matchId, $opponent);

                Session::flash('success', 'Wedstrijd aangemaakt.');
                $this->redirect('/matches/view?id=' . $matchId);
            }
        }

        $speelwijzen = $this->formationTemplateModel->getForTeam($teamId);

        View::render('matches/form', [
            'formData' => $formData,
            'speelwijzen' => $speelwijzen,
            'pageTitle' => 'Nieuwe Wedstrijd - Trainer Bobby'
        ]);
    }

    public function edit(): void {
        $this->requireTeamContext();

        $teamId = (int)Session::get('current_team')['id'];
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $match = $this->gameModel->getById($id);

        if (!$this->canAccessMatchInCurrentTeam($match)) {
            $this->redirect('/matches');
        }

        $parsedMatchDate = strtotime((string)($match['date'] ?? ''));
        $defaultDate = $parsedMatchDate !== false ? date('Y-m-d\TH:i', $parsedMatchDate) : date('Y-m-d\TH:i');
        $formData = [
            'opponent' => trim((string)($_POST['opponent'] ?? ($match['opponent'] ?? ''))),
            'date' => trim((string)($_POST['date'] ?? $defaultDate)),
            'is_home' => (string)($_POST['is_home'] ?? (string)($match['is_home'] ?? '1')),
            'formation' => trim((string)($_POST['formation'] ?? ($match['formation'] ?? '11-vs-11'))),
            'formation_template_id' => trim((string)($_POST['formation_template_id'] ?? (string)($match['formation_template_id'] ?? ''))),
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf('/matches/edit?id=' . $id);

            $validator = new Validator($_POST);
            $validator->required('opponent')->required('date');

            if ($validator->isValid()) {
                $isHome = (int)($formData['is_home'] ?? '1');
                $formation = $formData['formation'] !== '' ? $formData['formation'] : '11-vs-11';
                $opponent = $formData['opponent'];
                $date = $formData['date'];

                // Resolve formation_template_id and auto-derive format
                $templateId = $this->resolveFormationTemplateId($formData['formation_template_id'], $teamId);
                if ($templateId !== null) {
                    $template = $this->formationTemplateModel->getByIdForTeam($templateId, $teamId);
                    if ($template) {
                        $formation = $template['format'];
                    } else {
                        $templateId = null;
                    }
                }

                $this->gameModel->updateMatch($id, $opponent, $date, $isHome, $formation, $templateId);

                // Log activity
                $this->logActivity('edit_match', $id, $opponent);

                Session::flash('success', 'Wedstrijd bijgewerkt.');
                $this->redirect('/matches/view?id=' . $id);
            }
        }

        $speelwijzen = $this->formationTemplateModel->getForTeam($teamId);

        View::render('matches/form', [
            'match' => $match,
            'formData' => $formData,
            'speelwijzen' => $speelwijzen,
            'pageTitle' => 'Wedstrijd Bewerken - Trainer Bobby'
        ]);
    }

    public function reports(): void {
        $this->requireTeamContext();

        $teamId = (int)Session::get('current_team')['id'];
        $allowedSort = ['name', 'matches', 'absent', 'starts', 'goals', 'goal_matches', 'keepers'];
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
            'extraCssFiles' => ['/css/reports.css'],
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

        $teamId = (int)Session::get('current_team')['id'];
        $players = $this->playerModel->getAllForTeam($teamId, 'name ASC');
        $matchPlayers = $this->gameModel->getPlayers($id);
        $events = $this->gameModel->getEvents($id);
        $matchTactics = $this->matchTacticModel->getForMatch($id);

        // Resolve template positions if a speelwijze is linked
        $templatePositions = null;
        $templateId = (int)($match['formation_template_id'] ?? 0);
        if ($templateId > 0) {
            $template = $this->formationTemplateModel->getByIdForTeam($templateId, $teamId);
            if ($template) {
                $templatePositions = json_decode($template['positions'], true);
            }
        }

        View::render('matches/view', [
            'match' => $match,
            'players' => $players,
            'matchPlayers' => $matchPlayers,
            'events' => $events,
            'matchTactics' => $matchTactics,
            'templatePositions' => $templatePositions,
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
        $events = $this->gameModel->getEvents($id);
        $timerState = $this->gameModel->getTimerState($id);
        $liveState = $this->matchLiveStateService->getLiveState($id);

        $settings = new AppSetting($this->pdo);
        // Fallback naar "aan" als key (nog) ontbreekt om regressie op bestaande live-flow te voorkomen.
        $liveVoiceEnabled = $settings->get('live_voice_enabled', '1') === '1';

        View::render('matches/live', [
            'match' => $match,
            'players' => $players,
            'events' => $events,
            'timerState' => $timerState,
            'liveState' => $liveState,
            'liveVoiceEnabled' => $liveVoiceEnabled,
            'bodyClass' => 'page-match-live',
            'viewportContent' => 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover',
            'pageTitle' => 'Live Wedstrijd - Trainer Bobby'
        ]);
    }

    public function timerAction(): void {
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

        $json = $this->decodeJsonBody();
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['error' => 'Ongeldige requestdata.']);
            exit;
        }

        $csrfToken = $json['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::verifyToken(is_string($csrfToken) ? $csrfToken : null)) {
            http_response_code(403);
            echo json_encode(['error' => 'Ongeldig CSRF-token.']);
            exit;
        }

        $matchId = (int)($json['match_id'] ?? 0);
        $action = strtolower(trim((string)($json['action'] ?? ''))); // start, stop
        if ($matchId <= 0 || !in_array($action, ['start', 'stop'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Ongeldige timer-actie.']);
            exit;
        }

        $match = $this->gameModel->getById($matchId);
        if (!$this->canAccessMatchInCurrentTeam($match)) {
            http_response_code(403);
            echo json_encode(['error' => 'Geen toegang tot deze wedstrijd.']);
            exit;
        }

        $timerState = $this->gameModel->getTimerState($matchId);
        $isPlaying = (bool)($timerState['is_playing'] ?? false);
        $minute = (int)($timerState['total_minutes'] ?? 0);
        $currentPeriod = (int)($timerState['current_period'] ?? 0);

        $autoSnapshotPeriod = null;
        $autoSnapshotSlots = [];
        $autoSnapshotSaved = false;
        $periodSnapshotReused = false;

        if ($action === 'start') {
            if ($isPlaying) {
                echo json_encode([
                    'success' => true,
                    'timerState' => $timerState,
                    'noop' => true,
                ]);
                return;
            }

            // Start of a new period: capture current lineup and snapshot it into the new period.
            $nextPeriod = max(1, $currentPeriod + 1);
            $sourcePeriod = $currentPeriod > 0 ? $currentPeriod : 1;
            $sourceClockSeconds = max(0, (int)($timerState['total_seconds'] ?? 0));
            $nextPeriodLiveState = $this->matchLiveStateService->getLiveStateAt(
                $matchId,
                $nextPeriod,
                $sourceClockSeconds,
                $timerState
            );
            if (!empty($nextPeriodLiveState['period_lineup_saved'])) {
                $periodSnapshotReused = true;
            } else {
                $sourceLiveState = $this->matchLiveStateService->getLiveStateAt(
                    $matchId,
                    $sourcePeriod,
                    $sourceClockSeconds,
                    $timerState
                );
                $autoSnapshotPeriod = $nextPeriod;
                $autoSnapshotSlots = $this->extractPeriodSlotsFromLiveState($sourceLiveState);
            }
            $this->gameModel->addEvent($matchId, $minute, 'whistle', null, 'start_period', $nextPeriod);
        } else {
            if (!$isPlaying) {
                echo json_encode([
                    'success' => true,
                    'timerState' => $timerState,
                    'noop' => true,
                ]);
                return;
            }

            $stopPeriod = max(1, $currentPeriod);
            $this->gameModel->addEvent($matchId, $minute, 'whistle', null, 'end_period', $stopPeriod);
        }

        if ($autoSnapshotPeriod !== null && !empty($autoSnapshotSlots)) {
            try {
                $this->matchLiveStateService->savePeriodLineup(
                    $matchId,
                    $autoSnapshotPeriod,
                    $autoSnapshotSlots,
                    (int)Session::get('user_id')
                );
                $autoSnapshotSaved = true;
                $this->logActivity('autosave_period_lineup', $matchId, 'periode ' . $autoSnapshotPeriod);
            } catch (Throwable $e) {
                $autoSnapshotSaved = false;
            }
        }

        $newState = $this->gameModel->getTimerState($matchId);
        echo json_encode([
            'success' => true,
            'timerState' => $newState,
            'period_snapshot_saved' => $autoSnapshotSaved,
            'period_snapshot_period' => $autoSnapshotSaved ? $autoSnapshotPeriod : null,
            'period_snapshot_reused' => $periodSnapshotReused,
        ]);
    }

    public function addEvent(): void {
        $this->requireAuth();
        $isJson = $this->isJsonRequest();

        if (!Session::has('current_team')) {
            if ($isJson) {
                http_response_code(403); 
                echo json_encode(['error' => 'Auth required']);
                exit;
            }
            $this->redirect('/');
        }

        $input = $_POST;
        if ($isJson) {
            $input = $this->decodeJsonBody();
            if (!is_array($input)) {
                http_response_code(400);
                echo json_encode(['error' => 'Ongeldige requestdata.']);
                exit;
            }
            $csrfToken = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Csrf::verifyToken(is_string($csrfToken) ? $csrfToken : null)) {
                http_response_code(403);
                echo json_encode(['error' => 'Ongeldig CSRF-token.']);
                exit;
            }
        } else {
            $this->verifyCsrf('/matches');
        }

        $matchId = (int)($input['match_id'] ?? 0);
        $type = $input['type'] ?? '';

        if ($matchId && $type) {
            $match = $this->gameModel->getById($matchId);
            if ($this->canAccessMatchInCurrentTeam($match)) {
                $minute = isset($input['minute']) && $input['minute'] !== '' ? (int)$input['minute'] : null;
                $rawPeriod = $input['period'] ?? null;
                $period = (is_numeric($rawPeriod) && (int)$rawPeriod > 0) ? (int)$rawPeriod : 0;

                // Always derive period from live timer state when caller omits/invalidates period.
                $state = $this->gameModel->getTimerState($matchId);
                $derivedPeriod = (int)($state['current_period'] ?? 0);
                if ($derivedPeriod <= 0) {
                    $derivedPeriod = 1;
                }
                if ($period <= 0) {
                    $period = $derivedPeriod;
                }
                if ($minute === null) {
                    $minute = (int)($state['total_minutes'] ?? 0);
                }

                // Sanitation: Ensure playerId is a positive integer or null.
                $rawPlayerId = $input['player_id'] ?? '';
                $playerId = (is_numeric($rawPlayerId) && (int)$rawPlayerId > 0) ? (int)$rawPlayerId : null;
                if ($playerId !== null && !$this->gameModel->allPlayersBelongToTeam([$playerId], (int)$match['team_id'])) {
                    if ($isJson) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Ongeldige speler voor deze wedstrijd.']);
                        return;
                    }
                    Session::flash('error', 'Ongeldige speler voor deze wedstrijd.');
                    $this->redirect('/matches/view?id=' . $matchId);
                }

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

    public function substitute(): void {
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

        $matchId = (int)($data['match_id'] ?? 0);
        $playerOutId = (int)($data['player_out_id'] ?? 0);
        $playerInId = (int)($data['player_in_id'] ?? 0);
        $slotCode = isset($data['slot_code']) ? (string)$data['slot_code'] : null;

        if ($matchId <= 0 || $playerOutId <= 0 || $playerInId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Match en spelergegevens zijn verplicht.']);
            exit;
        }

        $match = $this->gameModel->getById($matchId);
        if (!$this->canAccessMatchInCurrentTeam($match)) {
            http_response_code(403);
            echo json_encode(['error' => 'Geen toegang tot deze wedstrijd.']);
            exit;
        }

        try {
            $result = $this->matchSubstitutionService->applyManualSubstitution(
                $matchId,
                $playerOutId,
                $playerInId,
                $slotCode,
                (int)Session::get('user_id')
            );

            $sub = $result['substitution'] ?? null;
            if (is_array($sub)) {
                $this->logActivity('live_substitute', $matchId, (string)($sub['player_out_name'] ?? '') . ' -> ' . (string)($sub['player_in_name'] ?? ''));
            }

            echo json_encode([
                'success' => true,
                'substitution' => $result['substitution'],
                'active_lineup' => $result['active_lineup'],
                'bench' => $result['bench'],
                'period' => (int)$result['period'],
                'clock_seconds' => (int)$result['clock_seconds'],
                'period_lineup_saved' => !empty($result['period_lineup_saved']),
                'minutes_summary' => $result['minutes_summary'],
                'events' => $result['events'],
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Wissel verwerken mislukt.']);
        }
    }

    public function undoSubstitution(): void {
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

        $matchId = (int)($data['match_id'] ?? 0);
        if ($matchId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Match-ID is verplicht.']);
            exit;
        }

        $match = $this->gameModel->getById($matchId);
        if (!$this->canAccessMatchInCurrentTeam($match)) {
            http_response_code(403);
            echo json_encode(['error' => 'Geen toegang tot deze wedstrijd.']);
            exit;
        }

        try {
            $result = $this->matchSubstitutionService->undoLastSubstitution($matchId);
            $this->logActivity('live_substitute_undo', $matchId, 'substitution_id=' . (int)$result['undone_substitution_id']);

            echo json_encode([
                'success' => true,
                'undone_substitution_id' => (int)$result['undone_substitution_id'],
                'active_lineup' => $result['active_lineup'],
                'bench' => $result['bench'],
                'period' => (int)$result['period'],
                'clock_seconds' => (int)$result['clock_seconds'],
                'period_lineup_saved' => !empty($result['period_lineup_saved']),
                'minutes_summary' => $result['minutes_summary'],
                'events' => $result['events'],
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Undo van wissel mislukt.']);
        }
    }

    public function voiceCommand(): void {
        header('Content-Type: application/json');

        if (!Session::has('user_id') || !Session::has('current_team')) {
            http_response_code(403);
            echo json_encode(['error' => 'Niet geautoriseerd.']);
            exit;
        }

        $settings = new AppSetting($this->pdo);
        if ($settings->get('live_voice_enabled', '1') !== '1') {
            http_response_code(403);
            echo json_encode(['error' => 'Spraakfunctie is niet ingeschakeld.']);
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Methode niet toegestaan.']);
            exit;
        }

        $csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::verifyToken(is_string($csrfToken) ? $csrfToken : null)) {
            http_response_code(403);
            echo json_encode(['error' => 'Ongeldig CSRF-token.']);
            exit;
        }

        $matchId = (int)($_POST['match_id'] ?? 0);
        if ($matchId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Match-ID is verplicht.']);
            exit;
        }

        $match = $this->gameModel->getById($matchId);
        if (!$this->canAccessMatchInCurrentTeam($match)) {
            http_response_code(403);
            echo json_encode(['error' => 'Geen toegang tot deze wedstrijd.']);
            exit;
        }

        $audioFile = $_FILES['audio_file'] ?? null;
        if (!is_array($audioFile) || empty($audioFile['tmp_name']) || (int)($audioFile['error'] ?? 1) !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Audiobestand ontbreekt of is ongeldig.']);
            exit;
        }

        $audioTmpPath = (string)$audioFile['tmp_name'];
        $audioContents = @file_get_contents($audioTmpPath);
        if ($audioContents === false || $audioContents === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Audiobestand kon niet worden gelezen.']);
            exit;
        }

        $maxAudioBytes = 10 * 1024 * 1024; // 10 MB
        if (strlen($audioContents) > $maxAudioBytes) {
            http_response_code(400);
            echo json_encode(['error' => 'Audiobestand is te groot (max 10 MB).']);
            exit;
        }

        $mimeType = (string)($audioFile['type'] ?? 'audio/webm');
        $allowedMimeTypes = ['audio/webm', 'audio/wav', 'audio/wave', 'audio/x-wav', 'audio/mp3', 'audio/mpeg', 'audio/mp4', 'audio/m4a', 'audio/ogg'];
        $normalizedMime = strtolower(trim(explode(';', $mimeType)[0]));
        if (!in_array($normalizedMime, $allowedMimeTypes, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Audioformaat niet ondersteund.']);
            exit;
        }

        $userId = (int)Session::get('user_id');
        $teamId = (int)Session::get('current_team')['id'];
        $clientDurationMs = isset($_POST['client_duration_ms']) ? (int)$_POST['client_duration_ms'] : null;
        $audioBase64 = base64_encode($audioContents);

        // Get timer state for log context
        $timerState = $this->gameModel->getTimerState($matchId);
        $period = (int)($timerState['current_period'] ?? 0);
        if ($period <= 0) {
            $period = 1;
        }
        $clockSeconds = max(0, (int)($timerState['total_seconds'] ?? 0));

        // Build player context for LLM
        $liveState = $this->matchLiveStateService->getLiveState($matchId);
        $fieldPlayers = is_array($liveState['active_lineup'] ?? null) ? $liveState['active_lineup'] : [];
        $benchPlayers = is_array($liveState['bench'] ?? null) ? $liveState['bench'] : [];

        $fieldContext = [];
        foreach ($fieldPlayers as $p) {
            $fieldContext[] = [
                'id' => (int)($p['player_id'] ?? 0),
                'name' => (string)($p['player_name'] ?? ''),
                'number' => isset($p['number']) ? (int)$p['number'] : null,
                'slot_code' => (string)($p['slot_code'] ?? ''),
            ];
        }

        $benchContext = [];
        foreach ($benchPlayers as $p) {
            $benchContext[] = [
                'id' => (int)($p['player_id'] ?? 0),
                'name' => (string)($p['player_name'] ?? ''),
                'number' => isset($p['number']) ? (int)$p['number'] : null,
            ];
        }

        $aliases = $this->gameModel->getAliasesForTeam($teamId);
        $aliasContext = [];
        foreach ($aliases as $a) {
            $aliasContext[] = [
                'player_id' => (int)$a['player_id'],
                'alias' => (string)$a['alias'],
            ];
        }

        // 1. LLM interprets audio → structured events
        $sttService = new OpenRouterSttService($this->pdo);
        $sttResult = $sttService->interpretAudio($audioBase64, $normalizedMime, [
            'field_players' => $fieldContext,
            'bench_players' => $benchContext,
            'aliases' => $aliasContext,
            'period' => $period,
            'locale' => 'nl',
        ], $userId);

        if (!$sttResult['ok']) {
            $voiceLogId = $this->gameModel->createVoiceCommandLog([
                'match_id' => $matchId,
                'user_id' => $userId,
                'period' => $period,
                'clock_seconds' => $clockSeconds,
                'audio_duration_ms' => $clientDurationMs,
                'stt_model_id' => $sttResult['model_id'] ?? null,
                'raw_transcript' => null,
                'normalized_transcript' => null,
                'parsed_json' => null,
                'status' => 'error',
                'error_code' => $sttResult['error_code'] ?? 'stt_failed',
            ]);

            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => $sttResult['error'] ?? 'Spraakherkenning mislukt.',
                'error_code' => $sttResult['error_code'] ?? 'stt_failed',
                'voice_log_id' => $voiceLogId,
            ]);
            exit;
        }

        $transcript = (string)($sttResult['transcript'] ?? '');
        $llmEvents = is_array($sttResult['events'] ?? null) ? $sttResult['events'] : [];

        // 2. Validate LLM events against actual live state
        $validator = new VoiceCommandValidator();
        $validation = $validator->validate($llmEvents, $fieldPlayers, $benchPlayers);

        $parsedJson = json_encode($validation['events'], JSON_UNESCAPED_UNICODE);
        $status = 'rejected';
        if (!empty($validation['events'])) {
            $status = $validation['requires_confirmation'] ? 'needs_confirmation' : 'accepted';
        }

        $voiceLogId = $this->gameModel->createVoiceCommandLog([
            'match_id' => $matchId,
            'user_id' => $userId,
            'period' => $period,
            'clock_seconds' => $clockSeconds,
            'audio_duration_ms' => $clientDurationMs,
            'stt_model_id' => $sttResult['model_id'] ?? null,
            'raw_transcript' => $transcript,
            'normalized_transcript' => $sttResult['raw_response'] ?? $transcript,
            'parsed_json' => $parsedJson,
            'status' => $status,
        ]);

        $this->logActivity('live_voice_command', $matchId, 'transcript=' . mb_substr($transcript, 0, 100));

        echo json_encode([
            'success' => true,
            'voice_log_id' => $voiceLogId,
            'transcript' => $transcript,
            'events' => $validation['events'],
            'requires_confirmation' => $validation['requires_confirmation'],
            'reason' => $validation['reason'],
        ]);
    }

    public function voiceCommandConfirm(): void {
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

        $matchId = (int)($data['match_id'] ?? 0);
        $voiceLogId = (int)($data['voice_log_id'] ?? 0);
        $events = is_array($data['events'] ?? null) ? $data['events'] : [];

        if ($matchId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Match-ID is verplicht.']);
            exit;
        }

        if (empty($events)) {
            http_response_code(400);
            echo json_encode(['error' => 'Geen events om te bevestigen.']);
            exit;
        }

        $match = $this->gameModel->getById($matchId);
        if (!$this->canAccessMatchInCurrentTeam($match)) {
            http_response_code(403);
            echo json_encode(['error' => 'Geen toegang tot deze wedstrijd.']);
            exit;
        }

        $userId = (int)Session::get('user_id');
        $timerState = $this->gameModel->getTimerState($matchId);
        $period = (int)($timerState['current_period'] ?? 0);
        if ($period <= 0) {
            $period = 1;
        }
        $clockSeconds = max(0, (int)($timerState['total_seconds'] ?? 0));
        $minuteDisplay = max(1, (int)floor($clockSeconds / 60) + 1);

        $appliedEvents = [];
        $failedEvent = null;

        foreach ($events as $event) {
            if (!is_array($event) || empty($event['type'])) {
                continue;
            }

            $type = (string)$event['type'];

            try {
                $applied = match ($type) {
                    'substitution' => $this->commitSubstitutionEvent($matchId, $event, $userId),
                    'goal' => $this->commitMatchEvent($matchId, $minuteDisplay, $period, 'goal', $event),
                    'card' => $this->commitMatchEvent($matchId, $minuteDisplay, $period, 'card', $event),
                    'chance' => $this->commitMatchEvent($matchId, $minuteDisplay, $period, 'chance', $event),
                    'note' => $this->commitMatchEvent($matchId, $minuteDisplay, $period, 'note', $event),
                    default => null,
                };

                if ($applied !== null) {
                    $appliedEvents[] = $applied;
                }
            } catch (Throwable $e) {
                $failedEvent = [
                    'type' => $type,
                    'error' => $e->getMessage(),
                ];
                break;
            }
        }

        if (empty($appliedEvents) && $failedEvent !== null) {
            if ($voiceLogId > 0) {
                $this->gameModel->updateVoiceCommandLogStatus($voiceLogId, 'error', 'confirm_failed');
            }

            http_response_code(500);
            echo json_encode(['error' => 'Event verwerken mislukt: ' . $failedEvent['error']]);
            exit;
        }

        if ($voiceLogId > 0) {
            $this->gameModel->updateVoiceCommandLogStatus($voiceLogId, 'accepted');
        }

        $this->logActivity('live_voice_confirm', $matchId, count($appliedEvents) . ' events bevestigd');

        // Use live state from the last substitution result if available,
        // because substitutions while clock is stopped modify the next-period
        // lineup and a fresh getLiveState() would return the current period.
        $subState = null;
        foreach (array_reverse($appliedEvents) as $ae) {
            if (($ae['type'] ?? '') === 'substitution' && isset($ae['_live_state'])) {
                $subState = $ae['_live_state'];
                break;
            }
        }

        $liveState = $subState ?? $this->matchLiveStateService->getLiveState($matchId);

        // Strip internal _live_state from applied events before returning
        $cleanedAppliedEvents = array_map(function (array $ae): array {
            unset($ae['_live_state']);
            return $ae;
        }, $appliedEvents);

        $response = [
            'success' => true,
            'applied_events' => $cleanedAppliedEvents,
            'active_lineup' => $liveState['active_lineup'],
            'bench' => $liveState['bench'],
            'period' => (int)$liveState['period'],
            'clock_seconds' => (int)$liveState['clock_seconds'],
            'period_lineup_saved' => !empty($liveState['period_lineup_saved']),
            'minutes_summary' => $liveState['minutes_summary'],
            'events' => $this->gameModel->getEvents($matchId),
        ];

        if ($failedEvent !== null) {
            $response['warning'] = 'Niet alle events konden worden doorgevoerd.';
            $response['failed_event'] = $failedEvent;
        }

        echo json_encode($response);
    }

    private function commitSubstitutionEvent(int $matchId, array $event, int $userId): array {
        $playerOutId = (int)($event['player_out_id'] ?? 0);
        $playerInId = (int)($event['player_in_id'] ?? 0);

        if ($playerOutId <= 0 || $playerInId <= 0 || $playerOutId === $playerInId) {
            throw new InvalidArgumentException('Ongeldige speler-IDs voor wissel.');
        }

        $result = $this->matchSubstitutionService->applyManualSubstitution(
            $matchId,
            $playerOutId,
            $playerInId,
            null,
            $userId
        );

        return [
            'type' => 'substitution',
            'substitution' => $result['substitution'] ?? null,
            '_live_state' => $result,
        ];
    }

    private function commitMatchEvent(int $matchId, int $minute, int $period, string $type, array $event): array {
        $playerId = (int)($event['player_id'] ?? 0);
        $description = $this->buildEventDescription($type, $event);

        $eventType = $type;
        if ($type === 'card') {
            $cardType = (string)($event['card_type'] ?? 'yellow');
            $eventType = $cardType === 'red' ? 'red_card' : 'yellow_card';
        }

        $this->gameModel->addEvent(
            $matchId,
            $minute,
            $eventType,
            $playerId > 0 ? $playerId : null,
            $description,
            $period
        );

        return [
            'type' => $type,
            'event_type' => $eventType,
            'player_id' => $playerId > 0 ? $playerId : null,
            'description' => $description,
        ];
    }

    private function buildEventDescription(string $type, array $event): string {
        $playerName = trim((string)($event['player_name'] ?? ''));

        return match ($type) {
            'goal' => $playerName !== ''
                ? 'Doelpunt: ' . $playerName . (isset($event['assist_player_name']) && trim((string)$event['assist_player_name']) !== '' ? ' (assist: ' . trim((string)$event['assist_player_name']) . ')' : '')
                : 'Doelpunt',
            'card' => $playerName !== ''
                ? (((string)($event['card_type'] ?? '') === 'red') ? 'Rode kaart' : 'Gele kaart') . ': ' . $playerName
                : (((string)($event['card_type'] ?? '') === 'red') ? 'Rode kaart' : 'Gele kaart'),
            'chance' => $playerName !== ''
                ? 'Kans: ' . $playerName . (isset($event['detail']) && trim((string)$event['detail']) !== '' ? ' - ' . trim((string)$event['detail']) : '')
                : 'Kans' . (isset($event['detail']) && trim((string)$event['detail']) !== '' ? ': ' . trim((string)$event['detail']) : ''),
            'note' => trim((string)($event['text'] ?? 'Notitie')),
            default => $playerName !== '' ? $type . ': ' . $playerName : $type,
        };
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

    protected function decodeJsonBody(): ?array {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function isJsonRequest(): bool {
        $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
        if ($contentType === '') {
            return false;
        }

        $mimeType = trim((string)explode(';', $contentType, 2)[0]);
        return $mimeType === 'application/json';
    }

    private function extractPeriodSlotsFromLiveState(array $liveState): array {
        $activeLineup = is_array($liveState['active_lineup'] ?? null) ? $liveState['active_lineup'] : [];
        $slots = [];
        $seenSlots = [];
        $seenPlayers = [];

        foreach ($activeLineup as $lineupItem) {
            if (!is_array($lineupItem)) {
                continue;
            }

            $slotCode = MatchSlotCode::sanitize((string)($lineupItem['slot_code'] ?? ''));
            $playerId = (int)($lineupItem['player_id'] ?? 0);
            if ($slotCode === '' || $playerId <= 0) {
                continue;
            }
            if (isset($seenSlots[$slotCode]) || isset($seenPlayers[$playerId])) {
                continue;
            }

            $slots[] = [
                'slot_code' => $slotCode,
                'player_id' => $playerId,
            ];
            $seenSlots[$slotCode] = true;
            $seenPlayers[$playerId] = true;
        }

        usort($slots, static function (array $a, array $b): int {
            return strcmp((string)$a['slot_code'], (string)$b['slot_code']);
        });

        return $slots;
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

    private function resolveFormationTemplateId(string $rawValue, int $teamId): ?int {
        $id = (int)$rawValue;
        return $id > 0 ? $id : null;
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

        return $safeTitle . '.' . trim($extension);
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
