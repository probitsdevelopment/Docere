<?php
// Debug delete functionality
require_once(__DIR__ . '/../../config.php');

require_login();

echo "<h1>Debug Delete Functionality</h1>";

// Show current user info
echo "<p><strong>Current User:</strong> " . fullname($USER) . " (ID: {$USER->id})</p>";

// Show all assessments for current user
$user_assessments = $DB->get_records('orgadmin_assessments', ['userid' => $USER->id], 'timemodified DESC');

echo "<h2>Your Assessments (" . count($user_assessments) . ")</h2>";

if (!empty($user_assessments)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f5f5f5;'><th>ID</th><th>Title</th><th>Status</th><th>Created</th><th>Direct Delete Test</th></tr>";

    foreach ($user_assessments as $assessment) {
        echo "<tr>";
        echo "<td>{$assessment->id}</td>";
        echo "<td>{$assessment->title}</td>";
        echo "<td>{$assessment->status}</td>";
        echo "<td>" . date('Y-m-d H:i:s', $assessment->timecreated) . "</td>";
        echo "<td>";

        // Direct form to assessment handler
        echo "<form method='post' action='" . $CFG->wwwroot . "/local/orgadmin/assessment_handler.php' style='display: inline;'>";
        echo "<input type='hidden' name='sesskey' value='" . sesskey() . "'>";
        echo "<input type='hidden' name='action' value='delete'>";
        echo "<input type='hidden' name='assessment_id' value='{$assessment->id}'>";
        echo "<input type='hidden' name='return_url' value='" . (new moodle_url('/local/orgadmin/debug_delete.php'))->out() . "'>";
        echo "<input type='submit' value='DELETE' style='background: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px;' onclick='return confirm(\"Delete {$assessment->title}?\");'>";
        echo "</form>";

        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // JavaScript test
    echo "<h2>JavaScript Delete Test</h2>";
    echo "<p>Test the same deleteAssessment function used in teacher dashboard:</p>";

    $first_assessment = reset($user_assessments);
    echo "<button onclick='testDelete({$first_assessment->id}, \"{$first_assessment->title}\")' style='background: #007bff; color: white; padding: 10px; border: none; border-radius: 3px;'>Test JavaScript Delete</button>";

} else {
    echo "<p>No assessments found. <a href='teacher_dashboard.php'>Create one first</a>.</p>";
}

echo "<br><br><a href='teacher_dashboard.php'>Back to Teacher Dashboard</a>";
?>

<script>
// Copy of the delete function from teacher dashboard
function testDelete(id, title) {
    console.log('TEST DELETE FUNCTION CALLED: ID=' + id + ', Title=' + title);

    if (confirm('TEST: Are you sure you want to delete assessment "' + title + '"?')) {
        console.log('TEST DELETE CONFIRMED - Creating form submission');

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = M.cfg.wwwroot + '/local/orgadmin/assessment_handler.php';

        console.log('Form action URL: ' + form.action);

        // Add CSRF token
        var sesskey = document.createElement('input');
        sesskey.type = 'hidden';
        sesskey.name = 'sesskey';
        sesskey.value = M.cfg.sesskey;
        form.appendChild(sesskey);

        // Add action
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        form.appendChild(actionInput);

        // Add assessment ID
        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'assessment_id';
        idInput.value = id;
        form.appendChild(idInput);

        // Add return URL
        var returnInput = document.createElement('input');
        returnInput.type = 'hidden';
        returnInput.name = 'return_url';
        returnInput.value = window.location.href;
        form.appendChild(returnInput);

        // Submit form
        document.body.appendChild(form);
        console.log('SUBMITTING TEST DELETE FORM');
        form.submit();
    } else {
        console.log('TEST DELETE CANCELLED');
    }
}
</script>