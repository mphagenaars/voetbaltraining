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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
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
    if (!in_array('training_objective', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN training_objective TEXT");
        echo "- Kolom 'training_objective' toegevoegd aan 'exercises'.\n";
    }
    if (!in_array('football_action', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN football_action TEXT");
        echo "- Kolom 'football_action' toegevoegd aan 'exercises'.\n";
    }

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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'trainings' aangemaakt (of bestond al).\n";

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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'matches' aangemaakt (of bestond al).\n";

    // Match players (Replaces Lineup positions)
    $db->exec("CREATE TABLE IF NOT EXISTS match_players (
        match_id INTEGER NOT NULL,
        player_id INTEGER NOT NULL,
        position_x INTEGER NOT NULL,
        position_y INTEGER NOT NULL,
        is_substitute BOOLEAN DEFAULT 0,
        PRIMARY KEY (match_id, player_id),
        FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'match_players' aangemaakt (of bestond al).\n";

    // Match events (Scoreverloop etc)
    $db->exec("CREATE TABLE IF NOT EXISTS match_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        match_id INTEGER NOT NULL,
        minute INTEGER NOT NULL,
        type TEXT NOT NULL, -- 'goal', 'card', 'sub'
        player_id INTEGER, -- Nullable (bijv. goal tegenstander)
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
    )");
    echo "- Tabel 'match_events' aangemaakt (of bestond al).\n";

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
