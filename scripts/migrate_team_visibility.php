<?php
require_once __DIR__ . '/../src/Database.php';

$db = new Database();
$pdo = $db->getConnection();

echo "Migrating team_members table to add is_hidden column...\n";

try {
    // Check if column exists
    $stmt = $pdo->query("PRAGMA table_info(team_members)");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

    if (!in_array('is_hidden', $columns)) {
        $pdo->exec("ALTER TABLE team_members ADD COLUMN is_hidden INTEGER DEFAULT 0");
        echo "- Column 'is_hidden' added to 'team_members'.\n";
    } else {
        echo "- Column 'is_hidden' already exists.\n";
    }

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
