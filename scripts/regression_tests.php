<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/Session.php';

spl_autoload_register(function (string $class): void {
    $class = str_replace('\\', '/', $class);
    $base = __DIR__ . '/../src/';

    $paths = [
        $base . $class . '.php',
        $base . 'models/' . $class . '.php',
        $base . 'controllers/' . $class . '.php',
        $base . 'services/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

if (is_dir('/tmp')) {
    session_save_path('/tmp');
}
Session::start();
if (ob_get_level() === 0) {
    ob_start();
}

class RedirectIntercept extends RuntimeException {
    public function __construct(public string $path) {
        parent::__construct($path);
    }
}

class TestExerciseController extends ExerciseController {
    protected function redirect(string $path): void {
        throw new RedirectIntercept($path);
    }

    protected function logActivity(string $action, ?int $entityId = null, ?string $details = null): void {
        // No-op in tests.
    }
}

class TestGameController extends GameController {
    private ?array $forcedJsonBody = null;
    private bool $forceJsonRequest = false;

    public function setJsonBody(?array $body): void {
        $this->forcedJsonBody = $body;
    }

    public function setJsonRequest(bool $isJson): void {
        $this->forceJsonRequest = $isJson;
    }

    protected function decodeJsonBody(): ?array {
        if ($this->forcedJsonBody !== null || $this->forceJsonRequest) {
            return $this->forcedJsonBody;
        }
        return parent::decodeJsonBody();
    }

    protected function isJsonRequest(): bool {
        if ($this->forceJsonRequest) {
            return true;
        }
        return parent::isJsonRequest();
    }

    protected function redirect(string $path): void {
        throw new RedirectIntercept($path);
    }

    protected function logActivity(string $action, ?int $entityId = null, ?string $details = null): void {
        // No-op in tests.
    }
}

function createTestPdo(): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function createSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            name TEXT NOT NULL,
            is_admin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE teams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            invite_code TEXT UNIQUE,
            club TEXT DEFAULT '',
            season TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE clubs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            logo_path TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE team_members (
            user_id INTEGER NOT NULL,
            team_id INTEGER NOT NULL,
            is_coach INTEGER DEFAULT 0,
            is_trainer INTEGER DEFAULT 0,
            is_hidden INTEGER DEFAULT 0,
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, team_id)
        )
    ");

    $pdo->exec("
        CREATE TABLE exercises (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_id INTEGER,
            title TEXT NOT NULL,
            description TEXT,
            team_task TEXT,
            training_objective TEXT,
            football_action TEXT,
            min_players INTEGER,
            max_players INTEGER,
            duration INTEGER,
            requirements TEXT,
            variation TEXT,
            image_path TEXT,
            drawing_data TEXT,
            field_type TEXT DEFAULT 'portrait',
            created_by INTEGER,
            source TEXT DEFAULT NULL,
            coach_instructions TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE trainings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            training_date DATE,
            start_time TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE training_exercises (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            training_id INTEGER NOT NULL,
            exercise_id INTEGER NOT NULL,
            sort_order INTEGER NOT NULL,
            duration INTEGER,
            goal TEXT,
            FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
            FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE exercise_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            exercise_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            comment TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE exercise_reactions (
            exercise_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            reaction_type TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (exercise_id, user_id)
        )
    ");
}

function seedData(PDO $pdo): void {
    $pdo->exec("
        INSERT INTO users (id, username, password_hash, name, is_admin) VALUES
        (1, 'trainer', 'x', 'Trainer User', 0),
        (2, 'player',  'x', 'Player User', 0),
        (3, 'admin',   'x', 'Admin User', 1)
    ");

    $pdo->exec("
        INSERT INTO teams (id, name, invite_code, club, season) VALUES
        (1, 'Team A', 'invA', '', ''),
        (2, 'Team B', 'invB', '', '')
    ");

    $pdo->exec("
        INSERT INTO team_members (user_id, team_id, is_coach, is_trainer, is_hidden) VALUES
        (1, 1, 0, 1, 0),
        (2, 1, 0, 0, 0),
        (3, 1, 0, 0, 0)
    ");

    $pdo->exec("
        INSERT INTO exercises (id, team_id, title, description, duration, created_by) VALUES
        (1, 1, 'Oefening 1', 'Beschrijving 1', 10, 1),
        (2, 1, 'Oefening 2', 'Beschrijving 2', 12, 1)
    ");

    $pdo->exec("
        INSERT INTO trainings (id, team_id, title, description, training_date) VALUES
        (1, 1, 'Training A', 'Beschrijving A', '2026-02-27'),
        (2, 2, 'Training B', 'Beschrijving B', '2026-02-28')
    ");

    $pdo->exec("
        INSERT INTO training_exercises (training_id, exercise_id, sort_order, duration) VALUES
        (1, 1, 0, 10)
    ");
}

function resetRequestState(): void {
    $_GET = [];
    $_POST = [];
    $_SESSION = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../public') ?: (__DIR__ . '/../public');
}

function loginAs(int $userId, string $name, bool $isAdmin = false): void {
    Session::set('user_id', $userId);
    Session::set('user_name', $name);
    Session::set('is_admin', $isAdmin);
}

function setCurrentTeam(int $teamId, string $teamName = 'Team A'): void {
    Session::set('current_team', [
        'id' => $teamId,
        'name' => $teamName,
    ]);
}

function setPost(array $data): void {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $data;
    $_POST['csrf_token'] = Csrf::getToken();
}

function captureRedirect(callable $fn): string {
    try {
        $fn();
    } catch (RedirectIntercept $e) {
        return $e->path;
    }
    throw new RuntimeException('Expected redirect but none was triggered.');
}

function assertSame(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . " (expected: " . var_export($expected, true) . ", actual: " . var_export($actual, true) . ")");
    }
}

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function fetchValue(PDO $pdo, string $sql, array $params = []): mixed {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function withFreshContext(callable $fn): void {
    resetRequestState();
    $pdo = createTestPdo();
    createSchema($pdo);
    seedData($pdo);
    $fn($pdo);
}

function createLiveSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            name TEXT NOT NULL,
            is_admin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE teams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE team_members (
            user_id INTEGER NOT NULL,
            team_id INTEGER NOT NULL,
            is_coach INTEGER DEFAULT 0,
            is_trainer INTEGER DEFAULT 0,
            PRIMARY KEY (user_id, team_id)
        )
    ");

    $pdo->exec("
        CREATE TABLE matches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_id INTEGER NOT NULL,
            opponent TEXT NOT NULL,
            date TEXT,
            is_home INTEGER DEFAULT 1,
            score_home INTEGER DEFAULT 0,
            score_away INTEGER DEFAULT 0,
            formation TEXT DEFAULT ''
        )
    ");

    $pdo->exec("
        CREATE TABLE players (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            number INTEGER
        )
    ");

    $pdo->exec("
        CREATE TABLE match_players (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            match_id INTEGER NOT NULL,
            player_id INTEGER NOT NULL,
            position_x REAL DEFAULT 0,
            position_y REAL DEFAULT 0,
            is_substitute INTEGER DEFAULT 0,
            is_keeper INTEGER DEFAULT 0,
            is_absent INTEGER DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE match_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            match_id INTEGER NOT NULL,
            minute INTEGER NOT NULL,
            type TEXT NOT NULL,
            player_id INTEGER,
            description TEXT,
            period INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE match_period_lineups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            match_id INTEGER NOT NULL,
            period INTEGER NOT NULL,
            slot_code TEXT NOT NULL,
            player_id INTEGER NOT NULL,
            created_by INTEGER NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE match_substitutions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            match_id INTEGER NOT NULL,
            period INTEGER NOT NULL,
            clock_seconds INTEGER NOT NULL,
            minute_display INTEGER NOT NULL,
            slot_code TEXT NOT NULL,
            player_out_id INTEGER NOT NULL,
            player_in_id INTEGER NOT NULL,
            source TEXT NOT NULL DEFAULT 'manual',
            raw_transcript TEXT NULL,
            transcript_confidence REAL NULL,
            stt_model_id TEXT NULL,
            created_by INTEGER NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

function seedLiveData(PDO $pdo): void {
    $pdo->exec("
        INSERT INTO users (id, username, password_hash, name, is_admin) VALUES
        (1, 'trainer', 'x', 'Trainer User', 0)
    ");

    $pdo->exec("
        INSERT INTO teams (id, name) VALUES
        (1, 'Team A'),
        (2, 'Team B')
    ");

    $pdo->exec("
        INSERT INTO team_members (user_id, team_id, is_coach, is_trainer) VALUES
        (1, 1, 1, 1)
    ");

    $pdo->exec("
        INSERT INTO matches (id, team_id, opponent, date, is_home, formation)
        VALUES (1, 1, 'Test Opponent', '2026-03-27', 1, '')
    ");

    $pdo->exec("
        INSERT INTO players (id, team_id, name, number) VALUES
        (1, 1, 'GK', 1),
        (2, 1, 'A', 2),
        (3, 1, 'B', 3),
        (4, 1, 'C', 4),
        (5, 1, 'D', 5),
        (6, 1, 'E', 6),
        (7, 1, 'F', 7)
    ");

    $pdo->exec("
        INSERT INTO match_players (match_id, player_id, position_x, position_y, is_substitute, is_keeper, is_absent) VALUES
        (1, 1, 50, 92, 0, 1, 0),
        (1, 2, 20, 20, 0, 0, 0),
        (1, 3, 80, 20, 0, 0, 0),
        (1, 4, 50, 45, 0, 0, 0),
        (1, 5, 20, 70, 0, 0, 0),
        (1, 6, 80, 70, 0, 0, 0),
        (1, 7, 50, 50, 1, 0, 0)
    ");
}

function withFreshLiveContext(callable $fn): void {
    resetRequestState();
    $pdo = createTestPdo();
    createLiveSchema($pdo);
    seedLiveData($pdo);
    $fn($pdo);
}

function slotForPlayer(array $lineupMap, int $playerId): ?string {
    foreach ($lineupMap as $slotCode => $mappedPlayerId) {
        if ((int)$mappedPlayerId === $playerId) {
            return (string)$slotCode;
        }
    }
    return null;
}

function activePositionForPlayer(array $activeLineup, int $playerId): ?array {
    foreach ($activeLineup as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((int)($row['player_id'] ?? 0) !== $playerId) {
            continue;
        }
        return [
            'x' => isset($row['position_x']) ? (float)$row['position_x'] : 0.0,
            'y' => isset($row['position_y']) ? (float)$row['position_y'] : 0.0,
        ];
    }
    return null;
}

function expectedSixVsSixAnchor(string $slotCode): ?array {
    $normalized = strtoupper(trim($slotCode));
    $alias = [
        'K' => 'GK',
        'S01' => 'LV',
        'S02' => 'RV',
        'S03' => 'M',
        'S04' => 'LA',
        'S05' => 'RA',
        'S06' => 'GK',
    ];
    $canonicalSlot = $alias[$normalized] ?? $normalized;

    $anchors = [
        'GK' => ['x' => 50.0, 'y' => 88.0],
        'LA' => ['x' => 20.0, 'y' => 65.0],
        'RA' => ['x' => 80.0, 'y' => 65.0],
        'M' => ['x' => 50.0, 'y' => 45.0],
        'LV' => ['x' => 20.0, 'y' => 20.0],
        'RV' => ['x' => 80.0, 'y' => 20.0],
    ];

    return $anchors[$canonicalSlot] ?? null;
}

$tests = [];

$tests['addToTraining allows trainer and writes row'] = function (): void {
    withFreshContext(function (PDO $pdo): void {
        loginAs(1, 'Trainer User', false);
        setPost([
            'exercise_id' => '2',
            'training_id' => '1',
            'duration' => '15',
        ]);

        $controller = new TestExerciseController($pdo);
        $redirect = captureRedirect(fn() => $controller->addToTraining());

        assertSame('/exercises/view?id=2', $redirect, 'Unexpected redirect path');
        assertSame(1, (int)fetchValue($pdo, "SELECT COUNT(*) FROM training_exercises WHERE training_id = 1 AND exercise_id = 2"), 'Exercise should be added once');
        assertSame(1, (int)fetchValue($pdo, "SELECT sort_order FROM training_exercises WHERE training_id = 1 AND exercise_id = 2"), 'Expected append sort_order');
        assertTrue(Session::hasFlash('success'), 'Expected success flash for successful add');
        assertTrue(!Session::hasFlash('error'), 'Unexpected error flash for successful add');
    });
};

$tests['addToTraining blocks non-staff user'] = function (): void {
    withFreshContext(function (PDO $pdo): void {
        loginAs(2, 'Player User', false);
        setPost([
            'exercise_id' => '2',
            'training_id' => '1',
            'from_training' => '1',
            'duration' => '12',
        ]);

        $controller = new TestExerciseController($pdo);
        $redirect = captureRedirect(fn() => $controller->addToTraining());

        assertSame('/exercises/view?id=2&from_training=1', $redirect, 'Unauthorized user should return to exercise view context');
        assertSame(0, (int)fetchValue($pdo, "SELECT COUNT(*) FROM training_exercises WHERE training_id = 1 AND exercise_id = 2"), 'Unauthorized user must not add exercise');
        assertTrue(Session::hasFlash('error'), 'Expected error flash for unauthorized add');
    });
};

$tests['addToTraining handles invalid exercise id without 500'] = function (): void {
    withFreshContext(function (PDO $pdo): void {
        loginAs(1, 'Trainer User', false);
        setPost([
            'exercise_id' => '999',
            'training_id' => '1',
        ]);

        $controller = new TestExerciseController($pdo);
        $redirect = captureRedirect(fn() => $controller->addToTraining());

        assertSame('/exercises', $redirect, 'Invalid exercise should redirect to exercises overview');
        assertTrue(Session::hasFlash('error'), 'Expected error flash for invalid exercise');
        assertSame(1, (int)fetchValue($pdo, "SELECT COUNT(*) FROM training_exercises WHERE training_id = 1"), 'No additional exercises should be inserted');
    });
};

$tests['from_training persists across comment/reaction/addToTraining'] = function (): void {
    withFreshContext(function (PDO $pdo): void {
        loginAs(1, 'Trainer User', false);
        $controller = new TestExerciseController($pdo);

        setPost([
            'exercise_id' => '1',
            'from_training' => '1',
            'comment' => 'Goede oefening',
        ]);
        $commentRedirect = captureRedirect(fn() => $controller->storeComment());
        assertSame('/exercises/view?id=1&from_training=1', $commentRedirect, 'Comment should preserve from_training');
        assertSame(1, (int)fetchValue($pdo, "SELECT COUNT(*) FROM exercise_comments WHERE exercise_id = 1"), 'Comment should be saved');

        setPost([
            'exercise_id' => '1',
            'from_training' => '1',
            'type' => 'rock',
        ]);
        $reactionRedirect = captureRedirect(fn() => $controller->toggleReaction());
        assertSame('/exercises/view?id=1&from_training=1', $reactionRedirect, 'Reaction should preserve from_training');
        assertSame(1, (int)fetchValue($pdo, "SELECT COUNT(*) FROM exercise_reactions WHERE exercise_id = 1 AND user_id = 1"), 'Reaction should be saved');

        setPost([
            'exercise_id' => '2',
            'training_id' => '1',
            'from_training' => '1',
            'duration' => '8',
        ]);
        $addRedirect = captureRedirect(fn() => $controller->addToTraining());
        assertSame('/exercises/view?id=2&from_training=1', $addRedirect, 'Add to training should preserve from_training');
    });
};

$tests['invalid from_training falls back to /exercises in view'] = function (): void {
    withFreshContext(function (PDO $pdo): void {
        loginAs(1, 'Trainer User', false);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [
            'id' => '2',
            'from_training' => '1', // user has access, but exercise 2 is NOT in training 1
        ];

        $controller = new TestExerciseController($pdo);
        ob_start();
        try {
            $controller->view();
            $html = (string)ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        if (!preg_match('/<div class="app-bar-start">.*?<a href="([^"]+)" class="btn-icon-round"/s', $html, $match)) {
            throw new RuntimeException('Could not locate back link in rendered exercise view');
        }

        assertSame('/exercises', $match[1], 'Invalid from_training should fall back to /exercises');
        assertTrue(strpos($html, 'name="from_training"') === false, 'Invalid from_training should not remain in hidden form fields');
    });
};

$tests['non-POST reaction route has safe fallback redirect'] = function (): void {
    withFreshContext(function (PDO $pdo): void {
        loginAs(1, 'Trainer User', false);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [
            'id' => '1',
            'from_training' => '1',
        ];

        $controller = new TestExerciseController($pdo);
        $redirect = captureRedirect(fn() => $controller->toggleReaction());
        assertSame('/exercises/view?id=1&from_training=1', $redirect, 'Non-POST reaction route should redirect safely');
    });
};

$tests['live mode supports back-to-back field swaps and undo order'] = function (): void {
    withFreshLiveContext(function (PDO $pdo): void {
        $gameModel = new Game($pdo);
        $liveStateService = new MatchLiveStateService($pdo, $gameModel);
        $substitutionService = new MatchSubstitutionService($pdo, $gameModel, $liveStateService);

        $pdo->exec("
            INSERT INTO match_events (match_id, minute, type, player_id, description, period, created_at)
            VALUES (1, 0, 'whistle', NULL, 'start_period', 1, datetime('now', '-10 seconds'))
        ");

        $initial = $liveStateService->getLiveState(1);
        assertSame('S01', slotForPlayer($initial['lineup_map'], 2), 'Player A should start at S01');
        assertSame('S02', slotForPlayer($initial['lineup_map'], 3), 'Player B should start at S02');
        assertSame('S03', slotForPlayer($initial['lineup_map'], 4), 'Player C should start at S03');

        $substitutionService->applyManualSubstitution(1, 2, 3, null, 1);
        $afterFirstSwap = $liveStateService->getLiveState(1);
        assertSame('S02', slotForPlayer($afterFirstSwap['lineup_map'], 2), 'After first swap, A should be at S02');
        assertSame('S01', slotForPlayer($afterFirstSwap['lineup_map'], 3), 'After first swap, B should be at S01');

        $substitutionService->applyManualSubstitution(1, 3, 4, null, 1);
        $afterSecondSwap = $liveStateService->getLiveState(1);
        assertSame('S03', slotForPlayer($afterSecondSwap['lineup_map'], 3), 'After second swap, B should be at S03');
        assertSame('S01', slotForPlayer($afterSecondSwap['lineup_map'], 4), 'After second swap, C should be at S01');
        assertSame(2, (int)fetchValue($pdo, "SELECT COUNT(*) FROM match_substitutions WHERE match_id = 1"), 'Expected two substitution records');
        assertSame(1, (int)fetchValue($pdo, "SELECT COUNT(DISTINCT minute_display) FROM match_substitutions WHERE match_id = 1"), 'Both swaps should share one minute_display value');

        $substitutionService->undoLastSubstitution(1);
        $afterUndoOne = $liveStateService->getLiveState(1);
        assertSame('S01', slotForPlayer($afterUndoOne['lineup_map'], 3), 'After first undo, B should return to S01');
        assertSame('S03', slotForPlayer($afterUndoOne['lineup_map'], 4), 'After first undo, C should return to S03');
        assertSame(1, (int)fetchValue($pdo, "SELECT COUNT(*) FROM match_substitutions WHERE match_id = 1"), 'After first undo one substitution should remain');

        $substitutionService->undoLastSubstitution(1);
        $afterUndoTwo = $liveStateService->getLiveState(1);
        assertSame('S01', slotForPlayer($afterUndoTwo['lineup_map'], 2), 'After second undo, A should be back at S01');
        assertSame('S02', slotForPlayer($afterUndoTwo['lineup_map'], 3), 'After second undo, B should be back at S02');
        assertSame('S03', slotForPlayer($afterUndoTwo['lineup_map'], 4), 'After second undo, C should be back at S03');
        assertSame(0, (int)fetchValue($pdo, "SELECT COUNT(*) FROM match_substitutions WHERE match_id = 1"), 'After second undo no substitutions should remain');
    });
};

$tests['live mode plans next period lineup while clock is stopped'] = function (): void {
    withFreshLiveContext(function (PDO $pdo): void {
        $gameModel = new Game($pdo);
        $liveStateService = new MatchLiveStateService($pdo, $gameModel);
        $substitutionService = new MatchSubstitutionService($pdo, $gameModel, $liveStateService);

        $pdo->exec("
            INSERT INTO match_events (match_id, minute, type, player_id, description, period, created_at)
            VALUES
                (1, 0, 'whistle', NULL, 'start_period', 1, datetime('now', '-300 seconds')),
                (1, 5, 'whistle', NULL, 'end_period', 1, datetime('now', '-60 seconds'))
        ");

        $timerState = $gameModel->getTimerState(1);
        assertTrue(empty($timerState['is_playing']), 'Timer should be stopped for planning flow.');

        $result = $substitutionService->applyManualSubstitution(1, 2, 7, null, 1);
        assertSame(null, $result['substitution'] ?? null, 'Paused lineup change should not create a substitution payload.');
        assertSame(2, (int)($result['planned_period'] ?? 0), 'Paused lineup change should target the next period.');

        assertSame(0, (int)fetchValue($pdo, "SELECT COUNT(*) FROM match_substitutions WHERE match_id = 1"), 'No substitution rows should be written while clock is stopped.');
        assertSame(0, (int)fetchValue($pdo, "SELECT COUNT(*) FROM match_events WHERE match_id = 1 AND type = 'sub'"), 'No substitution timeline events should be written while clock is stopped.');

        $clockSeconds = max(0, (int)($timerState['total_seconds'] ?? 0));
        $plannedState = $liveStateService->getLiveStateAt(1, 2, $clockSeconds, $timerState);
        assertSame('S01', slotForPlayer($plannedState['lineup_map'], 7), 'Bench player should be placed in the outgoing slot for next period.');
        assertSame(null, slotForPlayer($plannedState['lineup_map'], 2), 'Outgoing player should no longer be in the planned next-period lineup.');
    });
};

$tests['live timer start reuses planned period lineup without overwrite'] = function (): void {
    withFreshLiveContext(function (PDO $pdo): void {
        loginAs(1, 'Trainer User', false);
        setCurrentTeam(1, 'Team A');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/matches/timer-action';

        $pdo->exec("
            INSERT INTO match_events (match_id, minute, type, player_id, description, period, created_at)
            VALUES
                (1, 0, 'whistle', NULL, 'start_period', 1, datetime('now', '-300 seconds')),
                (1, 5, 'whistle', NULL, 'end_period', 1, datetime('now', '-60 seconds'))
        ");

        $gameModel = new Game($pdo);
        $liveStateService = new MatchLiveStateService($pdo, $gameModel);
        $substitutionService = new MatchSubstitutionService($pdo, $gameModel, $liveStateService);
        $substitutionService->applyManualSubstitution(1, 2, 7, null, 1);

        $controller = new TestGameController($pdo);
        $controller->setJsonRequest(true);
        $controller->setJsonBody([
            'match_id' => 1,
            'action' => 'start',
            'csrf_token' => Csrf::getToken(),
        ]);

        ob_start();
        try {
            $controller->timerAction();
            $response = (string)ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $payload = json_decode($response, true);
        assertTrue(is_array($payload), 'Timer response should be valid JSON.');
        assertTrue(!empty($payload['success']), 'Timer start should succeed.');
        assertTrue(!empty($payload['period_snapshot_reused']), 'Existing planned lineup should be reused.');
        assertTrue(empty($payload['period_snapshot_saved']), 'No auto-snapshot write is needed when lineup already exists.');

        assertSame(7, (int)fetchValue(
            $pdo,
            "SELECT player_id FROM match_period_lineups WHERE match_id = 1 AND period = 2 AND slot_code = 'S01' LIMIT 1"
        ), 'Pre-planned period lineup should remain untouched when starting the period.');

        $timerState = $gameModel->getTimerState(1);
        assertTrue(!empty($timerState['is_playing']), 'Timer should be running after start.');
        assertSame(2, (int)($timerState['current_period'] ?? 0), 'Timer should advance to period 2.');
    });
};

$tests['live mode fallback slot positioning uses canonical six-vs-six anchor'] = function (): void {
    withFreshLiveContext(function (PDO $pdo): void {
        // Create one invalid field coordinate so this slot must use fallback positioning.
        $pdo->exec("
            UPDATE match_players
            SET position_x = 100, position_y = 60
            WHERE match_id = 1
              AND player_id = 5
        ");

        $gameModel = new Game($pdo);
        $liveStateService = new MatchLiveStateService($pdo, $gameModel);
        $liveState = $liveStateService->getLiveState(1);

        $playerPosition = activePositionForPlayer($liveState['active_lineup'] ?? [], 5);
        assertTrue(is_array($playerPosition), 'Fallback-positioned player should be present in active lineup');

        $slotCode = slotForPlayer($liveState['lineup_map'] ?? [], 5);
        assertTrue(is_string($slotCode) && $slotCode !== '', 'Player should still be mapped to a live slot');
        $expectedAnchor = expectedSixVsSixAnchor((string)$slotCode);
        assertTrue(is_array($expectedAnchor), 'Expected canonical six-vs-six anchor for slot ' . (string)$slotCode);

        $xDiff = abs(((float)$playerPosition['x']) - ((float)$expectedAnchor['x']));
        $yDiff = abs(((float)$playerPosition['y']) - ((float)$expectedAnchor['y']));
        assertTrue($xDiff < 0.001, 'Fallback X should match canonical anchor for slot ' . (string)$slotCode);
        assertTrue($yDiff < 0.001, 'Fallback Y should match canonical anchor for slot ' . (string)$slotCode);
    });
};

$tests['live mode keeps six-vs-six slot positions valid and unique with legacy slot mix'] = function (): void {
    withFreshLiveContext(function (PDO $pdo): void {
        $pdo->exec("UPDATE matches SET formation = '6-vs-6' WHERE id = 1");
        $pdo->exec("
            UPDATE match_players
            SET position_x = 0, position_y = 0
            WHERE match_id = 1
              AND player_id IN (2, 3, 4, 5, 6)
        ");

        $pdo->exec("DELETE FROM match_period_lineups WHERE match_id = 1");
        $pdo->exec("
            INSERT INTO match_period_lineups (match_id, period, slot_code, player_id) VALUES
            (1, 1, 'GK', 1),
            (1, 1, 'S01', 2),
            (1, 1, 'S02', 3),
            (1, 1, 'S03', 4),
            (1, 1, 'S04', 5),
            (1, 1, 'S06', 6)
        ");

        $gameModel = new Game($pdo);
        $liveStateService = new MatchLiveStateService($pdo, $gameModel);
        $liveState = $liveStateService->getLiveStateAt(1, 1, 0, [
            'current_period' => 1,
            'total_seconds' => 0,
        ]);

        $seenPositionKeys = [];
        foreach (($liveState['active_lineup'] ?? []) as $row) {
            $slotCode = (string)($row['slot_code'] ?? '');
            $x = isset($row['position_x']) ? (float)$row['position_x'] : -1.0;
            $y = isset($row['position_y']) ? (float)$row['position_y'] : -1.0;

            assertTrue($x >= 1.0 && $x <= 99.0, 'X should stay within field bounds for slot ' . $slotCode);
            assertTrue($y >= 1.0 && $y <= 99.0, 'Y should stay within field bounds for slot ' . $slotCode);

            $positionKey = sprintf('%.2f:%.2f', $x, $y);
            assertTrue(!isset($seenPositionKeys[$positionKey]), 'Each live slot must map to a unique position');
            $seenPositionKeys[$positionKey] = true;

            if (in_array($slotCode, ['GK', 'S01', 'S02', 'S03', 'S04'], true)) {
                $expectedAnchor = expectedSixVsSixAnchor($slotCode);
                assertTrue(is_array($expectedAnchor), 'Expected anchor for six-vs-six slot ' . $slotCode);
                $xDiff = abs($x - (float)$expectedAnchor['x']);
                $yDiff = abs($y - (float)$expectedAnchor['y']);
                assertTrue($xDiff < 0.001, 'Slot ' . $slotCode . ' should stay on canonical X anchor');
                assertTrue($yDiff < 0.001, 'Slot ' . $slotCode . ' should stay on canonical Y anchor');
            }
        }
    });
};

$tests['live timer start is noop when timer already runs'] = function (): void {
    withFreshLiveContext(function (PDO $pdo): void {
        loginAs(1, 'Trainer User', false);
        setCurrentTeam(1, 'Team A');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/matches/timer-action';

        $pdo->exec("
            INSERT INTO match_events (match_id, minute, type, player_id, description, period, created_at)
            VALUES (1, 0, 'whistle', NULL, 'start_period', 1, datetime('now', '-120 seconds'))
        ");

        $controller = new TestGameController($pdo);
        $controller->setJsonRequest(true);
        $controller->setJsonBody([
            'match_id' => 1,
            'action' => 'start',
            'csrf_token' => Csrf::getToken(),
        ]);

        ob_start();
        try {
            $controller->timerAction();
            $response = (string)ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $payload = json_decode($response, true);
        assertTrue(is_array($payload), 'Timer response should be valid JSON');
        assertTrue(!empty($payload['success']), 'Timer response should indicate success');
        assertTrue(!empty($payload['noop']), 'Re-entrant start should be returned as noop');
        assertSame(1, (int)fetchValue($pdo, "SELECT COUNT(*) FROM match_events WHERE match_id = 1 AND type = 'whistle' AND description = 'start_period'"), 'No extra start_period whistle should be inserted');
    });
};

$tests['live addEvent derives active period when period missing'] = function (): void {
    withFreshLiveContext(function (PDO $pdo): void {
        loginAs(1, 'Trainer User', false);
        setCurrentTeam(1, 'Team A');

        $pdo->exec("
            INSERT INTO match_events (match_id, minute, type, player_id, description, period, created_at)
            VALUES (1, 0, 'whistle', NULL, 'start_period', 2, datetime('now', '-90 seconds'))
        ");

        setPost([
            'match_id' => '1',
            'type' => 'other',
            'minute' => '17',
            'description' => 'Notitie',
        ]);
        $_SERVER['REQUEST_URI'] = '/matches/add-event';

        $controller = new TestGameController($pdo);
        $redirect = captureRedirect(fn() => $controller->addEvent());
        assertSame('/matches/view?id=1', $redirect, 'addEvent should redirect back to match view');

        $period = (int)fetchValue($pdo, "SELECT period FROM match_events WHERE match_id = 1 AND type = 'other' ORDER BY id DESC LIMIT 1");
        assertSame(2, $period, 'Event period should follow active timer period when request omits period');
    });
};

$tests['live addEvent blocks player from another team'] = function (): void {
    withFreshLiveContext(function (PDO $pdo): void {
        loginAs(1, 'Trainer User', false);
        setCurrentTeam(1, 'Team A');

        $pdo->exec("
            INSERT INTO players (id, team_id, name, number) VALUES
            (99, 2, 'Other Team Player', 99)
        ");

        setPost([
            'match_id' => '1',
            'type' => 'other',
            'minute' => '5',
            'player_id' => '99',
            'description' => 'Invalid player test',
        ]);
        $_SERVER['REQUEST_URI'] = '/matches/add-event';

        $controller = new TestGameController($pdo);
        $redirect = captureRedirect(fn() => $controller->addEvent());
        assertSame('/matches/view?id=1', $redirect, 'Invalid player should redirect back to match view');
        assertTrue(Session::hasFlash('error'), 'Invalid player should set an error flash');
        assertSame(0, (int)fetchValue($pdo, "SELECT COUNT(*) FROM match_events WHERE match_id = 1 AND type = 'other'"), 'Invalid player must not create an event');
    });
};

$total = count($tests);
$passed = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
        echo "[PASS] {$name}\n";
    } catch (Throwable $e) {
        echo "[FAIL] {$name}\n";
        echo "       " . $e->getMessage() . "\n";
    }
}

echo "\nResult: {$passed}/{$total} tests passed.\n";
exit($passed === $total ? 0 : 1);
