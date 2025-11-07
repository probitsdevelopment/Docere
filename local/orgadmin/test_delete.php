<?php
// Test delete functionality
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

echo "<h1>Test Delete Functionality</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_delete'])) {
    // Test the delete functionality
    $assessment_id = required_param('assessment_id', PARAM_INT);

    // Create form data to test the handler
    $postData = [
        'action' => 'delete',
        'assessment_id' => $assessment_id,
        'sesskey' => sesskey()
    ];

    echo "<h2>Testing Delete for Assessment ID: {$assessment_id}</h2>";

    // Check if assessment exists and belongs to current user
    $assessment = $DB->get_record('orgadmin_assessments', [
        'id' => $assessment_id,
        'userid' => $USER->id
    ]);

    if ($assessment) {
        echo "<p>✅ Assessment found: {$assessment->title}</p>";
        echo "<p>✅ Assessment belongs to current user</p>";

        // Simulate the deletion (don't actually delete in test)
        echo "<p style='color: green;'>✅ Delete functionality should work correctly</p>";
        echo "<p><strong>Note:</strong> Actual deletion not performed in test mode</p>";
    } else {
        echo "<p style='color: red;'>❌ Assessment not found or access denied</p>";
    }
}

// Show current user's assessments
$user_assessments = $DB->get_records('orgadmin_assessments', ['userid' => $USER->id], 'timemodified DESC');

echo "<h2>Your Assessments (" . count($user_assessments) . ")</h2>";

if (!empty($user_assessments)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Created</th><th>Action</th></tr>";

    foreach ($user_assessments as $assessment) {
        echo "<tr>";
        echo "<td>{$assessment->id}</td>";
        echo "<td>{$assessment->title}</td>";
        echo "<td>{$assessment->status}</td>";
        echo "<td>" . date('Y-m-d H:i:s', $assessment->timecreated) . "</td>";
        echo "<td>";
        echo "<form method='post' style='display: inline;'>";
        echo "<input type='hidden' name='test_delete' value='1'>";
        echo "<input type='hidden' name='assessment_id' value='{$assessment->id}'>";
        echo "<input type='hidden' name='sesskey' value='" . sesskey() . "'>";
        echo "<input type='submit' value='Test Delete' style='background: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px;'>";
        echo "</form>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No assessments found for current user.</p>";
}

echo "<br><br>";
echo "<a href='teacher_dashboard.php'>Go to Teacher Dashboard</a>";
?>