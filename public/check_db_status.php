<?php
// public/check_db_status.php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

try {
    $db = (new Database())->getConnection();
    
    $checkTable = function($tableName) use ($db) {
        $sql = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$tableName'")->fetchColumn();
        return $sql;
    };

    $trainingExercisesSql = $checkTable('training_exercises');
    
    echo "<h1>Database Check</h1>";
    
    if (strpos($trainingExercisesSql, 'exercises_old') !== false) {
        echo "<h2 style='color: red;'>CRITICAL ERROR: Database structure is corrupt.</h2>";
        echo "<p>Table <code>training_exercises</code> references <code>exercises_old</code> which usually causes 500 errors on save.</p>";
        echo "<p><strong>Solution:</strong> Run <code>sudo ./update.sh</code> on your server terminal.</p>";
        echo "<pre>" . htmlspecialchars($trainingExercisesSql) . "</pre>";
    } else {
        echo "<h2 style='color: green;'>Database structure looks correct!</h2>";
        echo "<p>Table <code>training_exercises</code> does NOT reference <code>exercises_old</code>.</p>";
        echo "<p>If you still have issues, check server logs.</p>";
        echo "<pre>" . htmlspecialchars($trainingExercisesSql) . "</pre>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
