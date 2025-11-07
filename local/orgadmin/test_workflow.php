<?php
// Test the complete assessment workflow
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

if (!is_siteadmin()) {
    die('Access denied - admin only');
}

echo "<h1>Assessment Workflow Test</h1>";

try {
    // 1. Check current assessments
    $assessments = $DB->get_records('orgadmin_assessments', [], 'timemodified DESC');
    echo "<h2>Current Assessments (" . count($assessments) . ")</h2>";

    if (!empty($assessments)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Creator</th><th>Org ID</th><th>Created</th></tr>";
        foreach ($assessments as $assessment) {
            $user = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname');
            $creator = $user ? $user->firstname . ' ' . $user->lastname : 'Unknown';
            echo "<tr>";
            echo "<td>{$assessment->id}</td>";
            echo "<td>{$assessment->title}</td>";
            echo "<td>{$assessment->status}</td>";
            echo "<td>{$creator}</td>";
            echo "<td>{$assessment->organization_id}</td>";
            echo "<td>" . date('Y-m-d H:i:s', $assessment->timecreated) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No assessments found.</p>";
    }

    // 2. Test assessment handler accessibility
    $handler_url = new moodle_url('/local/orgadmin/assessment_handler.php');
    echo "<h2>Assessment Handler Test</h2>";
    echo "<p>Handler URL: " . $handler_url->out() . "</p>";

    // 3. Test L&D role detection
    echo "<h2>Role Detection Test</h2>";
    echo "<p>Should show teacher dashboard: " . (orgadmin_role_detector::should_show_teacher_dashboard() ? 'YES' : 'NO') . "</p>";
    echo "<p>Should show L&D dashboard: " . (orgadmin_role_detector::should_show_lnd_dashboard() ? 'YES' : 'NO') . "</p>";
    echo "<p>Is site admin: " . (is_siteadmin() ? 'YES' : 'NO') . "</p>";

    // 4. Test creating a sample assessment
    echo "<h2>Test Assessment Creation</h2>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='test_action' value='create_test'>";
    echo "<p><input type='submit' value='Create Test Assessment' style='padding: 10px; font-size: 14px;'></p>";
    echo "</form>";

    // Handle test assessment creation
    if (isset($_POST['test_action']) && $_POST['test_action'] === 'create_test') {
        $record = new stdClass();
        $record->userid = $USER->id;
        $record->organization_id = 0; // Site trainer
        $record->title = 'Test Assessment ' . date('Y-m-d H:i:s');
        $record->duration = 45;
        $record->total_marks = 100;
        $record->pass_percentage = 60;
        $record->language = 'English';
        $record->instructions = 'Test instructions';
        $record->status = 'pending_review';
        $record->timecreated = time();
        $record->timemodified = time();

        $id = $DB->insert_record('orgadmin_assessments', $record);
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0;'>";
        echo "✅ Created test assessment with ID: {$id}";
        echo "</div>";

        // Refresh the page to show updated data
        echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0;'>";
    echo "❌ Error: " . $e->getMessage();
    echo "</div>";
}

echo "<br><br>";
echo "<a href='lnd_dashboard.php' style='padding: 10px; background: #007bff; color: white; text-decoration: none;'>Go to L&D Dashboard</a> ";
echo "<a href='teacher_dashboard.php' style='padding: 10px; background: #28a745; color: white; text-decoration: none;'>Go to Teacher Dashboard</a>";
?>