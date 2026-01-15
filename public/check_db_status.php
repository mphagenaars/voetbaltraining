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
    
    // Handle Fix Request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_db'])) {
        try {
            $db->beginTransaction();

            // 1. Fix training_exercises
            if (strpos($trainingExercisesSql, 'exercises_old') !== false) {
                $db->exec("CREATE TABLE training_exercises_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    training_id INTEGER NOT NULL,
                    exercise_id INTEGER NOT NULL,
                    sort_order INTEGER NOT NULL,
                    duration INTEGER,
                    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
                    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
                )");
                $db->exec("INSERT INTO training_exercises_new SELECT * FROM training_exercises");
                $db->exec("DROP TABLE training_exercises");
                $db->exec("ALTER TABLE training_exercises_new RENAME TO training_exercises");
                $trainingExercisesSql = $checkTable('training_exercises'); // Refresh for display
            }

            // 2. Fix exercise_tags
            $exerciseTagsSql = $checkTable('exercise_tags');
            if (strpos($exerciseTagsSql, 'exercises_old') !== false) {
                $db->exec("CREATE TABLE exercise_tags_new (
                    exercise_id INTEGER NOT NULL,
                    tag_id INTEGER NOT NULL,
                    PRIMARY KEY (exercise_id, tag_id),
                    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
                )");
                $db->exec("INSERT INTO exercise_tags_new SELECT * FROM exercise_tags");
                $db->exec("DROP TABLE exercise_tags");
                $db->exec("ALTER TABLE exercise_tags_new RENAME TO exercise_tags");
            }
            
            $db->commit();
            echo "<div style='background: #d4edda; color: #155724; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;'>Database successfully repaired!</div>";
        } catch (Exception $ex) {
            $db->rollBack();
            echo "<div style='background: #f8d7da; color: #721c24; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;'>Repair failed: " . htmlspecialchars($ex->getMessage()) . "</div>";
        }
    }
    
    echo "<h1>Database Check</h1>";
    
    if (strpos($trainingExercisesSql, 'exercises_old') !== false) {
        echo "<h2 style='color: red;'>CRITICAL ERROR: Database structure is corrupt.</h2>";
        echo "<p>Table <code>training_exercises</code> references <code>exercises_old</code> which usually causes 500 errors on save.</p>";
        echo "<pre>" . htmlspecialchars($trainingExercisesSql) . "</pre>";
        echo "<form method='post'><button type='submit' name='fix_db' style='background: red; color: white; padding: 10px 20px; border: none; font-size: 16px; cursor: pointer;'>FIX DATABASE NOW</button></form>";
    } else {
        echo "<h2 style='color: green;'>Database structure looks correct!</h2>";
        echo "<p>Table <code>training_exercises</code> does NOT reference <code>exercises_old</code>.</p>";
        echo "<p>If you still have issues, check server logs.</p>";
        echo "<pre>" . htmlspecialchars($trainingExercisesSql) . "</pre>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
