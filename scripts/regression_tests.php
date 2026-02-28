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
