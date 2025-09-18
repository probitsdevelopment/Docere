<?php
// Direct test - bypass all role checking
require_once(__DIR__ . '/../../config.php');

require_login();

echo "<h1>Direct Assessment Test</h1>";

try {
    // Direct database query
    $assessments = $DB->get_records('orgadmin_assessments', ['status' => 'pending_review']);

    echo "<p>Found " . count($assessments) . " pending assessments:</p>";

    if (!empty($assessments)) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Title</th><th>Creator</th><th>Status</th></tr>";

        foreach ($assessments as $assessment) {
            $user = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname');
            $creator = $user ? $user->firstname . ' ' . $user->lastname : 'Unknown';

            echo "<tr>";
            echo "<td>" . $assessment->id . "</td>";
            echo "<td>" . htmlspecialchars($assessment->title) . "</td>";
            echo "<td>" . htmlspecialchars($creator) . "</td>";
            echo "<td>" . $assessment->status . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Test conversion to LND format
        echo "<h2>Converted to LND Format:</h2>";
        $lndAssessments = [];

        foreach ($assessments as $assessment) {
            $user = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname');
            $creator_name = $user ? $user->firstname . ' ' . $user->lastname : 'Unknown User';

            $trainer_type = '';
            if ($assessment->organization_id === 0 || $assessment->organization_id === null) {
                $trainer_type = ' (Site Trainer)';
            } else {
                $trainer_type = ' (Org Trainer)';
            }

            $lndAssessments[] = [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'creator' => $creator_name . $trainer_type,
                'questions' => 1,
                'time' => $assessment->duration ?: 45,
                'students' => 0,
                'organization_id' => $assessment->organization_id
            ];
        }

        echo "<ul>";
        foreach ($lndAssessments as $assessment) {
            echo "<li><strong>" . htmlspecialchars($assessment['title']) . "</strong> by " . htmlspecialchars($assessment['creator']) . "</li>";
        }
        echo "</ul>";

    } else {
        echo "<p>No pending assessments found in database.</p>";
    }

} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='lnd_dashboard.php'>Go to LND Dashboard</a>";
?>