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
    if (!in_array('ai_access_enabled', $userColumns)) {
        $db->exec("ALTER TABLE users ADD COLUMN ai_access_enabled INTEGER NOT NULL DEFAULT 0");
        echo "- Kolom 'ai_access_enabled' toegevoegd aan 'users'.\n";
    }
    if (!in_array('email', $userColumns)) {
        $db->exec("ALTER TABLE users ADD COLUMN email TEXT NULL");
        echo "- Kolom 'email' toegevoegd aan 'users'.\n";
    }

    // App settings tabel
    $db->exec("CREATE TABLE IF NOT EXISTS app_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        \"key\" TEXT NOT NULL UNIQUE,
        value TEXT NULL,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'app_settings' aangemaakt (of bestond al).\n";

    $settingColumns = $db->query("PRAGMA table_info(app_settings)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('updated_at', $settingColumns)) {
        // SQLite staat CURRENT_TIMESTAMP niet toe als DEFAULT bij ADD COLUMN op niet-lege tabellen.
        $db->exec("ALTER TABLE app_settings ADD COLUMN updated_at TEXT");
        $db->exec("UPDATE app_settings SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL OR TRIM(updated_at) = ''");
        echo "- Kolom 'updated_at' toegevoegd aan 'app_settings'.\n";
    }

    $settingStmt = $db->prepare("INSERT OR IGNORE INTO app_settings (\"key\", value, updated_at) VALUES (:key, :value, CURRENT_TIMESTAMP)");
    $initialSettings = [
        'ai_access_mode' => 'off',
        'ai_default_model' => null,
        'openrouter_api_key_enc' => null,
        'openrouter_management_api_key_enc' => null,
        'youtube_api_key_enc' => null,
        'ai_billing_enabled' => '1',
        'ai_pricing_version' => '1',
        'ai_budget_mode' => 'monthly_per_user',
        'ai_monthly_user_budget_eur' => null,
        'ai_budget_reset_day' => '1',
        'ai_rate_limit_per_minute' => '10',
        'ai_max_sessions_per_user' => '50',
        'ai_retrieval_enabled' => '1',
        'ai_retrieval_youtube_enabled' => '1',
        'ai_retrieval_max_candidates' => '10',
        'ai_retrieval_min_youtube_sources' => '2',
        'ai_retrieval_internal_limit' => '2',
        'live_voice_enabled' => '1',
    ];
    foreach ($initialSettings as $key => $value) {
        $settingStmt->execute([
            ':key' => $key,
            ':value' => $value,
        ]);
    }
    echo "- AI standaardinstellingen gecontroleerd/aangemaakt.\n";

    // Legacy migratie: oude ai_enabled key omzetten naar ai_access_mode
    $legacyAiEnabled = $db->query("SELECT value FROM app_settings WHERE \"key\" = 'ai_enabled' LIMIT 1")->fetchColumn();
    if ($legacyAiEnabled !== false && $legacyAiEnabled !== null) {
        $mappedMode = ((string)$legacyAiEnabled === '1') ? 'on' : 'off';
        $stmt = $db->prepare("UPDATE app_settings SET value = :value, updated_at = CURRENT_TIMESTAMP WHERE \"key\" = 'ai_access_mode'");
        $stmt->execute([':value' => $mappedMode]);
        echo "- Legacy key 'ai_enabled' gemigreerd naar 'ai_access_mode'.\n";
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
        goal TEXT,
        FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
        FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'training_exercises' aangemaakt (of bestond al).\n";

    // Check of goal kolom bestaat (voor migratie)
    $teColumns = $db->query("PRAGMA table_info(training_exercises)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('goal', $teColumns)) {
        $db->exec("ALTER TABLE training_exercises ADD COLUMN goal TEXT");
        echo "- Kolom 'goal' toegevoegd aan 'training_exercises'.\n";
    }

    $db->exec("CREATE INDEX IF NOT EXISTS idx_training_exercises_training_sort ON training_exercises (training_id, sort_order)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_training_exercises_training_exercise ON training_exercises (training_id, exercise_id)");
    echo "- Indexen voor 'training_exercises' gecontroleerd/aangemaakt.\n";

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

    // Indexen voor live timeline/timer queries
    $db->exec("CREATE INDEX IF NOT EXISTS idx_match_events_match_type_created ON match_events (match_id, type, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_match_events_match_minute_created ON match_events (match_id, minute, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_match_events_match_created ON match_events (match_id, created_at)");
    echo "- Indexen voor 'match_events' gecontroleerd/aangemaakt.\n";

    // Match period lineups (startopstelling per periode + slot)
    $db->exec("CREATE TABLE IF NOT EXISTS match_period_lineups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        match_id INTEGER NOT NULL,
        period INTEGER NOT NULL,
        slot_code TEXT NOT NULL,
        player_id INTEGER NOT NULL,
        created_by INTEGER NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE (match_id, period, slot_code)
    )");
    echo "- Tabel 'match_period_lineups' aangemaakt (of bestond al).\n";

    $db->exec("CREATE INDEX IF NOT EXISTS idx_match_period_lineups_match_period ON match_period_lineups (match_id, period)");
    echo "- Indexen voor 'match_period_lineups' gecontroleerd/aangemaakt.\n";

    // Match substitutions (gestructureerde wisselregistratie)
    $db->exec("CREATE TABLE IF NOT EXISTS match_substitutions (
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CHECK (player_out_id <> player_in_id),
        CHECK (source IN ('manual', 'voice')),
        FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
        FOREIGN KEY (player_out_id) REFERENCES players(id) ON DELETE CASCADE,
        FOREIGN KEY (player_in_id) REFERENCES players(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    echo "- Tabel 'match_substitutions' aangemaakt (of bestond al).\n";

    $db->exec("CREATE INDEX IF NOT EXISTS idx_match_substitutions_match_clock_created ON match_substitutions (match_id, clock_seconds, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_match_substitutions_match_period ON match_substitutions (match_id, period)");
    echo "- Indexen voor 'match_substitutions' gecontroleerd/aangemaakt.\n";

    // Player name aliases (team-specifieke naamsynoniemen voor STT-herkenning)
    $db->exec("CREATE TABLE IF NOT EXISTS player_name_aliases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        team_id INTEGER NOT NULL,
        player_id INTEGER NOT NULL,
        alias TEXT NOT NULL,
        normalized_alias TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (team_id, player_id, normalized_alias),
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'player_name_aliases' aangemaakt (of bestond al).\n";

    $db->exec("CREATE INDEX IF NOT EXISTS idx_player_name_aliases_team_normalized ON player_name_aliases (team_id, normalized_alias)");
    echo "- Indexen voor 'player_name_aliases' gecontroleerd/aangemaakt.\n";

    // Match voice command logs (audit + kwaliteitsverbetering STT)
    $db->exec("CREATE TABLE IF NOT EXISTS match_voice_command_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        match_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        period INTEGER NULL,
        clock_seconds INTEGER NULL,
        audio_duration_ms INTEGER NULL,
        stt_model_id TEXT NULL,
        raw_transcript TEXT NULL,
        normalized_transcript TEXT NULL,
        parsed_json TEXT NULL,
        status TEXT NOT NULL DEFAULT 'error',
        error_code TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CHECK (status IN ('accepted', 'needs_confirmation', 'rejected', 'error')),
        FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'match_voice_command_logs' aangemaakt (of bestond al).\n";

    $db->exec("CREATE INDEX IF NOT EXISTS idx_match_voice_command_logs_match_created ON match_voice_command_logs (match_id, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_match_voice_command_logs_status_created ON match_voice_command_logs (status, created_at)");
    echo "- Indexen voor 'match_voice_command_logs' gecontroleerd/aangemaakt.\n";

    // Match tactics (wedstrijdsituaties op tactiekbord)
    $db->exec("CREATE TABLE IF NOT EXISTS match_tactics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        team_id INTEGER NOT NULL,
        match_id INTEGER NULL,
        context_type TEXT NOT NULL DEFAULT 'match',
        title TEXT NOT NULL,
        phase TEXT NOT NULL DEFAULT 'open_play',
        minute INTEGER NULL,
        field_type TEXT NOT NULL DEFAULT 'standard_30x42_5',
        drawing_data TEXT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_by INTEGER NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    echo "- Tabel 'match_tactics' aangemaakt (of bestond al).\n";

    $mtInfo = $db->query("PRAGMA table_info(match_tactics)")->fetchAll(PDO::FETCH_ASSOC);
    $mtColumns = array_map(
        static fn(array $column): string => (string)$column['name'],
        $mtInfo
    );

    if (!in_array('team_id', $mtColumns, true)) {
        $db->exec("ALTER TABLE match_tactics ADD COLUMN team_id INTEGER NULL");
        echo "- Kolom 'team_id' toegevoegd aan 'match_tactics'.\n";
    }
    if (!in_array('context_type', $mtColumns, true)) {
        $db->exec("ALTER TABLE match_tactics ADD COLUMN context_type TEXT DEFAULT 'match'");
        echo "- Kolom 'context_type' toegevoegd aan 'match_tactics'.\n";
    }
    if (!in_array('title', $mtColumns, true)) {
        $db->exec("ALTER TABLE match_tactics ADD COLUMN title TEXT DEFAULT 'Nieuwe situatie'");
        echo "- Kolom 'title' toegevoegd aan 'match_tactics'.\n";
    }
    if (!in_array('phase', $mtColumns, true)) {
        $db->exec("ALTER TABLE match_tactics ADD COLUMN phase TEXT DEFAULT 'open_play'");
        echo "- Kolom 'phase' toegevoegd aan 'match_tactics'.\n";
    }
    if (!in_array('minute', $mtColumns, true)) {
        $db->exec("ALTER TABLE match_tactics ADD COLUMN minute INTEGER NULL");
        echo "- Kolom 'minute' toegevoegd aan 'match_tactics'.\n";
    }
    if (!in_array('field_type', $mtColumns, true)) {
        $db->exec("ALTER TABLE match_tactics ADD COLUMN field_type TEXT DEFAULT 'standard_30x42_5'");
        echo "- Kolom 'field_type' toegevoegd aan 'match_tactics'.\n";
    }
    if (!in_array('drawing_data', $mtColumns, true)) {
        $db->exec("ALTER TABLE match_tactics ADD COLUMN drawing_data TEXT NULL");
        echo "- Kolom 'drawing_data' toegevoegd aan 'match_tactics'.\n";
    }
    if (!in_array('sort_order', $mtColumns, true)) {
        $db->exec("ALTER TABLE match_tactics ADD COLUMN sort_order INTEGER DEFAULT 0");
        echo "- Kolom 'sort_order' toegevoegd aan 'match_tactics'.\n";
    }
    if (!in_array('created_by', $mtColumns, true)) {
        $db->exec("ALTER TABLE match_tactics ADD COLUMN created_by INTEGER NULL");
        echo "- Kolom 'created_by' toegevoegd aan 'match_tactics'.\n";
    }
    if (!in_array('updated_at', $mtColumns, true)) {
        $db->exec("ALTER TABLE match_tactics ADD COLUMN updated_at DATETIME");
        $db->exec("UPDATE match_tactics SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL OR TRIM(updated_at) = ''");
        echo "- Kolom 'updated_at' toegevoegd aan 'match_tactics'.\n";
    }

    // Backfill team/context waarden op oudere schema's.
    $db->exec("UPDATE match_tactics
               SET team_id = (
                   SELECT m.team_id
                   FROM matches m
                   WHERE m.id = match_tactics.match_id
               )
               WHERE team_id IS NULL
                 AND match_id IS NOT NULL");
    $db->exec("UPDATE match_tactics
               SET context_type = CASE
                   WHEN match_id IS NULL THEN 'team'
                   ELSE 'match'
               END
               WHERE context_type IS NULL
                  OR TRIM(context_type) = ''");

    $mtInfo = $db->query("PRAGMA table_info(match_tactics)")->fetchAll(PDO::FETCH_ASSOC);
    $mtColumns = array_map(
        static fn(array $column): string => (string)$column['name'],
        $mtInfo
    );
    $matchIdIsNullable = true;
    foreach ($mtInfo as $column) {
        if ((string)$column['name'] === 'match_id') {
            $matchIdIsNullable = ((int)$column['notnull'] === 0);
            break;
        }
    }

    $needsMatchTacticsRebuild = (
        !$matchIdIsNullable ||
        !in_array('team_id', $mtColumns, true) ||
        !in_array('context_type', $mtColumns, true)
    );

    if ($needsMatchTacticsRebuild) {
        $hasTeamIdBefore = in_array('team_id', $mtColumns, true);
        $hasContextTypeBefore = in_array('context_type', $mtColumns, true);
        $teamIdExpr = $hasTeamIdBefore ? 'old.team_id' : 'm.team_id';
        $contextTypeExpr = $hasContextTypeBefore
            ? "CASE
                   WHEN old.context_type IN ('match', 'team') THEN old.context_type
                   WHEN old.match_id IS NULL THEN 'team'
                   ELSE 'match'
               END"
            : "CASE
                   WHEN old.match_id IS NULL THEN 'team'
                   ELSE 'match'
               END";

        $db->beginTransaction();
        try {
            $db->exec("CREATE TABLE match_tactics_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team_id INTEGER NOT NULL,
                match_id INTEGER NULL,
                context_type TEXT NOT NULL DEFAULT 'match',
                title TEXT NOT NULL,
                phase TEXT NOT NULL DEFAULT 'open_play',
                minute INTEGER NULL,
                field_type TEXT NOT NULL DEFAULT 'standard_30x42_5',
                drawing_data TEXT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_by INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
                FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )");

            $db->exec("INSERT INTO match_tactics_new (
                    id,
                    team_id,
                    match_id,
                    context_type,
                    title,
                    phase,
                    minute,
                    field_type,
                    drawing_data,
                    sort_order,
                    created_by,
                    created_at,
                    updated_at
                )
                SELECT
                    old.id,
                    COALESCE($teamIdExpr, m.team_id) AS team_id,
                    old.match_id,
                    $contextTypeExpr AS context_type,
                    COALESCE(old.title, 'Nieuwe situatie') AS title,
                    COALESCE(old.phase, 'open_play') AS phase,
                    old.minute,
                    COALESCE(old.field_type, 'standard_30x42_5') AS field_type,
                    old.drawing_data,
                    COALESCE(old.sort_order, 0) AS sort_order,
                    old.created_by,
                    COALESCE(old.created_at, CURRENT_TIMESTAMP) AS created_at,
                    COALESCE(old.updated_at, CURRENT_TIMESTAMP) AS updated_at
                FROM match_tactics old
                LEFT JOIN matches m ON m.id = old.match_id
                WHERE COALESCE($teamIdExpr, m.team_id) IS NOT NULL");

            $db->exec("DROP TABLE match_tactics");
            $db->exec("ALTER TABLE match_tactics_new RENAME TO match_tactics");

            $db->commit();
            echo "- Tabel 'match_tactics' gemigreerd naar context-schema.\n";
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    $db->exec("CREATE INDEX IF NOT EXISTS idx_match_tactics_match_sort ON match_tactics (match_id, sort_order, id)");
    echo "- Index voor 'match_tactics' gecontroleerd/aangemaakt.\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_match_tactics_team_context ON match_tactics (team_id, context_type, updated_at, id)");
    echo "- Team/context index voor 'match_tactics' gecontroleerd/aangemaakt.\n";

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

    // AI modellen tabel
    $db->exec("CREATE TABLE IF NOT EXISTS ai_models (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        model_id TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL,
        enabled INTEGER NOT NULL DEFAULT 1,
        supports_vision INTEGER NOT NULL DEFAULT 0,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'ai_models' aangemaakt (of bestond al).\n";

    // Migratie: supports_vision kolom toevoegen aan ai_models
    $aiModelColumns = $db->query("PRAGMA table_info(ai_models)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('supports_vision', $aiModelColumns, true)) {
        $db->exec("ALTER TABLE ai_models ADD COLUMN supports_vision INTEGER NOT NULL DEFAULT 0");
        echo "- Kolom 'supports_vision' toegevoegd aan ai_models.\n";
    }

    // Migratie: supports_audio kolom toevoegen aan ai_models
    if (!in_array('supports_audio', $aiModelColumns, true)) {
        $db->exec("ALTER TABLE ai_models ADD COLUMN supports_audio INTEGER NOT NULL DEFAULT 0");
        echo "- Kolom 'supports_audio' toegevoegd aan ai_models.\n";
    }

    // AI chat sessies tabel
    $db->exec("CREATE TABLE IF NOT EXISTS ai_chat_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        team_id INTEGER NULL,
        exercise_id INTEGER NULL,
        title TEXT NULL,
        workflow_mode TEXT NULL,
        current_plan_json TEXT NULL,
        plan_updated_at TEXT NULL,
        plan_version INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
        FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE SET NULL
    )");
    echo "- Tabel 'ai_chat_sessions' aangemaakt (of bestond al).\n";

    // Migratie: workflow kolommen voor bestaande databases
    $sessionColumns = $db->query("PRAGMA table_info(ai_chat_sessions)")->fetchAll(PDO::FETCH_ASSOC);
    $sessionColumnMap = [];
    foreach ($sessionColumns as $col) {
        if (!empty($col['name'])) {
            $sessionColumnMap[(string)$col['name']] = true;
        }
    }

    if (!isset($sessionColumnMap['workflow_mode'])) {
        $db->exec("ALTER TABLE ai_chat_sessions ADD COLUMN workflow_mode TEXT NULL");
    }
    if (!isset($sessionColumnMap['current_plan_json'])) {
        $db->exec("ALTER TABLE ai_chat_sessions ADD COLUMN current_plan_json TEXT NULL");
    }
    if (!isset($sessionColumnMap['plan_updated_at'])) {
        $db->exec("ALTER TABLE ai_chat_sessions ADD COLUMN plan_updated_at TEXT NULL");
    }
    if (!isset($sessionColumnMap['plan_version'])) {
        $db->exec("ALTER TABLE ai_chat_sessions ADD COLUMN plan_version INTEGER NOT NULL DEFAULT 0");
    }

    // AI chat berichten tabel
    $db->exec("CREATE TABLE IF NOT EXISTS ai_chat_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        role TEXT NOT NULL,
        content TEXT NOT NULL,
        model_id TEXT NULL,
        metadata_json TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES ai_chat_sessions(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'ai_chat_messages' aangemaakt (of bestond al).\n";

    // AI model pricing tabel
    $db->exec("CREATE TABLE IF NOT EXISTS ai_model_pricing (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        model_id TEXT NOT NULL UNIQUE,
        currency TEXT NOT NULL DEFAULT 'EUR',
        input_price_per_mtoken REAL NOT NULL DEFAULT 0,
        output_price_per_mtoken REAL NOT NULL DEFAULT 0,
        request_flat_price REAL NOT NULL DEFAULT 0,
        min_request_price REAL NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (model_id) REFERENCES ai_models(model_id) ON DELETE CASCADE
    )");
    echo "- Tabel 'ai_model_pricing' aangemaakt (of bestond al).\n";

    // AI usage events tabel
    $db->exec("CREATE TABLE IF NOT EXISTS ai_usage_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        team_id INTEGER NULL,
        session_id INTEGER NULL,
        exercise_id INTEGER NULL,
        provider TEXT NOT NULL DEFAULT 'openrouter',
        generation_id TEXT NULL,
        model_id TEXT NOT NULL,
        status TEXT NOT NULL,
        input_tokens INTEGER NOT NULL DEFAULT 0,
        output_tokens INTEGER NOT NULL DEFAULT 0,
        total_tokens INTEGER NOT NULL DEFAULT 0,
        supplier_cost_usd REAL NOT NULL DEFAULT 0,
        billable_cost_eur REAL NOT NULL DEFAULT 0,
        pricing_version INTEGER NULL,
        pricing_snapshot_json TEXT NULL,
        error_code TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'ai_usage_events' aangemaakt (of bestond al).\n";

    // AI quality events tabel
    $db->exec("CREATE TABLE IF NOT EXISTS ai_quality_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        team_id INTEGER NULL,
        session_id INTEGER NULL,
        event_type TEXT NOT NULL,
        status TEXT NOT NULL,
        external_id TEXT NULL,
        payload_json TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'ai_quality_events' aangemaakt (of bestond al).\n";

    // AI source cache tabel (retrieval)
    $db->exec("CREATE TABLE IF NOT EXISTS ai_source_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        provider TEXT NOT NULL,
        external_id TEXT NOT NULL,
        title TEXT NOT NULL,
        url TEXT NULL,
        snippet TEXT NULL,
        metadata_json TEXT NULL,
        fetched_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at TEXT NOT NULL,
        UNIQUE(provider, external_id)
    )");
    echo "- Tabel 'ai_source_cache' aangemaakt (of bestond al).\n";

    // Indexen voor AI tabellen
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_models_enabled_sort_order ON ai_models (enabled, sort_order)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_model_pricing_active_model_id ON ai_model_pricing (is_active, model_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_chat_sessions_user_team_updated ON ai_chat_sessions (user_id, team_id, updated_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_chat_messages_session_created ON ai_chat_messages (session_id, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_usage_events_user_created ON ai_usage_events (user_id, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_usage_events_team_created ON ai_usage_events (team_id, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_usage_events_model_created ON ai_usage_events (model_id, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_usage_events_generation_id ON ai_usage_events (generation_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_quality_events_user_created ON ai_quality_events (user_id, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_quality_events_team_created ON ai_quality_events (team_id, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_quality_events_session_created ON ai_quality_events (session_id, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_quality_events_type_created ON ai_quality_events (event_type, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_source_cache_provider_external ON ai_source_cache (provider, external_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_source_cache_expires_at ON ai_source_cache (expires_at)");
    echo "- Indexen voor AI tabellen gecontroleerd/aangemaakt.\n";

    // Formation templates (speelwijzen)
    $db->exec("CREATE TABLE IF NOT EXISTS formation_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        team_id INTEGER NULL,
        name TEXT NOT NULL,
        format TEXT NOT NULL,
        positions TEXT NOT NULL,
        is_shared INTEGER DEFAULT 0,
        created_by INTEGER NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    echo "- Tabel 'formation_templates' aangemaakt (of bestond al).\n";

    // Kolom formation_template_id op matches (migratie)
    $mColumns2 = $db->query("PRAGMA table_info(matches)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('formation_template_id', $mColumns2)) {
        $db->exec("ALTER TABLE matches ADD COLUMN formation_template_id INTEGER NULL REFERENCES formation_templates(id) ON DELETE SET NULL");
        echo "- Kolom 'formation_template_id' toegevoegd aan 'matches'.\n";
    }

    // Seed standaard speelwijzen (gedeeld)
    $ftCount = (int) $db->query("SELECT COUNT(*) FROM formation_templates WHERE is_shared = 1 AND team_id IS NULL")->fetchColumn();
    if ($ftCount === 0) {
        $defaultTemplates = [
            [
                'name' => '6 tegen 6 (2-1-2)',
                'format' => '6-vs-6',
                'positions' => json_encode([
                    ['slot_code' => 'K',  'label' => 'Keeper',              'x' => 50, 'y' => 88],
                    ['slot_code' => 'LV', 'label' => 'Links verdediger',    'x' => 20, 'y' => 65],
                    ['slot_code' => 'RV', 'label' => 'Rechts verdediger',   'x' => 80, 'y' => 65],
                    ['slot_code' => 'M',  'label' => 'Middenvelder',        'x' => 50, 'y' => 45],
                    ['slot_code' => 'LA', 'label' => 'Links aanvaller',     'x' => 20, 'y' => 20],
                    ['slot_code' => 'RA', 'label' => 'Rechts aanvaller',    'x' => 80, 'y' => 20],
                ]),
            ],
            [
                'name' => '8 tegen 8 (2-3-2)',
                'format' => '8-vs-8',
                'positions' => json_encode([
                    ['slot_code' => 'K',  'label' => 'Keeper',              'x' => 50, 'y' => 88],
                    ['slot_code' => 'LV', 'label' => 'Links verdediger',    'x' => 30, 'y' => 75],
                    ['slot_code' => 'RV', 'label' => 'Rechts verdediger',   'x' => 70, 'y' => 75],
                    ['slot_code' => 'LM', 'label' => 'Links midden',        'x' => 20, 'y' => 50],
                    ['slot_code' => 'CM', 'label' => 'Centraal midden',     'x' => 50, 'y' => 50],
                    ['slot_code' => 'RM', 'label' => 'Rechts midden',       'x' => 80, 'y' => 50],
                    ['slot_code' => 'LA', 'label' => 'Links aanvaller',     'x' => 35, 'y' => 25],
                    ['slot_code' => 'RA', 'label' => 'Rechts aanvaller',    'x' => 65, 'y' => 25],
                ]),
            ],
            [
                'name' => '4-3-3',
                'format' => '11-vs-11',
                'positions' => json_encode([
                    ['slot_code' => 'K',   'label' => 'Keeper',                 'x' => 50, 'y' => 90],
                    ['slot_code' => 'LAV', 'label' => 'Links achter',           'x' => 15, 'y' => 75],
                    ['slot_code' => 'CV1', 'label' => 'Centraal verdediger',    'x' => 38, 'y' => 75],
                    ['slot_code' => 'CV2', 'label' => 'Centraal verdediger',    'x' => 62, 'y' => 75],
                    ['slot_code' => 'RAV', 'label' => 'Rechts achter',          'x' => 85, 'y' => 75],
                    ['slot_code' => 'LM',  'label' => 'Links midden',           'x' => 30, 'y' => 50],
                    ['slot_code' => 'VM',  'label' => 'Verdedigend midden',     'x' => 50, 'y' => 55],
                    ['slot_code' => 'RM',  'label' => 'Rechts midden',          'x' => 70, 'y' => 50],
                    ['slot_code' => 'LB',  'label' => 'Links buiten',           'x' => 15, 'y' => 25],
                    ['slot_code' => 'SP',  'label' => 'Spits',                  'x' => 50, 'y' => 20],
                    ['slot_code' => 'RB',  'label' => 'Rechts buiten',          'x' => 85, 'y' => 25],
                ]),
            ],
        ];

        $stmt = $db->prepare("INSERT INTO formation_templates (team_id, name, format, positions, is_shared, created_by) VALUES (NULL, :name, :format, :positions, 1, NULL)");
        foreach ($defaultTemplates as $tpl) {
            $stmt->execute([
                'name' => $tpl['name'],
                'format' => $tpl['format'],
                'positions' => $tpl['positions'],
            ]);
        }
        echo "- 3 standaard speelwijzen aangemaakt.\n";
    }

    echo "Database succesvol geïnitialiseerd in data/database.sqlite\n";

} catch (PDOException $e) {
    echo "Fout bij initialiseren database: " . $e->getMessage() . "\n";
    exit(1);
}
