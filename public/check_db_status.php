<?php
// public/check_db_status.php
declare(strict_types=1);

// Enable error reporting explicitly for debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../src/Database.php';

function checkPermissions() {
    $dbPath = __DIR__ . '/../data/database.sqlite';
    $dirPath = __DIR__ . '/../data';
    
    $info = [];
    $info[] = "User: " . get_current_user() . " (UID: " . getmyuid() . ")";
    $info[] = "PHP executing user: " . exec('whoami');
    
    if (file_exists($dbPath)) {
        $info[] = "DB File: Exists";
        $info[] = "DB File Writable: " . (is_writable($dbPath) ? 'YES' : 'NO');
        $info[] = "DB File Owner: " . fileowner($dbPath);
        $info[] = "DB File Perms: " . substr(sprintf('%o', fileperms($dbPath)), -4);
    } else {
        $info[] = "DB File: Missing";
    }
    
    $info[] = "Dir Writable: " . (is_writable($dirPath) ? 'YES' : 'NO');
    $info[] = "Dir Owner: " . fileowner($dirPath);
    $info[] = "Dir Perms: " . substr(sprintf('%o', fileperms($dirPath)), -4);
    
    return implode("<br>", $info);
}

try {
    $db = (new Database())->getConnection();
    
    $checkTable = function($tableName) use ($db) {
        $stmt = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$tableName'");
        return $stmt ? $stmt->fetchColumn() : false;
    };

    $trainingExercisesSql = $checkTable('training_exercises');
    
    // Handle Fix Request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_db'])) {
        try {
            // Disable FK checks during migration to prevent lock issues or strict violations during swap
            $db->exec("PRAGMA foreign_keys = OFF");
            
            $db->beginTransaction();

            // 1. Fix training_exercises
            if (strpos($trainingExercisesSql, 'exercises_old') !== false) {
                // Ensure target IDs exist or clean up bad data?
                // For now, assume data is valid enough or we lose orphans
                
                $db->exec("CREATE TABLE training_exercises_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    training_id INTEGER NOT NULL,
                    exercise_id INTEGER NOT NULL,
                    sort_order INTEGER NOT NULL,
                    duration INTEGER,
                    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
                    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
                )");
                
                // Only copy rows where exercise exists to prevent FK violation later
                $db->exec("INSERT INTO training_exercises_new SELECT * FROM training_exercises WHERE exercise_id IN (SELECT id FROM exercises)");
                
                $db->exec("DROP TABLE training_exercises");
                $db->exec("ALTER TABLE training_exercises_new RENAME TO training_exercises");
                $trainingExercisesSql = $checkTable('training_exercises'); // Refresh
            }

            // 2. Fix exercise_tags
            $exerciseTagsSql = $checkTable('exercise_tags');
            if ($exerciseTagsSql && strpos($exerciseTagsSql, 'exercises_old') !== false) {
                $db->exec("CREATE TABLE exercise_tags_new (
                    exercise_id INTEGER NOT NULL,
                    tag_id INTEGER NOT NULL,
                    PRIMARY KEY (exercise_id, tag_id),
                    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
                )");
                
                $db->exec("INSERT INTO exercise_tags_new SELECT * FROM exercise_tags WHERE exercise_id IN (SELECT id FROM exercises)");
                
                $db->exec("DROP TABLE exercise_tags");
                $db->exec("ALTER TABLE exercise_tags_new RENAME TO exercise_tags");
            }
            
            $db->commit();
            
            // Re-enable FK
            $db->exec("PRAGMA foreign_keys = ON");
            
            echo "<div style='background: #d4edda; color: #155724; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;'>Database successfully repaired!</div>";
        } catch (Exception $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            echo "<div style='background: #f8d7da; color: #721c24; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;'>";
            echo "Repair failed: " . htmlspecialchars($ex->getMessage());
            echo "<br>Trace: <pre>" . $ex->getTraceAsString() . "</pre>";
            echo "</div>";
        }
    }
    
    echo "<h1>Database Check</h1>";
    echo "<div style='font-family: monospace; background: #f0f0f0; padding: 10px; margin-bottom: 20px;'>" . checkPermissions() . "</div>";

    
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
