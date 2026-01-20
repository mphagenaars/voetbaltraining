<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "Initialiseren database...\n";

try {
    $db = (new Database())->getConnection();

    // Users tabel
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        name TEXT NOT NULL,
        is_admin INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'users' aangemaakt (of bestond al).\n";

    // Check of is_admin kolom bestaat (voor migratie)
    $userColumns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('is_admin', $userColumns)) {
        $db->exec("ALTER TABLE users ADD COLUMN is_admin INTEGER DEFAULT 0");
        echo "- Kolom 'is_admin' toegevoegd aan 'users'.\n";
    }

    // Teams tabel
    $db->exec("CREATE TABLE IF NOT EXISTS teams (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        invite_code TEXT UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'teams' aangemaakt (of bestond al).\n";

    // Team members tabel (Koppeling gebruikers en teams)
    // Update: role kolom vervangen door is_coach en is_trainer flags
    $db->exec("CREATE TABLE IF NOT EXISTS team_members (
        user_id INTEGER NOT NULL,
        team_id INTEGER NOT NULL,
        role TEXT DEFAULT 'coach', -- Deprecated, kept for backward compatibility during migration
        is_coach INTEGER DEFAULT 0,
        is_trainer INTEGER DEFAULT 0,
        is_hidden INTEGER DEFAULT 0,
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, team_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'team_members' aangemaakt (of bestond al).\n";

    // Migratie: Voeg kolommen toe als ze niet bestaan
    $tmColumns = $db->query("PRAGMA table_info(team_members)")->fetchAll(PDO::FETCH_COLUMN, 1);
    
    if (!in_array('is_coach', $tmColumns)) {
        $db->exec("ALTER TABLE team_members ADD COLUMN is_coach INTEGER DEFAULT 0");
        echo "- Kolom 'is_coach' toegevoegd aan 'team_members'.\n";
        // Migreer bestaande data
        $db->exec("UPDATE team_members SET is_coach = 1 WHERE role IN ('coach', 'admin')");
        echo "- Bestaande coaches gemigreerd.\n";
    }

    if (!in_array('is_trainer', $tmColumns)) {
        $db->exec("ALTER TABLE team_members ADD COLUMN is_trainer INTEGER DEFAULT 0");
        echo "- Kolom 'is_trainer' toegevoegd aan 'team_members'.\n";
        // Migreer bestaande data
        $db->exec("UPDATE team_members SET is_trainer = 1 WHERE role = 'trainer'");
        echo "- Bestaande trainers gemigreerd.\n";
    }

    if (!in_array('is_hidden', $tmColumns)) {
        $db->exec("ALTER TABLE team_members ADD COLUMN is_hidden INTEGER DEFAULT 0");
        echo "- Kolom 'is_hidden' toegevoegd aan 'team_members'.\n";
    }

    // Clubs tabel
    $db->exec("CREATE TABLE IF NOT EXISTS clubs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        logo_path TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'clubs' aangemaakt (of bestond al).\n";

    // Check columns for clubs
    $clubColumns = $db->query("PRAGMA table_info(clubs)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('logo_path', $clubColumns)) {
        $db->exec("ALTER TABLE clubs ADD COLUMN logo_path TEXT DEFAULT NULL");
        echo "- Kolom 'logo_path' toegevoegd aan 'clubs'.\n";
    }

    // Seasons tabel
    $db->exec("CREATE TABLE IF NOT EXISTS seasons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        is_current INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'seasons' aangemaakt (of bestond al).\n";

    // Seed initial data if empty
    $clubCount = $db->query("SELECT COUNT(*) FROM clubs")->fetchColumn();
    if ($clubCount == 0) {
        $db->exec("INSERT INTO clubs (name) VALUES ('FC Bal op het Dak'), ('VV De Toekomst'), ('SV Trapvast')");
        echo "- Voorbeeld clubs toegevoegd.\n";
    }

    $seasonCount = $db->query("SELECT COUNT(*) FROM seasons")->fetchColumn();
    if ($seasonCount == 0) {
        $currentYear = (int)date('Y');
        $nextYear = $currentYear + 1;
        $seasonName = "$currentYear-$nextYear";
        
        $stmt = $db->prepare("INSERT INTO seasons (name, is_current) VALUES (:name, 1)");
        $stmt->execute([':name' => $seasonName]);
        echo "- Huidig seizoen ($seasonName) toegevoegd.\n";
    }

    // Check columns for teams (club, season)
    $teamColumns = $db->query("PRAGMA table_info(teams)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('club', $teamColumns)) {
        $db->exec("ALTER TABLE teams ADD COLUMN club TEXT DEFAULT ''");
        echo "- Kolom 'club' toegevoegd aan 'teams'.\n";
    }
    if (!in_array('season', $teamColumns)) {
        $db->exec("ALTER TABLE teams ADD COLUMN season TEXT DEFAULT ''");
        echo "- Kolom 'season' toegevoegd aan 'teams'.\n";
    }

    // Activity Logs tabel
    $db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action VARCHAR(50) NOT NULL,
        entity_id INTEGER NULL,
        details TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");
    echo "- Tabel 'activity_logs' aangemaakt (of bestond al).\n";

    // Exercise Options tabel
    $db->exec("CREATE TABLE IF NOT EXISTS exercise_options (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        name TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0
    )");
    echo "- Tabel 'exercise_options' aangemaakt (of bestond al).\n";

    // Seeding of exercise options has been moved to scripts/seed_options.php
    // to prevent overwriting manual changes during updates.



    // Exercises tabel
    // team_id is nullable to allow global exercises
    $db->exec("CREATE TABLE IF NOT EXISTS exercises (
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    echo "- Tabel 'exercises' aangemaakt (of bestond al).\n";

    // Check of min_players/max_players kolom bestaat (voor migratie)
    $columns = $db->query("PRAGMA table_info(exercises)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('min_players', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN min_players INTEGER");
        echo "- Kolom 'min_players' toegevoegd aan 'exercises'.\n";
    }
    if (!in_array('max_players', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN max_players INTEGER");
        echo "- Kolom 'max_players' toegevoegd aan 'exercises'.\n";
    }
    if (!in_array('field_type', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN field_type TEXT DEFAULT 'portrait'");
        echo "- Kolom 'field_type' toegevoegd aan 'exercises'.\n";
    }
    if (!in_array('variation', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN variation TEXT");
        echo "- Kolom 'variation' toegevoegd aan 'exercises'.\n";
    }
    if (!in_array('team_task', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN team_task TEXT");
        echo "- Kolom 'team_task' toegevoegd aan 'exercises'.\n";
    }
    if (!in_array('created_by', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN created_by INTEGER REFERENCES users(id) ON DELETE SET NULL");
        echo "- Kolom 'created_by' toegevoegd aan 'exercises'.\n";
    }
    if (!in_array('training_objective', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN training_objective TEXT");
        echo "- Kolom 'training_objective' toegevoegd aan 'exercises'.\n";
    }
    if (!in_array('football_action', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN football_action TEXT");
        echo "- Kolom 'football_action' toegevoegd aan 'exercises'.\n";
    }
    if (!in_array('source', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN source TEXT DEFAULT NULL");
        echo "- Kolom 'source' toegevoegd aan 'exercises'.\n";
    }
    if (!in_array('coach_instructions', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN coach_instructions TEXT DEFAULT NULL");
        echo "- Kolom 'coach_instructions' toegevoegd aan 'exercises'.\n";
    }

    // Exercise Comments tabel
    $db->exec("CREATE TABLE IF NOT EXISTS exercise_comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        exercise_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'exercise_comments' aangemaakt (of bestond al).\n";

    // Exercise Reactions tabel
    $db->exec("CREATE TABLE IF NOT EXISTS exercise_reactions (
        exercise_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        reaction_type TEXT NOT NULL, 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (exercise_id, user_id),
        FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'exercise_reactions' aangemaakt (of bestond al).\n";

    // User Tokens tabel (voor 'Remember Me' functionaliteit)
    $db->exec("CREATE TABLE IF NOT EXISTS user_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        selector TEXT NOT NULL UNIQUE,
        hashed_validator TEXT NOT NULL,
        user_id INTEGER NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'user_tokens' aangemaakt (of bestond al).\n";

    // Trainings tabel
    $db->exec("CREATE TABLE IF NOT EXISTS trainings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        team_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        training_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'trainings' aangemaakt (of bestond al).\n";

    // Check of training_date kolom bestaat (voor migratie)
    $tColumns = $db->query("PRAGMA table_info(trainings)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('training_date', $tColumns)) {
        $db->exec("ALTER TABLE trainings ADD COLUMN training_date DATE");
        echo "- Kolom 'training_date' toegevoegd aan 'trainings'.\n";
    }

    // Training Exercises tabel
    $db->exec("CREATE TABLE IF NOT EXISTS training_exercises (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        training_id INTEGER NOT NULL,
        exercise_id INTEGER NOT NULL,
        sort_order INTEGER NOT NULL,
        duration INTEGER,
        FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
        FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'training_exercises' aangemaakt (of bestond al).\n";

    // Players tabel
    $db->exec("CREATE TABLE IF NOT EXISTS players (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        team_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        number INTEGER,
        position TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'players' aangemaakt (of bestond al).\n";

    // Check columns for players migration
    $plColumns = $db->query("PRAGMA table_info(players)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('number', $plColumns)) {
        $db->exec("ALTER TABLE players ADD COLUMN number INTEGER");
        echo "- Kolom 'number' toegevoegd aan 'players'.\n";
    }
    if (!in_array('position', $plColumns)) {
        $db->exec("ALTER TABLE players ADD COLUMN position TEXT");
        echo "- Kolom 'position' toegevoegd aan 'players'.\n";
    }

    // Matches tabel (Replaces Lineups)
    $db->exec("CREATE TABLE IF NOT EXISTS matches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        team_id INTEGER NOT NULL,
        opponent TEXT NOT NULL,
        date DATETIME NOT NULL,
        is_home INTEGER DEFAULT 1,
        score_home INTEGER DEFAULT 0,
        score_away INTEGER DEFAULT 0,
        formation TEXT DEFAULT '4-3-3',
        evaluation TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'matches' aangemaakt (of bestond al).\n";

    // Check of evaluation kolom bestaat (voor migratie)
    $mColumns = $db->query("PRAGMA table_info(matches)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('evaluation', $mColumns)) {
        $db->exec("ALTER TABLE matches ADD COLUMN evaluation TEXT");
        echo "- Kolom 'evaluation' toegevoegd aan 'matches'.\n";
    }

    // Match players (Replaces Lineup positions)
    $db->exec("CREATE TABLE IF NOT EXISTS match_players (
        match_id INTEGER NOT NULL,
        player_id INTEGER NOT NULL,
        position_x INTEGER NOT NULL,
        position_y INTEGER NOT NULL,
        is_substitute BOOLEAN DEFAULT 0,
        is_keeper INTEGER DEFAULT 0,
        PRIMARY KEY (match_id, player_id),
        FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'match_players' aangemaakt (of bestond al).\n";

    // Migratie: voeg is_keeper kolom toe indien nodig
    $mpColumns = $db->query("PRAGMA table_info(match_players)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('is_keeper', $mpColumns)) {
        $db->exec("ALTER TABLE match_players ADD COLUMN is_keeper INTEGER DEFAULT 0");
        echo "- Kolom 'is_keeper' toegevoegd aan 'match_players'.\n";
    }
    if (!in_array('is_absent', $mpColumns)) {
        $db->exec("ALTER TABLE match_players ADD COLUMN is_absent INTEGER DEFAULT 0");
        echo "- Kolom 'is_absent' toegevoegd aan 'match_players'.\n";
    }

    // Match events (Scoreverloop etc)
    $db->exec("CREATE TABLE IF NOT EXISTS match_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        match_id INTEGER NOT NULL,
        minute INTEGER NOT NULL,
        type TEXT NOT NULL, -- 'goal', 'card', 'sub', 'whistle'
        player_id INTEGER, -- Nullable (bijv. goal tegenstander)
        description TEXT,
        period INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
    )");
    echo "- Tabel 'match_events' aangemaakt (of bestond al).\n";

    // Check columns for match_events migration
    $meColumns = $db->query("PRAGMA table_info(match_events)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('period', $meColumns)) {
        $db->exec("ALTER TABLE match_events ADD COLUMN period INTEGER DEFAULT 1");
        echo "- Kolom 'period' toegevoegd aan 'match_events'.\n";
    }

    // Migratie van oude lineups naar matches
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='lineups'")->fetchAll();
    if (count($tables) > 0) {
        $matchCount = $db->query("SELECT COUNT(*) FROM matches")->fetchColumn();
        if ($matchCount == 0) {
            echo "Migreren van oude lineups naar matches...\n";
            $lineups = $db->query("SELECT * FROM lineups")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($lineups as $lineup) {
                $stmt = $db->prepare("INSERT INTO matches (team_id, opponent, date, formation) VALUES (:team_id, :opponent, :date, :formation)");
                $stmt->execute([
                    ':team_id' => $lineup['team_id'],
                    ':opponent' => $lineup['name'], 
                    ':date' => date('Y-m-d H:i:s'), 
                    ':formation' => $lineup['formation']
                ]);
                $matchId = $db->lastInsertId();

                $positions = $db->query("SELECT * FROM lineup_positions WHERE lineup_id = " . $lineup['id'])->fetchAll(PDO::FETCH_ASSOC);
                $posStmt = $db->prepare("INSERT INTO match_players (match_id, player_id, position_x, position_y, is_substitute) VALUES (:match_id, :player_id, :x, :y, :sub)");
                foreach ($positions as $pos) {
                    $posStmt->execute([
                        ':match_id' => $matchId,
                        ':player_id' => $pos['player_id'],
                        ':x' => $pos['position_x'] ?? 0,
                        ':y' => $pos['position_y'] ?? 0,
                        ':sub' => $pos['is_substitute'] ?? 0
                    ]);
                }
            }
            echo "- Migratie voltooid.\n";
        }
    }

    echo "Database succesvol geïnitialiseerd in data/database.sqlite\n";

} catch (PDOException $e) {
    echo "Fout bij initialiseren database: " . $e->getMessage() . "\n";
    exit(1);
}
