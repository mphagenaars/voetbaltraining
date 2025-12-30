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
    $db->exec("CREATE TABLE IF NOT EXISTS exercises (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        team_id INTEGER NOT NULL,
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'players' aangemaakt (of bestond al).\n";

    // Lineups tabel
    $db->exec("CREATE TABLE IF NOT EXISTS lineups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        team_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        formation TEXT DEFAULT '4-3-3',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'lineups' aangemaakt (of bestond al).\n";

    // Lineup Positions tabel
    $db->exec("CREATE TABLE IF NOT EXISTS lineup_positions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lineup_id INTEGER NOT NULL,
        player_id INTEGER,
        position_x INTEGER, -- Percentage 0-100
        position_y INTEGER, -- Percentage 0-100
        is_substitute BOOLEAN DEFAULT 0,
        FOREIGN KEY (lineup_id) REFERENCES lineups(id) ON DELETE CASCADE,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
    )");
    echo "- Tabel 'lineup_positions' aangemaakt (of bestond al).\n";

    echo "Database succesvol geïnitialiseerd in data/database.sqlite\n";

} catch (PDOException $e) {
    echo "Fout bij initialiseren database: " . $e->getMessage() . "\n";
    exit(1);
}
