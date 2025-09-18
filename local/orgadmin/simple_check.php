<?php
// Simple check to see what's in the database
require_once(__DIR__ . '/../../config.php');

require_login();

echo "<h1>Simple Database Check</h1>";

echo "<p>Current user: " . fullname($USER) . " (ID: " . $USER->id . ")</p>";
echo "<p>Is admin: " . (is_siteadmin() ? 'YES' : 'NO') . "</p>";

try {
    // Check if table exists
    $dbman = $DB->get_manager();
    $table = new xmldb_table('orgadmin_assessments');
    $exists = $dbman->table_exists($table);

    echo "<p>Table exists: " . ($exists ? 'YES' : 'NO') . "</p>";

    if ($exists) {
        // Get all assessments
        $assessments = $DB->get_records('orgadmin_assessments');
        echo "<p>Total assessments: " . count($assessments) . "</p>";

        // Get pending only
        $pending = $DB->get_records('orgadmin_assessments', ['status' => 'pending_review']);
        echo "<p>Pending assessments: " . count($pending) . "</p>";

        if (!empty($pending)) {
            echo "<h2>Pending Assessments:</h2>";
            echo "<ul>";
            foreach ($pending as $assessment) {
                echo "<li>ID: {$assessment->id}, Title: {$assessment->title}, Status: {$assessment->status}</li>";
            }
            echo "</ul>";
        }
    }

} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='lnd_dashboard.php'>Go to LND Dashboard</a>";
?>