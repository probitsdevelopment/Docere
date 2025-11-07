<?php
// Test L&D dashboard functions specifically
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

if (!is_siteadmin()) {
    die('Access denied - admin only');
}

echo "<h1>L&D Dashboard Functions Test</h1>";

try {
    // Test the functions used in L&D dashboard
    echo "<h2>Function Tests</h2>";

    // Include the L&D dashboard functions
    require_once(__DIR__ . '/lnd_dashboard.php');

    echo "<h3>1. get_lnd_organization_id()</h3>";
    $lnd_org_id = get_lnd_organization_id();
    echo "<p>Result: " . ($lnd_org_id !== null ? $lnd_org_id : 'null') . "</p>";

    echo "<h3>2. get_pending_assessments_for_lnd()</h3>";
    $pending = get_pending_assessments_for_lnd();
    echo "<p>Found " . count($pending) . " pending assessments</p>";

    if (!empty($pending)) {
        echo "<ul>";
        foreach ($pending as $assessment) {
            echo "<li>ID: {$assessment['id']}, Title: {$assessment['title']}, Creator: {$assessment['creator']}</li>";
        }
        echo "</ul>";
    }

    echo "<h3>3. Test Assessment Handler POST</h3>";
    $handler_url = $CFG->wwwroot . '/local/orgadmin/assessment_handler.php';
    echo "<p>Handler URL: {$handler_url}</p>";

    // Test if we can detect any pending assessments
    $all_pending = $DB->get_records('orgadmin_assessments', ['status' => 'pending_review']);
    echo "<p>Direct DB query found " . count($all_pending) . " pending assessments</p>";

    if (!empty($all_pending)) {
        echo "<h3>4. Test Reject Form</h3>";
        $first_assessment = reset($all_pending);

        echo "<form method='post' action='{$handler_url}'>";
        echo "<input type='hidden' name='sesskey' value='" . sesskey() . "'>";
        echo "<input type='hidden' name='action' value='reject'>";
        echo "<input type='hidden' name='assessment_id' value='{$first_assessment->id}'>";
        echo "<input type='hidden' name='reason' value='Test rejection'>";
        echo "<input type='hidden' name='return_url' value='" . (new moodle_url('/local/orgadmin/test_lnd_functions.php'))->out() . "'>";
        echo "<p><input type='submit' value='Test Reject Assessment ID {$first_assessment->id}' style='background: #dc3545; color: white; padding: 10px;'></p>";
        echo "</form>";
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0;'>";
    echo "❌ Error: " . $e->getMessage();
    echo "<br>Trace: " . $e->getTraceAsString();
    echo "</div>";
}

echo "<br><br>";
echo "<a href='test_workflow.php'>Back to Workflow Test</a> | ";
echo "<a href='lnd_dashboard.php'>Go to L&D Dashboard</a>";
?>