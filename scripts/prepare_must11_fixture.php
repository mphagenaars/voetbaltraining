<?php
declare(strict_types=1);

/**
 * Prepares stable local fixture data for MUST-11 visual baselines.
 * Output is JSON only (for machine parsing).
 */

$dbPath = realpath(__DIR__ . '/../data/database.sqlite');
if ($dbPath === false) {
    fwrite(STDERR, "Database niet gevonden.\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$fixtureUsername = 'must11_admin';
$fixtureName = 'MUST-11 Admin';
$fixturePassword = 'Must11Admin!2026';

$pdo->beginTransaction();

try {
    // Ensure at least one team exists.
    $teamId = (int)($pdo->query('SELECT id FROM teams ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
    if ($teamId <= 0) {
        $inviteCode = bin2hex(random_bytes(8));
        $stmt = $pdo->prepare(
            'INSERT INTO teams (name, invite_code, club, season, created_at) VALUES (:name, :invite_code, :club, :season, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            ':name' => 'MUST-11 Team',
            ':invite_code' => $inviteCode,
            ':club' => '',
            ':season' => '',
        ]);
        $teamId = (int)$pdo->lastInsertId();
    }

    // Ensure a dedicated admin fixture user exists with known credentials.
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username COLLATE NOCASE LIMIT 1');
    $stmt->execute([':username' => $fixtureUsername]);
    $adminUserId = (int)($stmt->fetchColumn() ?: 0);

    $passwordHash = password_hash($fixturePassword, PASSWORD_DEFAULT);
    if ($adminUserId > 0) {
        $stmt = $pdo->prepare('UPDATE users SET name = :name, password_hash = :password_hash, is_admin = 1 WHERE id = :id');
        $stmt->execute([
            ':name' => $fixtureName,
            ':password_hash' => $passwordHash,
            ':id' => $adminUserId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, name, is_admin, created_at) VALUES (:username, :password_hash, :name, 1, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            ':username' => $fixtureUsername,
            ':password_hash' => $passwordHash,
            ':name' => $fixtureName,
        ]);
        $adminUserId = (int)$pdo->lastInsertId();
    }

    // Ensure user is member of the selected team.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM team_members WHERE user_id = :user_id AND team_id = :team_id');
    $stmt->execute([
        ':user_id' => $adminUserId,
        ':team_id' => $teamId,
    ]);
    $membershipExists = (int)$stmt->fetchColumn() > 0;

    if ($membershipExists) {
        $stmt = $pdo->prepare(
            'UPDATE team_members
             SET is_coach = COALESCE(is_coach, 0),
                 is_trainer = CASE WHEN COALESCE(is_coach, 0) = 0 AND COALESCE(is_trainer, 0) = 0 THEN 1 ELSE is_trainer END,
                 is_hidden = 0
             WHERE user_id = :user_id AND team_id = :team_id'
        );
        $stmt->execute([
            ':user_id' => $adminUserId,
            ':team_id' => $teamId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO team_members (user_id, team_id, is_coach, is_trainer, is_hidden, joined_at)
             VALUES (:user_id, :team_id, 0, 1, 0, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            ':user_id' => $adminUserId,
            ':team_id' => $teamId,
        ]);
    }

    // Ensure at least one match exists for the selected team.
    $stmt = $pdo->prepare('SELECT id FROM matches WHERE team_id = :team_id ORDER BY id DESC LIMIT 1');
    $stmt->execute([':team_id' => $teamId]);
    $matchId = (int)($stmt->fetchColumn() ?: 0);

    if ($matchId <= 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO matches (team_id, opponent, date, is_home, score_home, score_away, formation, created_at)
             VALUES (:team_id, :opponent, :date, 1, 0, 0, :formation, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            ':team_id' => $teamId,
            ':opponent' => 'MUST-11 Tegenstander',
            ':date' => date('Y-m-d\TH:i'),
            ':formation' => '6-vs-6',
        ]);
        $matchId = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Fixture voorbereiding mislukt: ' . $e->getMessage() . "\n");
    exit(1);
}

echo json_encode([
    'username' => $fixtureUsername,
    'password' => $fixturePassword,
    'admin_user_id' => $adminUserId,
    'team_id' => $teamId,
    'match_id' => $matchId,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
