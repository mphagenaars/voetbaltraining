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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'users' aangemaakt (of bestond al).\n";

    // Teams tabel
    $db->exec("CREATE TABLE IF NOT EXISTS teams (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        invite_code TEXT UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'teams' aangemaakt (of bestond al).\n";

    // Team members tabel (Koppeling gebruikers en teams)
    $db->exec("CREATE TABLE IF NOT EXISTS team_members (
        user_id INTEGER NOT NULL,
        team_id INTEGER NOT NULL,
        role TEXT DEFAULT 'coach',
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, team_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'team_members' aangemaakt (of bestond al).\n";

    // Exercises tabel
    $db->exec("CREATE TABLE IF NOT EXISTS exercises (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        team_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        players INTEGER,
        duration INTEGER,
        requirements TEXT,
        image_path TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'exercises' aangemaakt (of bestond al).\n";

    // Check of image_path kolom bestaat (voor migratie)
    $columns = $db->query("PRAGMA table_info(exercises)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('image_path', $columns)) {
        $db->exec("ALTER TABLE exercises ADD COLUMN image_path TEXT");
        echo "- Kolom 'image_path' toegevoegd aan 'exercises'.\n";
    }

    // Tags tabel
    $db->exec("CREATE TABLE IF NOT EXISTS tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        team_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        UNIQUE(team_id, name)
    )");
    echo "- Tabel 'tags' aangemaakt (of bestond al).\n";

    // Exercise Tags tabel (Many-to-Many)
    $db->exec("CREATE TABLE IF NOT EXISTS exercise_tags (
        exercise_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        PRIMARY KEY (exercise_id, tag_id),
        FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'exercise_tags' aangemaakt (of bestond al).\n";

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

    // Check of name kolom bestaat (voor migratie)
    $columns = $db->query("PRAGMA table_info(lineups)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('name', $columns)) {
        $db->exec("ALTER TABLE lineups ADD COLUMN name TEXT DEFAULT 'Nieuwe opstelling'");
        echo "- Kolom 'name' toegevoegd aan 'lineups'.\n";
    }
    if (!in_array('formation', $columns)) {
        $db->exec("ALTER TABLE lineups ADD COLUMN formation TEXT DEFAULT '4-3-3'");
        echo "- Kolom 'formation' toegevoegd aan 'lineups'.\n";
    }

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
