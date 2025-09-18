<?php
// Debug page to check assessments in database
require_once(__DIR__ . '/../../config.php');

require_login();

echo "<h1>Assessment Debug Page</h1>";

try {
    // Check if table exists
    $tables = $DB->get_tables();
    echo "<h2>Database Tables:</h2>";
    $table_exists = false;
    foreach ($tables as $table) {
        if (strpos($table, 'orgadmin_assessments') !== false) {
            echo "<p>✅ Found table: $table</p>";
            $table_exists = true;
        }
    }

    if (!$table_exists) {
        echo "<p>❌ orgadmin_assessments table not found</p>";
        echo "<p>Available tables containing 'orgadmin':</p>";
        foreach ($tables as $table) {
            if (strpos($table, 'orgadmin') !== false) {
                echo "<p>- $table</p>";
            }
        }
    }

    // Try to get all assessments
    echo "<h2>All Assessments:</h2>";
    $assessments = $DB->get_records('orgadmin_assessments');

    if (empty($assessments)) {
        echo "<p>❌ No assessments found in database</p>";
    } else {
        echo "<p>✅ Found " . count($assessments) . " assessments</p>";

        foreach ($assessments as $assessment) {
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px;'>";
            echo "<strong>ID:</strong> " . $assessment->id . "<br>";
            echo "<strong>Title:</strong> " . htmlspecialchars($assessment->title) . "<br>";
            echo "<strong>Status:</strong> " . $assessment->status . "<br>";
            echo "<strong>User ID:</strong> " . $assessment->userid . "<br>";
            echo "<strong>Duration:</strong> " . $assessment->duration . "<br>";
            echo "<strong>Created:</strong> " . date('Y-m-d H:i:s', $assessment->timecreated) . "<br>";
            echo "<strong>Modified:</strong> " . date('Y-m-d H:i:s', $assessment->timemodified) . "<br>";

            // Get user info
            $user = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname');
            if ($user) {
                echo "<strong>Creator:</strong> " . $user->firstname . " " . $user->lastname . "<br>";
            }
            echo "</div>";
        }
    }

    // Check specifically for pending_review
    echo "<h2>Pending Review Assessments:</h2>";
    $pending = $DB->get_records('orgadmin_assessments', ['status' => 'pending_review']);

    if (empty($pending)) {
        echo "<p>❌ No pending review assessments found</p>";
    } else {
        echo "<p>✅ Found " . count($pending) . " pending review assessments</p>";
        foreach ($pending as $assessment) {
            echo "<div style='border: 2px solid #f39c12; padding: 10px; margin: 10px;'>";
            echo "<strong>Title:</strong> " . htmlspecialchars($assessment->title) . "<br>";
            echo "<strong>Status:</strong> " . $assessment->status . "<br>";
            $user = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname');
            if ($user) {
                echo "<strong>Creator:</strong> " . $user->firstname . " " . $user->lastname . "<br>";
            }
            echo "</div>";
        }
    }

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Trace: " . $e->getTraceAsString() . "</p>";
}

echo "<br><a href='teacher_dashboard.php'>Back to Teacher Dashboard</a>";
echo "<br><a href='lnd_dashboard.php'>Back to LND Dashboard</a>";
?>